<?php

namespace Database\Seeders;

use App\Models\CentralBillingPlan;
use App\Models\CentralUserPricing;
use Illuminate\Database\Seeder;

class CentralBillingSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => 'تستی یک هفته‌ای', 'duration_days' => 7, 'base_price' => 0, 'is_trial' => true, 'sort_order' => 1],
            ['name' => 'یک ماهه', 'duration_days' => 30, 'base_price' => 0, 'sort_order' => 2],
            ['name' => 'دو ماهه', 'duration_days' => 60, 'base_price' => 0, 'sort_order' => 3],
            ['name' => 'شش ماهه', 'duration_days' => 180, 'base_price' => 0, 'sort_order' => 4],
            ['name' => 'یک ساله', 'duration_days' => 365, 'base_price' => 0, 'sort_order' => 5],
        ];

        foreach ($plans as $plan) {
            CentralBillingPlan::updateOrCreate(
                ['name' => $plan['name']],
                [
                    'duration_days' => $plan['duration_days'],
                    'base_price' => $plan['base_price'],
                    'is_trial' => $plan['is_trial'] ?? false,
                    'is_active' => true,
                    'sort_order' => $plan['sort_order'],
                ]
            );
        }

        CentralUserPricing::query()->firstOrCreate([], [
            'included_users' => 1,
            'extra_user_price' => 0,
        ]);
    }
}
