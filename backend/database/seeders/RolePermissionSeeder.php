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

        // نقش عمومی: امکان مشاهده همه بخش‌ها و انجام ثبت ورود/خروج،
        // بدون دسترسی‌های مدیریتی، مالی، حذف یا تنظیمات سیستم.
        $phoneHidePermission = 'patients.hide_phone';
        $defaultManagerPermissions = $permissionNames
            ->reject(fn (string $name) => $name === $phoneHidePermission);

        $generalStaffPermissions = $permissionNames
            ->filter(fn (string $name) => str_ends_with($name, '.view'))
            ->merge(['patients.view_phone', 'attendance.clock', 'personal_report.details',
                'employee_portal.leave_request', 'employee_portal.advance_request', 'employee_portal.message'])
            ->reject(fn (string $name) => $name === $phoneHidePermission)
            ->filter(fn (string $name) => $permissionNames->contains($name));

        $rolePermissions = [
            'مدیر سیستم' => $defaultManagerPermissions,
            'مدیر مجموعه' => $defaultManagerPermissions->reject(fn (string $name) => $name === 'roles.manage'),
            'پرسنل مجموعه' => $generalStaffPermissions,
            'پذیرش' => $permissionsByPrefix(['patients', 'appointments'])
                ->reject(fn (string $name) => $name === 'appointments.finalized_services_update')
                ->merge(['services.view', 'followups.view', 'followups.create']),
            'پزشک' => collect([
                'patients.view', 'patients.update', 'appointments.view',
                'appointments.update', 'appointments.past_view', 'appointments.past_update', 'services.view',
                'personal_report.view', 'personal_report.details', 'employee_portal.leave_request',
                'employee_portal.advance_request', 'employee_portal.message',
            ]),
            'مشاور' => $permissionsByPrefix(['followups'])
                ->merge(['patients.view', 'patients.view_phone', 'appointments.view', 'appointments.update', 'appointments.past_view', 'appointments.past_update', 'services.view',
                    'personal_report.view', 'personal_report.details', 'employee_portal.leave_request', 'employee_portal.advance_request', 'employee_portal.message']),
            'حسابدار' => $permissionsByPrefix(['reports', 'bills'])
                ->merge(['appointments.income', 'inventory.view', 'inventory.cost']),
            'انباردار' => $permissionsByPrefix(['inventory']),
        ];

        // چت گروهی: همهٔ نقش‌ها گفت‌وگو می‌کنند؛ حذف پیام دیگران فقط برای مدیران است.
        $groupChatMemberPermissions = ['group_chat.view', 'group_chat.send', 'group_chat.edit', 'group_chat.delete'];
        foreach ($rolePermissions as $roleName => $permissions) {
            $rolePermissions[$roleName] = $permissions->merge($groupChatMemberPermissions)
                ->filter(fn (string $name) => $permissionNames->contains($name));
        }

        // Reception needs the real phone number while finding a patient and
        // creating an appointment. Prefix-based collection above also includes
        // the explicit deny permission, so remove that contradictory grant.
        $rolePermissions['پذیرش'] = $rolePermissions['پذیرش']
            ->reject(fn (string $name) => $name === $phoneHidePermission);

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissions->unique()->values()->all());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
