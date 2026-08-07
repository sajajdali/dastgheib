<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (Schema::hasTable('central_admins')) {
            $this->call(CentralAdminSeeder::class);
        }

        if (Schema::hasTable('central_billing_plans')) {
            $this->call(CentralBillingSeeder::class);
        }

        if (Schema::hasTable('users')) {
            $this->call(SuperAdminSeeder::class);
        }
    }
}
