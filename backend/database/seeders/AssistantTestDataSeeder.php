<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\AttendanceMonthController;
use App\Http\Controllers\AppointmentController;
use App\Models\Appointment;
use App\Models\AppSetting;
use App\Models\AttendanceMonth;
use App\Models\Doctor;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\InventorySection;
use App\Models\Patient;
use App\Models\ResourceEarningLine;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Reporting\DynamicReports\JalaliMonthWindow;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * دادهٔ تستی واقعی‌نما برای آزمودن دستیار هوش مصنوعی: دو ماه و نیم گذشته تا ده روز آینده.
 * شناسهٔ همهٔ رکوردهای ساخته‌شده در app_settings (کلید assistant_test_data) ذخیره می‌شود
 * تا با AssistantTestDataSeeder::cleanup() دقیقاً همان‌ها پاک شوند و به دادهٔ واقعی دست نخورد.
 */
class AssistantTestDataSeeder extends Seeder
{
    private const KEY = 'assistant_test_data';
    private array $ids = [];
    private JalaliMonthWindow $jalali;

    public function run(): void
    {
        self::cleanup();
        mt_srand(1405);
        $this->jalali = new JalaliMonthWindow;

        [$doctors, $staff] = $this->people();
        $services = $this->services();
        $patients = $this->patients();
        $this->appointments($patients, $services, $doctors, $staff);
        $this->expenses();
        $this->attendance([...$doctors, ...$staff]);
        $this->tickets();
        $this->syncPayroll();

        AppSetting::updateOrCreate(['key' => self::KEY], ['value' => json_encode($this->ids)]);
    }

    public static function cleanup(): array
    {
        $ids = json_decode((string) AppSetting::getByKey(self::KEY, '{}'), true) ?: [];
        $deleted = [];
        if (! empty($ids['appointments'])) {
            ResourceEarningLine::query()->whereIn('appointment_id', $ids['appointments'])->delete();
            $deleted['appointments'] = Appointment::query()->whereIn('id', $ids['appointments'])->delete();
        }
        if (! empty($ids['attendance'])) {
            ResourceEarningLine::query()->where('earning_type', 'attendance')->whereIn('resource_id', array_merge($ids['doctors'] ?? [], $ids['staff'] ?? []))->delete();
            $deleted['attendance'] = AttendanceMonth::query()->whereIn('id', $ids['attendance'])->delete();
        }
        foreach (['wallet' => WalletTransaction::class, 'patients' => Patient::class, 'expenses' => Expense::class, 'tickets' => Ticket::class,
            'services' => Inventory::class, 'doctors' => Doctor::class, 'staff' => Staff::class, 'users' => User::class] as $key => $model) {
            if (empty($ids[$key])) continue;
            $query = $model::query()->whereIn('id', $ids[$key]);
            $deleted[$key] = in_array(SoftDeletes::class, class_uses_recursive($model), true) ? $query->withTrashed()->forceDelete() : $query->delete();
        }
        AppSetting::query()->where('key', self::KEY)->delete();

        return $deleted;
    }

    private function track(string $key, int $id): void
    {
        $this->ids[$key][] = $id;
    }

