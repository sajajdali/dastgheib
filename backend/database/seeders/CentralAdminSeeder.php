<?php

namespace Database\Seeders;

use App\Models\CentralAdmin;
use Illuminate\Database\Seeder;

class CentralAdminSeeder extends Seeder
{
    public function run(): void
    {
        CentralAdmin::updateOrCreate(
            ['email' => 'admin@central.local'],
            [
                'name' => 'مدیر مرکزی',
                'password' => '12345678',
            ]
        );
    }
}
