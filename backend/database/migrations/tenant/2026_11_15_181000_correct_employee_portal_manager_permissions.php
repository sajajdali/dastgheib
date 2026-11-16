<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    private const MANAGER_PERMISSIONS = [
        'employee_portal.manage',
        'employee_portal.templates_manage',
        'employee_portal.adjustments_manage',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = Permission::query()->whereIn('name', self::MANAGER_PERMISSIONS)->get();

        Role::query()->whereIn('name', ['پرسنل مجموعه', 'پزشک', 'مشاور'])
            ->get()->each(fn (Role $role) => $role->revokePermissionTo($permissions));

        Role::query()->whereIn('name', ['مدیر سیستم', 'مدیر کل', 'مدیر مجموعه', 'super admin', 'super-admin'])
            ->get()->each(fn (Role $role) => $role->givePermissionTo($permissions));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // This migration only repairs an overly broad grant; rolling it back
        // must not restore the insecure permissions.
    }
};
