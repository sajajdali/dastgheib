<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Inventory;
use App\Models\InventorySection;
use App\Models\Patient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoAppointmentsSeeder extends Seeder
{
    public function run(): void
    {
        $month = '1405-04';
        $day = 28;
        $today = '2026-07-19';

        $people = [
            ['سارا', 'احمدی', 'زن', 'تهرانپارس', 'تهران', 'طلایی'],
            ['نگار', 'محمدی', 'زن', 'پاسداران', 'تهران', 'آبی'],
            ['علی', 'رضایی', 'مرد', 'نارمک', 'تهران', 'نقره‌ای'],
            ['مریم', 'کریمی', 'زن', 'سعادت‌آباد', 'تهران', 'عالی'],
            ['زهرا', 'حسینی', 'زن', 'ونک', 'تهران', 'متوسط'],
            ['امیرحسین', 'موسوی', 'مرد', 'صادقیه', 'تهران', 'خوب'],
            ['الهام', 'جعفری', 'زن', 'جردن', 'تهران', 'عالی'],
            ['نیلوفر', 'کاظمی', 'زن', 'شهرک غرب', 'تهران', 'خوب'],
            ['آرمان', 'قاسمی', 'مرد', 'یوسف‌آباد', 'تهران', 'متوسط'],
            ['سمیه', 'مرادی', 'زن', 'اکباتان', 'تهران', 'خوب'],
            ['رضا', 'اکبری', 'مرد', 'پیروزی', 'تهران', 'متوسط'],
            ['مهسا', 'صادقی', 'زن', 'قیطریه', 'تهران', 'عالی'],
            ['حدیث', 'نوری', 'زن', 'میدان هروی', 'تهران', 'خوب'],
            ['محمد', 'عباسی', 'مرد', 'گیشا', 'تهران', 'متوسط'],
            ['ریحانه', 'رحیمی', 'زن', 'زعفرانیه', 'تهران', 'عالی'],
            ['پویا', 'بهرامی', 'مرد', 'تهران‌نو', 'تهران', 'خوب'],
            ['شبنم', 'یوسفی', 'زن', 'فرمانیه', 'تهران', 'متوسط'],
        ];

        $statuses = [
            'وقت داده شد', 'وقت داده شد', 'وقت داده شد', 'وقت داده شد',
            'آمد', 'آمد', 'آمد', 'آمد',
            'پاسخ نداد', 'پاسخ نداد', 'پاسخ نداد',
            'پیگیری', 'پیگیری', 'پیگیری', 'پیگیری',
            'کنسل شد', 'کنسل شد',
        ];

        $doneStates = [
            '', '', 'مشاوره', '',
            'انجام شد', 'انجام شد', 'ترمیم', 'انتقال',
            'انجام نشد', '', '',
            'مشاوره', 'انجام نشد', 'ترمیم', '',
            'انجام نشد', 'انجام نشد',
        ];

        DB::transaction(function () use ($month, $day, $today, $people, $statuses, $doneStates) {
            $sections = [
                'تزریقات' => ['بوتاکس دیسپورت', 'فیلر لب', 'فیلر خط خنده'],
                'پوست و جوانسازی' => ['میکرونیدلینگ', 'مزوتراپی صورت', 'هایفوتراپی', 'پکیج نقره‌ای جوانسازی', 'پکیج طلایی جوانسازی کامل'],
                'لیزر' => ['لیزر موهای زائد', 'لیزر جوانسازی'],
            ];

            $prices = [
                'بوتاکس دیسپورت' => 3200000,
                'فیلر لب' => 6800000,
                'فیلر خط خنده' => 7500000,
                'میکرونیدلینگ' => 2800000,
                'مزوتراپی صورت' => 3900000,
                'هایفوتراپی' => 8500000,
                'لیزر موهای زائد' => 1600000,
                'لیزر جوانسازی' => 4200000,
                'پکیج نقره‌ای جوانسازی' => 18500000,
                'پکیج طلایی جوانسازی کامل' => 110000000,
            ];

            $inventory = [];
            foreach ($sections as $sectionName => $serviceNames) {
                $section = InventorySection::updateOrCreate(
                    ['name' => $sectionName],
                    ['sort_order' => count($inventory)]
                );

                foreach ($serviceNames as $sort => $serviceName) {
                    $inventory[$serviceName] = Inventory::updateOrCreate(
                        ['section_id' => $section->id, 'name' => $serviceName],
                        [
                            'service_tags' => [$serviceName, $sectionName],
                            'amount' => $prices[$serviceName],
                            'price' => $prices[$serviceName],
                            'count' => 25 + $sort * 5,
                            'stock' => 25 + $sort * 5,
                            'min_stock' => 5,
                            'active' => true,
                            'sort_order' => $sort,
                            'default_commission_type' => 'percent',
                            'default_commission_value' => 10,
                        ]
                    );
                }
            }

            $serviceSets = [
                ['پکیج طلایی جوانسازی کامل'], ['فیلر لب'], ['پکیج نقره‌ای جوانسازی'], ['لیزر موهای زائد'],
                ['پکیج طلایی جوانسازی کامل'], ['مزوتراپی صورت'], ['پکیج نقره‌ای جوانسازی'], ['بوتاکس دیسپورت', 'فیلر لب'],
                ['پکیج طلایی جوانسازی کامل'], ['میکرونیدلینگ', 'مزوتراپی صورت'], ['پکیج نقره‌ای جوانسازی'], ['پکیج نقره‌ای جوانسازی'],
                ['پکیج نقره‌ای جوانسازی'], ['لیزر موهای زائد'], ['فیلر خط خنده'], ['مزوتراپی صورت'], ['لیزر جوانسازی'],
            ];

            $doctorNames = \App\Models\Doctor::query()->orderBy('id')->pluck('name')->values();
            if ($doctorNames->isEmpty()) {
                $doctorNames = collect(['دکتر رضایی', 'دکتر رمضانی']);
            }

            $fileNumbers = array_map('strval', range(4, 20));
            Appointment::query()
                ->where('month', $month)
                ->where('day_num', $day)
                ->whereIn('file_number', $fileNumbers)
                ->delete();

            foreach ($people as $index => [$firstName, $lastName, $gender, $area, $city, $financial]) {
                $fileNumber = (string) ($index + 4);
                $phone = '0912'.str_pad((string) (1000000 + $index + 4), 7, '0', STR_PAD_LEFT);
                $customerLevel = match ($fileNumber) {
                    '4', '8', '12' => 'gold',
                    '5', '7', '9', '11', '13', '17', '18', '19' => 'silver',
                    '6', '10', '14', '15', '16' => 'blue',
                    '20' => 'problematic',
                    default => null,
                };

                Patient::updateOrCreate(
                    ['file_number' => $fileNumber],
                    [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => $phone,
                        'gender' => $gender,
                        'birth_date' => (1984 + ($index % 15)).'-'.str_pad((string) (($index % 9) + 1), 2, '0', STR_PAD_LEFT).'-15',
                        'area' => $area,
                        'city' => $city,
                        'financial_status' => $financial,
                        'customer_level' => $customerLevel,
                        'patient_history' => $index % 3 === 0 ? 'مراجعه از طریق معرفی دوستان؛ پیگیر و منظم' : 'مراجعه جهت دریافت خدمات زیبایی و مراقبت دوره‌ای',
                        'medical_history' => $index % 5 === 0 ? 'حساسیت فصلی؛ بدون سابقه بیماری زمینه‌ای مهم' : 'فاقد بیماری زمینه‌ای و حساسیت دارویی اعلام‌شده',
                        'national_id' => '001'.str_pad((string) ($index + 1000000), 7, '0', STR_PAD_LEFT),
                        'father_name' => ['حسن', 'محمد', 'علی', 'رضا'][$index % 4],
                        'education' => ['کارشناسی', 'کارشناسی ارشد', 'دیپلم', 'دکتری'][$index % 4],
                        'second_phone' => '02144'.str_pad((string) ($index + 10000), 5, '0', STR_PAD_LEFT),
                        'address' => 'تهران، '.$area.'، خیابان نمونه، پلاک '.($index + 10),
                    ]
                );

                $selectedServices = $serviceSets[$index];
                $originalAmount = array_sum(array_map(fn ($name) => $prices[$name], $selectedServices));
                $discount = in_array($index, [3, 7, 11], true) ? 300000 : 0;
                $debt = match ($fileNumber) {
                    '11' => 1200000,
                    '18' => 2500000,
                    default => 0,
                };
                $paidAmount = max(0, $originalAmount - $discount - $debt);
                $doctor = $doctorNames[$index % $doctorNames->count()];
                $status = $statuses[$index];
                $done = $doneStates[$index];
                $time = sprintf('%02d:%02d', 9 + intdiv($index, 2), ($index % 2) * 30);
                $servicesPayload = array_map(function ($name) use ($inventory, $doctor, $index) {
                    return [
                        'name' => $name,
                        'section_id' => $inventory[$name]->section_id,
                        'cc' => 1,
                        'doctor' => $doctor,
                        'consultant' => ['خانم مرادی', 'خانم احمدی'][$index % 2],
                        'addons' => [],
                    ];
                }, $selectedServices);

                Appointment::create([
                    'month' => $month,
                    'day_num' => $day,
                    'sort_order' => $index + 1,
                    'lastname' => $firstName.' '.$lastName,
                    'gender' => $gender,
                    'phone' => $phone,
                    'file_number' => $fileNumber,
                    'time' => $time,
                    'status' => $status,
                    'arrived_at' => $status === 'آمد' ? $today.' '.$time.':00' : null,
                    'doctor' => $doctor,
                    'consultant' => ['خانم مرادی', 'خانم احمدی'][$index % 2],
                    'source' => ['اینستاگرام', 'معرفی دوستان', 'گوگل', 'مراجعه قبلی'][$index % 4],
                    'description' => $fileNumber === '20' ? 'مشتری دردسرساز؛ قبل از هر اقدام هماهنگی با مدیریت انجام شود.' : 'نوبت دمو با اطلاعات کامل؛ تماس و شرح خدمت تأیید شده است.',
                    'doctor_note' => $index % 4 === 1 ? 'بررسی سابقه حساسیت و ثبت رضایت‌نامه پیش از شروع خدمت.' : null,
                    'done' => $done,
                    'completed_at' => $done === 'انجام شد' ? $today.' '.date('H:i:s', strtotime($time.' +45 minutes')) : null,
                    'amount' => $paidAmount,
                    'debt' => $debt,
                    'payment_method' => ['کارتخوان', 'کارت به کارت', 'نقدی'][$index % 3],
                    'payment_account' => ['حساب اصلی', 'صندوق کلینیک'][$index % 2],
                    'discount' => $discount,
                    'original_amount' => $originalAmount,
                    'wallet_applied' => 0,
                    'new_customer' => $index % 4 === 0,
                    'appointment_sms' => $index % 3 === 0 ? 'ارسال شد' : 'در انتظار',
                    'info_sms' => $index % 2 === 0 ? 'ارسال شد' : 'در انتظار',
                    'completion_sms_statuses' => ['reminder' => $index % 3 === 0 ? 'sent' : 'pending'],
                    'services' => $servicesPayload,
                    'service_types' => array_values(array_unique(array_map(fn ($name) => $inventory[$name]->section_id, $selectedServices))),
                ]);
            }
        });

        $this->command?->info('17 demo patients and today appointments (files 4-20) were created.');
    }
}
