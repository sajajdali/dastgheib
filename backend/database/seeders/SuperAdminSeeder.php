<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $user = User::query()
            ->where('mobile', '09121236686')
            ->orWhere('email', 'superadmin@clinic.local')
            ->first() ?? new User();

        $user->fill([
            'name' => 'مدیر کل',
            'mobile' => '09121236686',
            'email' => 'manager@clinic.local',
            'password' => Hash::make('09121236686'),
        ])->save();

        $user->syncRoles(['مدیر سیستم']);
        AppSetting::updateOrCreate(
            ['key' => 'project_owner_user_id'],
            ['value' => (string) $user->id],
        );
    }
}
