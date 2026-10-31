<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_assistant_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150)->nullable();
            $table->string('model', 80);
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });
        Schema::create('ai_assistant_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_assistant_conversations')->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('content');
            $table->json('presentation')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['assistant.use', 'assistant.manage'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            Role::query()->whereIn('name', ['مدیر سیستم', 'مدیر کل', 'مدیر مجموعه', 'super admin', 'super-admin'])
                ->get()->each(fn (Role $role) => $role->givePermissionTo($permission));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_messages');
        Schema::dropIfExists('ai_assistant_conversations');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->whereIn('name', ['assistant.use', 'assistant.manage'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
