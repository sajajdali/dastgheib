<?php

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (AppSetting::query()->where('key', 'project_owner_user_id')->exists()) {
            return;
        }

        // Tenant migrations run before tenant seeders. Ensure the protected role
        // exists before using Spatie's role scope on a freshly-created tenant.
        $systemManagerRole = Role::firstOrCreate([
            'name' => 'مدیر سیستم',
            'guard_name' => 'web',
        ]);

        $owner = User::role($systemManagerRole)->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if ($owner) {
            $owner->assignRole($systemManagerRole);
            AppSetting::create(['key' => 'project_owner_user_id', 'value' => (string) $owner->id]);
        }
    }

    public function down(): void
    {
        AppSetting::query()->where('key', 'project_owner_user_id')->delete();
    }
};
