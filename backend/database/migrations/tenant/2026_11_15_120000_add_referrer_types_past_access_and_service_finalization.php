<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * معرف نوبت (مشتری / پزشک / پرسنل)، پورسانت معرفی منابع، دسترسی نوبت‌های گذشته و ویرایش خدمات ثبت نهایی‌شده.
 */
return new class extends Migration {
    private const PAST_PERMISSIONS = ['appointments.past_view', 'appointments.past_update'];
    private const FINALIZED_PERMISSION = 'appointments.finalized_services_update';
    private const MANAGER_ROLES = ['مدیر سیستم', 'مدیر کل', 'مدیر مجموعه'];

    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'referrer_type')) $table->string('referrer_type', 20)->nullable()->after('referrer_phone');
            if (! Schema::hasColumn('appointments', 'referrer_id')) $table->unsignedBigInteger('referrer_id')->nullable()->after('referrer_type');
            if (! Schema::hasColumn('appointments', 'referrer_name')) $table->string('referrer_name')->nullable()->after('referrer_id');
            if (! Schema::hasColumn('appointments', 'referral_commission_snapshot')) $table->json('referral_commission_snapshot')->nullable()->after('referral_commission_value');
        });

        foreach (['doctors', 'staff'] as $resourceTable) {
            Schema::table($resourceTable, function (Blueprint $table) use ($resourceTable) {
                if (! Schema::hasColumn($resourceTable, 'referral_commission_type')) $table->string('referral_commission_type', 10)->nullable();
                if (! Schema::hasColumn($resourceTable, 'referral_commission_value')) $table->decimal('referral_commission_value', 15, 2)->nullable();
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $past = collect(self::PAST_PERMISSIONS)->map(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        $finalized = Permission::firstOrCreate(['name' => self::FINALIZED_PERMISSION, 'guard_name' => 'web']);

        // تا امروز هر نقشی که نوبت‌ها را می‌دید روزهای گذشته را هم می‌دید و ویرایش می‌کرد؛ همان حفظ می‌شود.
        Role::query()->whereHas('permissions', fn ($query) => $query->where('name', 'appointments.view'))->get()
            ->each(fn (Role $role) => $role->givePermissionTo($past->all()));
        Role::query()->whereIn('name', self::MANAGER_ROLES)
            ->orWhereHas('permissions', fn ($query) => $query->where('name', 'roles.manage'))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo([...$past->all(), $finalized]));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->whereIn('name', [...self::PAST_PERMISSIONS, self::FINALIZED_PERMISSION])->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['doctors', 'staff'] as $resourceTable) {
            Schema::table($resourceTable, fn (Blueprint $table) => $table->dropColumn(['referral_commission_type', 'referral_commission_value']));
        }
        Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn(['referrer_type', 'referrer_id', 'referrer_name', 'referral_commission_snapshot']));
    }
};
