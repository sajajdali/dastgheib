<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    private const PERMISSIONS = [
        'staff_tasks.view',
        'staff_tasks.manage',
    ];

    public function up(): void
    {
        Schema::create('staff_task_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedTinyInteger('color')->default(0);
            $table->string('effect', 10)->default('none'); // plus|minus|none
            $table->unsignedBigInteger('amount')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('staff_task_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expiry_default_status_id')->nullable()->constrained('staff_task_statuses')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('staff_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('note')->nullable();
            $table->date('task_date')->index();
            $table->string('priority', 20)->default('normal'); // low|normal|high|urgent
            $table->unsignedTinyInteger('progress')->default(0);
            $table->foreignId('status_id')->nullable()->constrained('staff_task_statuses')->nullOnDelete();
            $table->boolean('auto_expired')->default(false);
            $table->boolean('expiry_enabled')->default(false);
            $table->date('expiry_date')->nullable()->index();
            $table->time('expiry_time')->nullable();
            $table->foreignId('expiry_status_id')->nullable()->constrained('staff_task_statuses')->nullOnDelete();
            $table->string('image_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
            $table->index(['task_date', 'status_id']);
        });

        Schema::create('staff_task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_task_id')->constrained('staff_tasks')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['staff_task_id', 'staff_id']);
        });

        Schema::create('staff_task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_task_id')->constrained('staff_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('uploader_name')->nullable();
            $table->string('kind', 20)->default('file'); // image|file
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_task_voices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_task_id')->constrained('staff_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('uploader_name')->nullable();
            $table->string('path');
            $table->unsignedInteger('duration')->nullable();
            $table->timestamps();
            $table->index(['staff_task_id', 'created_at']);
        });

        if (Schema::hasTable('resource_adjustments') && ! Schema::hasColumn('resource_adjustments', 'source_task_id')) {
            Schema::table('resource_adjustments', function (Blueprint $table) {
                $table->unsignedBigInteger('source_task_id')->nullable()->index()->after('resource_id');
            });
        }

        $now = now();
        $defaults = [
            ['title' => 'انجام شد', 'color' => 0, 'effect' => 'plus', 'amount' => 100000, 'sort_order' => 1, 'is_system' => true],
            ['title' => 'تکمیل نشد', 'color' => 1, 'effect' => 'minus', 'amount' => 50000, 'sort_order' => 2, 'is_system' => true],
            ['title' => 'پیگیری', 'color' => 2, 'effect' => 'none', 'amount' => 0, 'sort_order' => 3, 'is_system' => true],
            ['title' => 'انجام نشد', 'color' => 3, 'effect' => 'minus', 'amount' => 100000, 'sort_order' => 4, 'is_system' => true],
        ];
        foreach ($defaults as $row) {
            DB::table('staff_task_statuses')->insert([...$row, 'created_at' => $now, 'updated_at' => $now]);
        }
        $notDoneId = DB::table('staff_task_statuses')->where('title', 'انجام نشد')->value('id');
        DB::table('staff_task_settings')->insert([
            'expiry_default_status_id' => $notDoneId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = collect(self::PERMISSIONS)->map(fn ($name) => Permission::findOrCreate($name, 'web'));
        Role::query()->whereIn('name', ['مدیر سیستم', 'مدیر کل', 'مدیر مجموعه', 'super admin', 'super-admin'])
            ->get()->each(fn (Role $role) => $role->givePermissionTo($permissions->all()));
        Role::query()->whereIn('name', ['پرسنل مجموعه', 'پزشک', 'مشاور'])
            ->get()->each(fn (Role $role) => $role->givePermissionTo($permissions->firstWhere('name', 'staff_tasks.view')));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('resource_adjustments') && Schema::hasColumn('resource_adjustments', 'source_task_id')) {
            Schema::table('resource_adjustments', fn (Blueprint $table) => $table->dropColumn('source_task_id'));
        }
        Schema::dropIfExists('staff_task_voices');
        Schema::dropIfExists('staff_task_attachments');
        Schema::dropIfExists('staff_task_assignees');
        Schema::dropIfExists('staff_tasks');
        Schema::dropIfExists('staff_task_settings');
        Schema::dropIfExists('staff_task_statuses');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->whereIn('name', self::PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
