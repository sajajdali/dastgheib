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

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name');

        $generalPermissions = $permissions
            ->filter(fn (string $name) => str_ends_with($name, '.view'))
            ->merge(['patients.view_phone', 'attendance.clock'])
            ->filter(fn (string $name) => $permissions->contains($name))
            ->unique()
            ->values()
            ->all();

        Role::firstOrCreate([
            'name' => 'پرسنل مجموعه',
            'guard_name' => 'web',
        ])->syncPermissions($generalPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()
            ->where('name', 'پرسنل مجموعه')
            ->where('guard_name', 'web')
            ->first()?->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
