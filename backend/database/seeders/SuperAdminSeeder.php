<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $user = User::updateOrCreate(
            ['mobile' => '09122978167'],
            [
                'name' => 'مدیر کل',
                'email' => 'superadmin@clinic.local',
                'password' => Hash::make('1234'),
            ]
        );

        $user->syncRoles(['مدیر سیستم']);
    }
}
