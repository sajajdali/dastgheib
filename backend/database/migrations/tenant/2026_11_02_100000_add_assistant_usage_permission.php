<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::firstOrCreate(['name' => 'assistant.usage.view', 'guard_name' => 'web']);
        Role::query()->whereIn('name', ['مدیر سیستم', 'مدیر کل', 'مدیر مجموعه', 'super admin', 'super-admin'])
            ->get()->each(fn (Role $role) => $role->givePermissionTo($permission));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->where('name', 'assistant.usage.view')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
