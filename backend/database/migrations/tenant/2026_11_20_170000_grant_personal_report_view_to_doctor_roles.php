<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * نقش پزشک/مشاور باید گزارش شخصی ببیند؛ مهاجرت قدیمی فقط به نقش‌های دارای
 * payroll.view می‌داد و پزشک را جا می‌گذاشت.
 */
return new class extends Migration {
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'personal_report.view',
            'guard_name' => 'web',
        ]);

        Role::query()
            ->whereIn('name', ['پزشک', 'مشاور', 'پرسنل مجموعه'])
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // نگه می‌داریم؛ برداشتن مجوز از نقش‌های عملیاتی ورود پزشک را دوباره می‌شکند.
    }
};
