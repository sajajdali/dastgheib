<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    /** همهٔ کاربران کلینیک می‌توانند گفت‌وگو کنند و پیام‌های خودشان را ویرایش یا حذف کنند. */
    private const MEMBER_PERMISSIONS = [
        'group_chat.view',
        'group_chat.send',
        'group_chat.edit',
        'group_chat.delete',
    ];

    /** حذف پیام دیگران فقط برای مدیران. */
    private const MANAGER_PERMISSIONS = ['group_chat.moderate'];

    private const MANAGER_ROLES = ['مدیر سیستم', 'مدیر کل', 'مدیر مجموعه', 'super admin', 'super-admin'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $member = collect(self::MEMBER_PERMISSIONS)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        $manager = collect(self::MANAGER_PERMISSIONS)
            ->map(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));

        Role::query()->where('guard_name', 'web')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($member));
        Role::query()->whereIn('name', self::MANAGER_ROLES)->get()
            ->each(fn (Role $role) => $role->givePermissionTo($manager));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()
            ->whereIn('name', [...self::MEMBER_PERMISSIONS, ...self::MANAGER_PERMISSIONS])
            ->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