    private function people(): array
    {
        $definitions = [
            ['doctor', 'دکتر الهام صدری', 'female', '09125550101', 'پزشک', 15, 42000000],
            ['doctor', 'دکتر بهراد نیک‌پور', 'male', '09125550102', 'پزشک', 12, 38000000],
            ['doctor', 'دکتر ستاره امینی', 'female', '09125550103', 'پزشک', 10, 33000000],
            ['staff', 'زهره کاظمی', 'female', '09125550201', 'مشاور', 5, 19000000],
            ['staff', 'پریا رحمانی', 'female', '09125550202', 'پذیرش', 3, 16000000],
            ['staff', 'مهدی سلیمانی', 'male', '09125550203', 'پرسنل مجموعه', 4, 17500000],
        ];
        $doctors = [];
        $staff = [];
        foreach ($definitions as [$type, $name, $gender, $mobile, $roleName, $bonus, $salary]) {
            $user = User::query()->create([
                'name' => $name, 'nickname' => $name, 'gender' => $gender, 'mobile' => $mobile,
                'email' => 'test-'.Str::uuid().'@assistant-test.local', 'password' => Hash::make(Str::random(24)),
            ]);
            $this->track('users', $user->id);
            if ($role = Role::query()->where('name', $roleName)->first()) $user->assignRole($role);
            $model = $type === 'doctor' ? Doctor::class : Staff::class;
            $resource = $model::query()->create([
                'user_id' => $user->id, 'name' => $name, 'bonus' => $bonus, 'commission_customer_scope' => 'both',
                'commission_after_materials' => false, 'sales_bonus_enabled' => false, 'sales_bonus_tiers' => [],
                'salary' => $salary, 'overtime_hourly_rate' => (int) round($salary / 180), 'shortage_hourly_deduction' => (int) round($salary / 260),
                'absence_deduction' => (int) round($salary / 26), 'allowed_shortage_hours' => 1,
            ]);
            $this->track($type === 'doctor' ? 'doctors' : 'staff', $resource->id);
            $type === 'doctor' ? $doctors[] = $resource : $staff[] = $resource;
        }

        return [$doctors, $staff];
    }

    private function services(): array
    {
        $section = fn (string $name) => InventorySection::query()->where('name', $name)->value('id') ?? InventorySection::query()->value('id');
        $rows = [
            ['بوتاکس پیشانی (تست)', 4500000, 1800000, 'بوتاکس', 'پوست و زیبایی', ['پیشانی']],
            ['فیلر لب ۱ سی‌سی (تست)', 6500000, 2600000, 'ژل', 'تزریقات', ['لب']],
            ['مزوتراپی صورت (تست)', 2800000, 900000, 'مزوتراپی', 'پوست و زیبایی', ['صورت']],
            ['لیزر موهای زائد فول بادی (تست)', 3800000, 400000, 'لیزر بانوان', 'لیزر مو', ['فول بادی']],
            ['لیزر صورت آقایان (تست)', 1600000, 200000, 'لیزر آقایان', 'لیزر مو', ['صورت']],
            ['هیدرافیشیال (تست)', 1500000, 350000, 'فیشیال', 'اسپا', ['صورت']],
            ['پلاسماتراپی مو (تست)', 3200000, 700000, 'پوست و زیبایی', 'پوست و زیبایی', ['مو']],
            ['لیفت با نخ (تست)', 12000000, 4500000, 'نخ', 'تزریقات', ['گونه', 'فک']],
            ['ویزیت پوست (تست)', 600000, 0, 'پوست و زیبایی', 'پوست و زیبایی', []],
        ];

        return array_map(function (array $row) use ($section) {
            [$name, $sale, $cost, $sectionName, $subsection, $tags] = $row;
            $item = Inventory::query()->create([
                'section_id' => $section($sectionName), 'name' => $name, 'service_tags' => $tags, 'amount' => $sale, 'price' => $cost,
                'stock' => mt_rand(8, 40), 'min_stock' => 5, 'active' => true, 'default_commission_type' => 'percent', 'default_commission_value' => 10,
            ]);
            $this->track('services', $item->id);

            return ['id' => $item->id, 'name' => $name, 'sale' => $sale, 'cost' => $cost, 'section' => $sectionName, 'subsection' => $subsection, 'tags' => $tags];
        }, $rows);
    }

