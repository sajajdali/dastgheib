<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = collect(config('roles.groups', []))
            ->flatMap(fn (array $group) => $group['permissions'] ?? [])
            ->pluck('name')
            ->unique()
            ->values();

        $permissionNames->each(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]));

        $permissionsByPrefix = fn (array $prefixes) => $permissionNames
            ->filter(fn (string $name) => collect($prefixes)->contains(
                fn (string $prefix) => str_starts_with($name, $prefix.'.')
            ));

        $rolePermissions = [
            'مدیر سیستم' => $permissionNames,
            'مدیر مجموعه' => $permissionNames->reject(fn (string $name) => $name === 'roles.manage'),
            'پذیرش' => $permissionsByPrefix(['patients', 'appointments'])
                ->merge(['services.view', 'followups.view', 'followups.create']),
            'پزشک' => collect([
                'patients.view', 'patients.update', 'appointments.view',
                'appointments.update', 'services.view',
            ]),
            'مشاور' => $permissionsByPrefix(['followups'])
                ->merge(['patients.view', 'patients.view_phone', 'appointments.view', 'appointments.update', 'services.view']),
            'حسابدار' => $permissionsByPrefix(['reports', 'bills'])
                ->merge(['appointments.income', 'inventory.view', 'inventory.cost']),
            'انباردار' => $permissionsByPrefix(['inventory']),
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions->unique()->values()->all());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
