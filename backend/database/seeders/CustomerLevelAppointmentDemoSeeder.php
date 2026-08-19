<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Database\Seeder;

class CustomerLevelAppointmentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $month = '1405-04';
        $day = 21;

        Appointment::query()
            ->where('file_number', 'like', 'DEMO-LEVEL-%')
            ->delete();

        $rows = [
            ['silver', 'آوا', 'کاظمی', 'زن', '09:00', 2500000, 'پاکسازی پوست', 'اینستاگرام', 'اولین مراجعه؛ مشاوره و پاکسازی پوست'],
            ['silver', 'سامیار', 'نیک‌روش', 'مرد', '09:30', 4250000, 'مزوتراپی مو', 'معرفی دوستان', 'اولین مراجعه؛ ارزیابی و مزوتراپی مو'],
            ['silver', 'هلیا', 'پارسا', 'زن', '10:00', 6750000, 'فیشیال تخصصی', 'وب‌سایت', 'اولین مراجعه؛ فیشیال و آبرسانی تخصصی'],
            ['blue', 'نیلوفر', 'رحیمی', 'زن', '10:30', 10000000, 'میکرونیدلینگ', 'اینستاگرام', 'مشتری آبی؛ جلسه میکرونیدلینگ صورت'],
            ['blue', 'آرین', 'فرهمند', 'مرد', '11:00', 12500000, 'لیزر جوان‌سازی', 'گوگل', 'مشتری آبی؛ پکیج جوان‌سازی پوست'],
            ['blue', 'ترانه', 'شریفی', 'زن', '11:30', 20000000, 'ژل لب', 'مراجعه حضوری', 'مشتری آبی؛ تزریق ژل با فرم طبیعی'],
            ['gold', 'رها', 'مهرآیین', 'زن', '12:00', 100000000, 'پکیج VIP جوان‌سازی', 'معرفی پزشک', 'مشتری طلایی؛ پکیج کامل VIP جوان‌سازی'],
            ['gold', 'کیان', 'دادخواه', 'مرد', '12:30', 125000000, 'کاشت مو VIP', 'معرفی دوستان', 'مشتری طلایی؛ خدمات ویژه کاشت و مراقبت مو'],
            ['gold', 'یسنا', 'بهرامی', 'زن', '13:00', 175000000, 'کانتورینگ VIP', 'اینستاگرام', 'مشتری طلایی؛ طراحی و کانتورینگ کامل صورت'],
            ['problematic', 'بردیا', 'رستگار', 'مرد', '13:30', 8500000, 'ترمیم خدمات', 'پیگیری کلینیک', 'نیازمند هماهنگی دقیق و تأیید نهایی پیش از انجام خدمات'],
        ];

        foreach ($rows as $index => [$level, $firstName, $lastName, $gender, $time, $amount, $service, $source, $description]) {
            $number = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $phone = '09009000'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $fileNumber = 'DEMO-LEVEL-'.$number;

            Patient::updateOrCreate(
                ['file_number' => $fileNumber],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => $phone,
                    'gender' => $gender,
                    'city' => 'تهران',
                    'area' => $index % 2 === 0 ? 'مرکز تهران' : 'شمال تهران',
                    'financial_status' => $level === 'gold' ? 'عالی' : ($level === 'blue' ? 'خوب' : 'متوسط'),
                    'customer_level' => $level === 'problematic' ? 'problematic' : null,
                    'patient_history' => 'پرونده دمو برای نمایش سطح‌بندی مشتریان در نوبت‌دهی',
                    'medical_history' => 'مورد خاصی ثبت نشده است',
                ],
            );

            Appointment::updateOrCreate(
                [
                    'month' => $month,
                    'file_number' => $fileNumber,
                ],
                [
                    'day_num' => $day,
                    'sort_order' => $index,
                    'lastname' => $firstName.' '.$lastName,
                    'gender' => $gender,
                    'phone' => $phone,
                    'time' => $time,
                    'status' => 'وقت داده شد',
                    'source' => $source,
                    'description' => $description,
                    'done' => 'انجام شد',
                    'amount' => (string) $amount,
                    'original_amount' => (string) $amount,
                    'debt' => '0',
                    'payment_method' => $index % 2 === 0 ? 'کارتخوان' : 'کارت به کارت',
                    'payment_account' => $index % 2 === 0 ? 'حساب اصلی کلینیک' : 'حساب آنلاین',
                    'new_customer' => $level === 'silver',
                    'appointment_sms' => 'ارسال شد',
                    'info_sms' => 'ارسال شد',
                    'services' => [[
                        'name' => 'سرنگ',
                        'section_id' => 82,
                        'cc' => (string) ($amount / 250000),
                        'doctor' => 'دکتر نادری',
                        'consultant' => 'خانم احمدی',
                        'addons' => [],
                    ]],
                    'service_types' => [$service],
                ],
            );
        }
    }
}