    private function patients(): array
    {
        $female = ['سارا', 'مریم', 'نگار', 'الهام', 'زهرا', 'فاطمه', 'نازنین', 'مهسا', 'هانیه', 'سمیرا', 'لیلا', 'آیدا', 'شیوا', 'پریسا', 'رویا', 'ترانه', 'یاسمن', 'نیلوفر', 'بهار', 'کیانا'];
        $male = ['علی', 'رضا', 'محمد', 'امیر', 'حسین', 'سعید', 'مهدی', 'پویا', 'آرش', 'کامران'];
        $last = ['احمدی', 'محمدی', 'حسینی', 'کریمی', 'رضایی', 'موسوی', 'جعفری', 'صادقی', 'رحیمی', 'نوری', 'کاظمی', 'اکبری', 'قاسمی', 'یوسفی', 'مرادی', 'شریفی', 'نیک‌محمدی', 'طاهری', 'فرهادی', 'عباسی'];
        $cities = ['تهران', 'تهران', 'تهران', 'کرج', 'شیراز', 'اصفهان', 'مشهد', 'قم'];
        $patients = [];
        foreach (range(1, 60) as $index) {
            $isMale = $index % 4 === 0;
            $first = $isMale ? $male[$index % count($male)] : $female[$index % count($female)];
            $patient = Patient::query()->create([
                'first_name' => $first, 'last_name' => $last[($index * 7) % count($last)],
                'phone' => '0912555'.str_pad((string) (3000 + $index), 4, '0', STR_PAD_LEFT),
                'file_number' => 'T-'.(2000 + $index), 'gender' => $isMale ? 'مرد' : 'زن',
                'birth_date' => sprintf('13%02d/%02d/%02d', mt_rand(55, 84), mt_rand(1, 12), mt_rand(1, 28)),
                'national_id' => $index % 3 === 0 ? null : '00'.str_pad((string) (12345678 + $index * 97), 8, '0', STR_PAD_LEFT),
                'city' => $cities[$index % count($cities)],
                'financial_status' => ['عالی', 'خوب', 'متوسط', 'ضعیف'][$index % 4],
                'patient_history' => 'مراجعه جهت خدمات پوست و زیبایی (داده تستی)',
                'medical_history' => $index % 5 === 0 ? 'حساسیت به لیدوکائین' : 'سابقه خاصی ندارد',
            ]);
            $this->track('patients', $patient->id);
            if ($index % 6 === 0) {
                $wallet = WalletTransaction::query()->create(['patient_id' => $patient->id, 'type' => 'deposit', 'amount' => 500000 * ($index % 5 + 1),
                    'description' => 'اعتبار هدیه (داده تستی)', 'source_type' => 'customer_club', 'source_key' => 'test-'.$patient->file_number]);
                $this->track('wallet', $wallet->id);
            }
            $patients[] = $patient;
        }

        return $patients;
    }

