<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class CustomerSegmentationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->firstWhere('id', 'clinic1');
        if (! $tenant) {
            $this->command?->warn('Tenant clinic1 was not found.');
            return;
        }

        tenancy()->initialize($tenant);

        $fixtures = [
            [
                'file_number' => 'REPORT-SEG-SILVER',
                'phone' => '09129001001',
                'first_name' => 'مشتری معمولی',
                'level' => 'silver',
                'amounts' => [5000000],
            ],
            [
                'file_number' => 'REPORT-SEG-BLUE',
                'phone' => '09129001002',
                'first_name' => 'مشتری خوب',
                'level' => 'blue',
                'amounts' => [6000000, 6000000],
            ],
            [
                'file_number' => 'REPORT-SEG-GOLD',
                'phone' => '09129001003',
                'first_name' => 'مشتری CIP',
                'level' => 'gold',
                'amounts' => [40000000, 40000000, 40000000],
            ],
            [
                'file_number' => 'REPORT-SEG-PROBLEMATIC',
                'phone' => '09129001004',
                'first_name' => 'مشتری مشکل‌ساز',
                'level' => 'problematic',
                'amounts' => [8000000],
            ],
        ];

        foreach ($fixtures as $fixtureIndex => $fixture) {
            Patient::query()->updateOrCreate(
                ['file_number' => $fixture['file_number']],
                [
                    'first_name' => $fixture['first_name'],
                    'last_name' => 'دمو گزارش',
                    'phone' => $fixture['phone'],
                    'gender' => $fixtureIndex % 2 === 0 ? 'خانم' : 'آقا',
                    'customer_level' => $fixture['level'],
                ]
            );

            foreach ($fixture['amounts'] as $visitIndex => $amount) {
                $day = 20 + ($fixtureIndex * 3) + $visitIndex;
                Appointment::query()->updateOrCreate(
                    [
                        'file_number' => $fixture['file_number'],
                        'month' => '1405-04',
                        'day_num' => $day,
                        'time' => sprintf('%02d:00', 9 + $visitIndex),
                    ],
                    [
                        'lastname' => $fixture['first_name'].' دمو گزارش',
                        'gender' => $fixtureIndex % 2 === 0 ? 'خانم' : 'آقا',
                        'phone' => $fixture['phone'],
                        'status' => 'مراجعه کرد',
                        'done' => 'انجام شد',
                        'amount' => (string) $amount,
                        'debt' => '0',
                        'source' => 'دمو دسته‌بندی گزارش',
                        'new_customer' => $visitIndex === 0,
                        'services' => [[
                            'name' => 'خدمت دمو دسته‌بندی',
                            'price' => $amount,
                            'quantity' => 1,
                        ]],
                    ]
                );
            }
        }

        $this->command?->info('Customer segmentation demo data was created for clinic1.');
    }
}
