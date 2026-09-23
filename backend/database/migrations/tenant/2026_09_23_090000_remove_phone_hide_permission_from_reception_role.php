<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::query()
            ->where('name', 'پذیرش')
            ->where('guard_name', 'web')
            ->first();
        $permission = Permission::query()
            ->where('name', 'patients.hide_phone')
            ->where('guard_name', 'web')
            ->first();

        if ($role && $permission) {
            $role->revokePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // This migration resolves an invalid pair of opposing permissions.
        // Restoring the deny permission automatically would hide patient phone
        // numbers again, so rollback intentionally leaves the role unchanged.
    }
};