    private function appointments(array $patients, array $services, array $doctors, array $staff): void
    {
        $sources = ['اینستاگرام', 'اینستاگرام', 'معرفی دوستان', 'گوگل', 'مراجعه حضوری', 'دیوار', 'معرفی پزشک'];
        $seen = [];
        $today = now('Asia/Tehran')->startOfDay();
        for ($date = $today->copy()->subDays(72); $date->lte($today->copy()->addDays(9)); $date->addDay()) {
            if ($date->dayOfWeek === 5) continue; // جمعه
            $jalali = $this->jalali->date($date);
            $future = $date->gt($today);
            $count = mt_rand(4, 9);
            foreach (range(0, $count - 1) as $slot) {
                $patient = $patients[mt_rand(0, count($patients) - 1)];
                $doctor = $doctors[$slot % count($doctors)];
                $consultant = $staff[mt_rand(0, count($staff) - 1)];
                $picked = [$services[mt_rand(0, count($services) - 1)]];
                if (mt_rand(1, 4) === 1) $picked[] = $services[mt_rand(0, count($services) - 1)];
                $items = array_map(fn ($service) => [
                    'name' => $service['name'], 'price' => $service['sale'], 'quantity' => 1, 'cc' => 1, 'discount' => 0,
                    'material_cost' => $service['cost'], 'section' => $service['section'], 'subsection' => $service['subsection'],
                    'tags' => $service['tags'], 'doctor' => $doctor->name, 'consultant' => $consultant->name,
                ], $picked);
                $original = array_sum(array_column($items, 'price'));
                $discount = mt_rand(1, 6) === 1 ? (int) round($original * 0.1 / 10000) * 10000 : 0;
                $amount = $original - $discount;

                $roll = mt_rand(1, 100);
                $status = $future ? 'وقت داده شد' : match (true) {
                    $roll <= 70 => 'آمد', $roll <= 80 => 'کنسل شد', $roll <= 88 => 'پاسخ نداد',
                    $roll <= 93 => 'پیگیری', $roll <= 98 => 'وقت داده شد', default => 'انتقال داده شده',
                };
                $done = $status === 'آمد' ? 'انجام شد' : null;
                $debt = 0;
                $method = null;
                $details = null;
                if ($done) {
                    $pay = mt_rand(1, 100);
                    $debt = $pay > 78 ? (int) round($amount * mt_rand(20, 60) / 100 / 10000) * 10000 : 0;
                    $paid = $amount - $debt;
                    $method = $pay % 9 === 0 ? 'چک' : ['نقدی', 'کارتخوان', 'کارتخوان', 'کارت به کارت'][$pay % 4];
                    $details = $method === 'چک'
                        ? ['cash' => 0, 'card' => 0, 'check' => ['amount' => $paid, 'number' => 'CHK-'.$jalali.'-'.$slot, 'dueDate' => $date->copy()->addDays(30)->toDateString()]]
                        : ['cash' => $method === 'نقدی' ? $paid : 0, 'card' => $method === 'نقدی' ? 0 : $paid];
                }
                $key = $patient->file_number;
                $appointment = Appointment::query()->create([
                    'month' => substr($jalali, 0, 7), 'day_num' => (int) substr($jalali, 8, 2), 'sort_order' => $slot,
                    'lastname' => trim($patient->first_name.' '.$patient->last_name), 'gender' => $patient->gender,
                    'phone' => $patient->phone, 'file_number' => $patient->file_number,
                    'time' => sprintf('%02d:%02d', 10 + intdiv($slot, 1) % 8, ($slot % 2) * 30),
                    'status' => $status, 'done' => $done, 'doctor' => $doctor->name, 'consultant' => $consultant->name,
                    'source' => $sources[mt_rand(0, count($sources) - 1)], 'new_customer' => ! isset($seen[$key]),
                    'services' => $items, 'original_amount' => $original, 'discount' => $discount,
                    'amount' => $done ? $amount : 0, 'debt' => $debt, 'payment_method' => $method,
                    'payment_account' => $method ? ($method === 'نقدی' ? 'صندوق پذیرش' : ($method === 'کارت به کارت' ? 'حساب بانک سامان' : 'کارتخوان بانک ملت')) : null,
                    'payment_details' => $details,
                    'description' => $status === 'کنسل شد' ? 'لغو به درخواست مراجع' : null,
                ]);
                $seen[$key] = true;
                $this->track('appointments', $appointment->id);
            }
        }
    }

    private function expenses(): void
    {
        $months = [];
        $today = now('Asia/Tehran');
        foreach ([2, 1, 0] as $back) $months[] = substr($this->jalali->date($today->copy()->subMonths($back)), 0, 7);
        foreach (array_unique($months) as $month) {
            foreach ([
                ['اجاره', 'اجاره ماهانهٔ مطب', 120000000, 'paid'],
                ['مواد مصرفی', 'خرید مواد تزریقی و مصرفی', mt_rand(38, 62) * 1000000, 'paid'],
                ['تبلیغات', 'تبلیغات اینستاگرام', mt_rand(18, 30) * 1000000, 'paid'],
                ['قبوض', 'آب، برق، گاز و اینترنت', mt_rand(5, 9) * 1000000, 'paid'],
                ['تعمیرات', 'سرویس دستگاه لیزر', mt_rand(4, 15) * 1000000, $month === end($months) ? 'unpaid' : 'paid'],
            ] as $i => [$category, $title, $amount, $status]) {
                $expense = Expense::query()->create([
                    'occurred_on' => $month.'-'.str_pad((string) (3 + $i * 4), 2, '0', STR_PAD_LEFT), 'category' => $category,
                    'title' => $title.' (داده تستی)', 'amount' => $amount, 'type' => 'expense', 'payment_status' => $status,
                    'party' => ['مالک', 'تأمین‌کنندهٔ دارو', 'آژانس تبلیغاتی', 'ادارات', 'شرکت سرویس'][$i], 'payment_method' => 'کارت به کارت',
                ]);
                $this->track('expenses', $expense->id);
            }
        }
    }

