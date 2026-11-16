<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    private const PERMISSIONS = [
        'personal_report.details',
        'employee_portal.leave_request',
        'employee_portal.advance_request',
        'employee_portal.message',
        'employee_portal.manage',
        'employee_portal.templates_manage',
        'employee_portal.adjustments_manage',
    ];

    public function up(): void
    {
        foreach (['doctors', 'staff'] as $resourceTable) {
            Schema::table($resourceTable, function (Blueprint $table) use ($resourceTable) {
                if (! Schema::hasColumn($resourceTable, 'personal_report_columns')) {
                    $table->json('personal_report_columns')->nullable();
                }
            });
        }

        Schema::table('resource_adjustments', function (Blueprint $table) {
            if (! Schema::hasColumn('resource_adjustments', 'kind')) $table->string('kind', 20)->default('adjustment')->index();
            if (! Schema::hasColumn('resource_adjustments', 'occurred_on')) $table->string('occurred_on', 10)->nullable()->index();
            if (! Schema::hasColumn('resource_adjustments', 'title')) $table->string('title')->nullable();
            if (! Schema::hasColumn('resource_adjustments', 'description')) $table->text('description')->nullable();
            if (! Schema::hasColumn('resource_adjustments', 'status')) $table->string('status', 20)->default('active')->index();
            if (! Schema::hasColumn('resource_adjustments', 'created_by_name')) $table->string('created_by_name')->nullable();
        });

        Schema::create('employee_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 20)->index();
            $table->unsignedBigInteger('resource_id')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('leave_type', 20);
            $table->string('start_date', 10)->index();
            $table->string('end_date', 10)->index();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('manager_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['resource_type', 'resource_id', 'start_date'], 'leave_resource_date_idx');
        });

        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 20)->index();
            $table->unsignedBigInteger('resource_id')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('requested_amount', 15, 2);
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->text('reason')->nullable();
            $table->text('manager_note')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->boolean('deduct_from_salary')->default(true);
            $table->unsignedSmallInteger('installment_count')->default(1);
            $table->string('deduction_start_month', 7)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['resource_type', 'resource_id', 'status'], 'advance_resource_status_idx');
        });

        Schema::create('salary_advance_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_advance_id')->constrained('salary_advances')->cascadeOnDelete();
            $table->string('month', 7)->index();
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('scheduled');
            $table->timestamp('deducted_at')->nullable();
            $table->timestamps();
            $table->unique(['salary_advance_id', 'month'], 'advance_month_unique');
        });

        Schema::create('employee_messages', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 20)->index();
            $table->unsignedBigInteger('resource_id')->index();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_name')->nullable();
            $table->text('body')->nullable();
            $table->string('image_path')->nullable();
            $table->string('voice_path')->nullable();
            $table->unsignedInteger('voice_duration')->nullable();
            $table->timestamp('read_by_employee_at')->nullable();
            $table->timestamp('read_by_manager_at')->nullable();
            $table->timestamps();
            $table->index(['resource_type', 'resource_id', 'created_at'], 'employee_message_thread_idx');
        });

        Schema::create('employee_portal_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('resource_type', 20)->nullable()->index();
            $table->unsignedBigInteger('resource_id')->nullable()->index();
            $table->string('type', 40)->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('action_tab', 40)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
            $table->index(['user_id', 'read_at', 'created_at'], 'employee_notification_user_idx');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = collect(self::PERMISSIONS)->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        Role::query()->whereIn('name', ['مدیر سیستم', 'مدیر کل', 'مدیر مجموعه', 'super admin', 'super-admin'])
            ->get()->each(fn (Role $role) => $role->givePermissionTo($permissions->all()));

        Role::query()->whereIn('name', ['پزشک', 'پرسنل مجموعه', 'مشاور'])
            ->get()->each(fn (Role $role) => $role->givePermissionTo($permissions->whereIn('name', [
                'personal_report.details', 'employee_portal.leave_request',
                'employee_portal.advance_request', 'employee_portal.message',
            ])->all()));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_portal_notifications');
        Schema::dropIfExists('employee_messages');
        Schema::dropIfExists('salary_advance_installments');
        Schema::dropIfExists('salary_advances');
        Schema::dropIfExists('employee_leave_requests');

        Schema::table('resource_adjustments', fn (Blueprint $table) => $table->dropColumn([
            'kind', 'occurred_on', 'title', 'description', 'status', 'created_by_name',
        ]));
        foreach (['doctors', 'staff'] as $resourceTable) {
            Schema::table($resourceTable, fn (Blueprint $table) => $table->dropColumn('personal_report_columns'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