    private function attendance(array $resources): void
    {
        $today = now('Asia/Tehran')->startOfDay();
        $byMonth = [];
        for ($date = $today->copy()->subDays(72); $date->lte($today); $date->addDay()) {
            if ($date->dayOfWeek === 5) continue;
            $jalali = $this->jalali->date($date);
            $byMonth[substr($jalali, 0, 7)][] = (int) substr($jalali, 8, 2);
        }
        foreach ($resources as $resource) {
            $type = $resource instanceof Doctor ? 'doctor' : 'staff';
            foreach ($byMonth as $month => $days) {
                $rows = array_map(function (int $day) use ($resource) {
                    $roll = mt_rand(1, 100);
                    if ($roll <= 5) return ['day' => $day, 'absent' => true, 'in' => '', 'out' => '', 'workedHours' => 0, 'diff' => 0, 'amount' => -((float) $resource->absence_deduction)];
                    if ($roll <= 8) return ['day' => $day, 'absent' => true, 'leaveApproved' => true, 'leaveRequestTitle' => 'مرخصی تأییدشده', 'in' => '', 'out' => '', 'workedHours' => 0, 'diff' => 0, 'amount' => 0];
                    $inMinutes = 9 * 60 + mt_rand(-20, 45);
                    $outMinutes = 17 * 60 + mt_rand(-40, 120);
                    $worked = round(($outMinutes - $inMinutes) / 60, 2);
                    $diff = round($worked - 8, 2);
                    $approved = $diff > 0 && mt_rand(0, 1) === 1;
                    $amount = $diff > 0 && $approved ? round($diff * (float) $resource->overtime_hourly_rate) : ($diff < 0 ? -round(abs($diff) * (float) $resource->shortage_hourly_deduction) : 0);

                    return ['day' => $day, 'in' => sprintf('%02d:%02d', intdiv($inMinutes, 60), $inMinutes % 60), 'out' => sprintf('%02d:%02d', intdiv($outMinutes, 60), $outMinutes % 60),
                        'workedHours' => $worked, 'diff' => $diff, 'amount' => $amount, 'absent' => false, 'employerApproved' => $approved];
                }, $days);
                [$year, $monthNumber] = array_map('intval', explode('-', $month));
                $attendance = AttendanceMonth::query()->create(['resource_type' => $type, 'resource_id' => $resource->id, 'year' => $year, 'month' => $monthNumber,
                    'name' => $resource->name, 'daily_hours' => 8, 'days' => $rows]);
                $this->track('attendance', $attendance->id);
            }
        }
    }

    private function tickets(): void
    {
        foreach ([
            ['تماس با مراجعین پاسخ‌نداده', 'active', 'بالا'], ['شارژ مواد تزریقی انبار', 'active', 'متوسط'],
            ['سرویس دورهٔ دستگاه لیزر', 'done', 'بالا'], ['ارسال پیامک تبریک تولد', 'done', 'کم'], ['پیگیری چک‌های برگشتی', 'expired', 'بالا'],
        ] as $i => [$subject, $status, $priority]) {
            $ticket = Ticket::query()->create(['subject' => $subject.' (تست)', 'description' => 'داده تستی برای آزمودن دستیار', 'date' => $this->jalali->date(now('Asia/Tehran')->subDays($i * 5)),
                'owner' => ['زهره کاظمی', 'پریا رحمانی', 'مهدی سلیمانی'][$i % 3], 'priority' => $priority, 'status' => $status]);
            $this->track('tickets', $ticket->id);
        }
    }

    /** خطوط کارکرد و پورسانت با همان منطق خود برنامه. */
    private function syncPayroll(): void
    {
        try {
            $appointments = app(AppointmentController::class);
            $syncAppointment = new \ReflectionMethod($appointments, 'syncResourceEarningLines');
            Appointment::query()->whereIn('id', $this->ids['appointments'] ?? [])->where('done', 'انجام شد')->get()
                ->each(fn (Appointment $appointment) => $syncAppointment->invoke($appointments, $appointment));
            $attendance = app(AttendanceMonthController::class);
            $syncAttendance = new \ReflectionMethod($attendance, 'syncAttendanceEarningLines');
            AttendanceMonth::query()->whereIn('id', $this->ids['attendance'] ?? [])->get()
                ->each(fn (AttendanceMonth $month) => $syncAttendance->invoke($attendance, $month));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
