<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\AttendanceMonthController;
use App\Http\Controllers\AppointmentController;
use App\Models\Appointment;
use App\Models\AppSetting;
use App\Models\AttendanceMonth;
use App\Models\Doctor;
use App\Models\Inventory;
use App\Models\InventorySection;
use App\Models\Patient;
use App\Models\ResourceEarningLine;
use App\Models\Staff;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CompleteDemoDataSeeder extends Seeder
{
    private array $months = ['1405-05', '1405-06'];

    public function run(): void
    {
        $admin = $this->users();
        [$doctorA, $doctorB, $staffA, $staffB] = $this->resources();
        $inventory = $this->inventory($doctorA, $doctorB, $staffA, $staffB);
        $patients = $this->patients($admin);

        $this->appointments($patients, $inventory, $doctorA, $doctorB, $staffA, $staffB);
        $this->attendance($doctorA, $doctorB, $staffA, $staffB);
        $this->syncPayrollLines();
    }

    private function users(): User
    {
        $permissions = collect(config('roles.sections', []))
            ->flatMap(fn (array $section) => $section['permissions'] ?? [])
            ->pluck('name')
            ->filter()
            ->values();

        $permissions->each(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
        Permission::firstOrCreate(['name' => 'payroll.view', 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'مدیر سیستم', 'guard_name' => 'web']);
        $role->syncPermissions(Permission::query()->pluck('name')->all());

        $admin = User::query()->updateOrCreate(
            ['mobile' => '09120000000'],
            ['name' => 'مدیر دمو', 'password' => Hash::make('12345678')]
        );
        $admin->assignRole($role);

        foreach ([
            ['name' => 'اپراتور پذیرش دمو', 'mobile' => '09120000001'],
            ['name' => 'کارشناس فروش دمو', 'mobile' => '09120000002'],
            ['name' => 'حسابدار دمو', 'mobile' => '09120000003'],
        ] as $user) {
            User::query()->updateOrCreate(
                ['mobile' => $user['mobile']],
                ['name' => $user['name'], 'password' => Hash::make('12345678')]
            )->givePermissionTo(['appointments.view', 'patients.view', 'payroll.view']);
        }

        return $admin;
    }

    private function resources(): array
    {
        $doctorA = Doctor::query()->updateOrCreate(
            ['name' => 'دکتر نازنین رضایی دمو'],
            [
                'bonus' => 14,
                'commission_customer_scope' => 'both',
                'commission_after_materials' => true,
                'sales_bonus_enabled' => true,
                'sales_bonus_tiers' => [['sales_from' => 20000000, 'salary_addition' => 2500000]],
                'salary' => 36000000,
                'overtime_hourly_rate' => 950000,
                'shortage_hourly_deduction' => 650000,
                'absence_deduction' => 2600000,
                'allowed_shortage_hours' => 1,
            ]
        );

        $doctorB = Doctor::query()->updateOrCreate(
            ['name' => 'دکتر آرمان کاویانی دمو'],
            [
                'bonus' => 11,
                'commission_customer_scope' => 'new',
                'commission_after_materials' => false,
                'sales_bonus_enabled' => true,
                'sales_bonus_tiers' => [['sales_from' => 16000000, 'salary_addition' => 1800000]],
                'salary' => 31000000,
                'overtime_hourly_rate' => 820000,
                'shortage_hourly_deduction' => 520000,
                'absence_deduction' => 2100000,
                'allowed_shortage_hours' => 0.5,
            ]
        );

        $staffA = Staff::query()->updateOrCreate(
            ['name' => 'سارا احمدی دمو'],
            [
                'bonus' => 5,
                'commission_customer_scope' => 'both',
                'commission_after_materials' => false,
                'sales_bonus_enabled' => true,
                'sales_bonus_tiers' => [['sales_from' => 12000000, 'salary_addition' => 1100000]],
                'salary' => 18000000,
                'overtime_hourly_rate' => 380000,
                'shortage_hourly_deduction' => 240000,
                'absence_deduction' => 950000,
                'allowed_shortage_hours' => 1,
            ]
        );

        $staffB = Staff::query()->updateOrCreate(
            ['name' => 'مریم صالحی دمو'],
            [
                'bonus' => 4,
                'commission_customer_scope' => 'existing',
                'commission_after_materials' => false,
                'sales_bonus_enabled' => false,
                'sales_bonus_tiers' => [],
                'salary' => 16500000,
                'overtime_hourly_rate' => 340000,
                'shortage_hourly_deduction' => 220000,
                'absence_deduction' => 900000,
                'allowed_shortage_hours' => 1,
            ]
        );

        return [$doctorA, $doctorB, $staffA, $staffB];
    }

    private function inventory(Doctor $doctorA, Doctor $doctorB, Staff $staffA, Staff $staffB): array
    {
        AppSetting::updateOrCreate(
            ['key' => 'service_tags'],
            ['value' => json_encode(['بوتاکس پیشانی', 'بوتاکس دور چشم', 'ژل لب', 'فرم‌دهی لب', 'لیزر صورت', 'لیزر فول فیس'], JSON_UNESCAPED_UNICODE)]
        );

        $beauty = InventorySection::query()->updateOrCreate(
            ['name' => 'زیبایی دمو'],
            ['parent_id' => null, 'level' => 1, 'sort_order' => 1]
        );
        $injection = InventorySection::query()->updateOrCreate(
            ['name' => 'تزریقات دمو'],
            ['parent_id' => $beauty->id, 'level' => 2, 'sort_order' => 1]
        );
        $laser = InventorySection::query()->updateOrCreate(
            ['name' => 'لیزر دمو'],
            ['parent_id' => $beauty->id, 'level' => 2, 'sort_order' => 2]
        );

        $items = [
            'botox' => Inventory::query()->updateOrCreate(
                ['name' => 'بوتاکس مصپورت دمو'],
                ['section_id' => $injection->id, 'service_tags' => ['بوتاکس پیشانی', 'بوتاکس دور چشم'], 'amount' => 3600000, 'price' => 950000, 'stock' => 24, 'min_stock' => 5, 'active' => true, 'default_commission_type' => 'percent', 'default_commission_value' => 12]
            ),
            'gel' => Inventory::query()->updateOrCreate(
                ['name' => 'ژل لب دمو'],
                ['section_id' => $injection->id, 'service_tags' => ['ژل لب', 'فرم‌دهی لب'], 'amount' => 5200000, 'price' => 1800000, 'stock' => 14, 'min_stock' => 3, 'active' => true, 'default_commission_type' => 'fixed', 'default_commission_value' => 350000]
            ),
            'laserFace' => Inventory::query()->updateOrCreate(
                ['name' => 'لیزر صورت دمو'],
                ['section_id' => $laser->id, 'service_tags' => ['لیزر صورت', 'لیزر فول فیس'], 'amount' => 1900000, 'price' => 320000, 'stock' => 60, 'min_stock' => 10, 'active' => true, 'default_commission_type' => 'percent', 'default_commission_value' => 8]
            ),
        ];

        $items['botox']->commissions()->updateOrCreate(['recipient_type' => 'doctor', 'recipient_id' => $doctorA->id], ['recipient_name' => $doctorA->name, 'commission_type' => 'percent', 'commission_value' => 22]);
        $items['gel']->commissions()->updateOrCreate(['recipient_type' => 'doctor', 'recipient_id' => $doctorB->id], ['recipient_name' => $doctorB->name, 'commission_type' => 'fixed', 'commission_value' => 500000]);
        $items['laserFace']->commissions()->updateOrCreate(['recipient_type' => 'staff', 'recipient_id' => $staffA->id], ['recipient_name' => $staffA->name, 'commission_type' => 'percent', 'commission_value' => 7]);
        $items['gel']->commissions()->updateOrCreate(['recipient_type' => 'staff', 'recipient_id' => $staffB->id], ['recipient_name' => $staffB->name, 'commission_type' => 'percent', 'commission_value' => 5]);

        return $items;
    }

    private function patients(User $admin): array
    {
        $rows = [
            ['first_name' => 'آوا', 'last_name' => 'کریمی', 'phone' => '09121110001', 'file_number' => 'DEMO-1001', 'gender' => 'زن', 'financial_status' => 'عالی', 'customer_level' => 'gold', 'city' => 'تهران'],
            ['first_name' => 'نیلوفر', 'last_name' => 'محمدی', 'phone' => '09121110002', 'file_number' => 'DEMO-1002', 'gender' => 'زن', 'financial_status' => 'خوب', 'customer_level' => 'silver', 'city' => 'تهران'],
            ['first_name' => 'مهسا', 'last_name' => 'احمدی', 'phone' => '09121110003', 'file_number' => 'DEMO-1003', 'gender' => 'زن', 'financial_status' => 'متوسط', 'customer_level' => 'bronze', 'city' => 'کرج'],
            ['first_name' => 'سامان', 'last_name' => 'راد', 'phone' => '09121110004', 'file_number' => 'DEMO-1004', 'gender' => 'مرد', 'financial_status' => 'خوب', 'customer_level' => 'gold', 'city' => 'تهران'],
            ['first_name' => 'ترانه', 'last_name' => 'ملکی', 'phone' => '09121110005', 'file_number' => 'DEMO-1005', 'gender' => 'زن', 'financial_status' => 'ضعیف', 'customer_level' => 'bronze', 'city' => 'قم'],
            ['first_name' => 'رها', 'last_name' => 'نوری', 'phone' => '09121110006', 'file_number' => 'DEMO-1006', 'gender' => 'زن', 'financial_status' => 'عالی', 'customer_level' => 'silver', 'city' => 'تهران'],
        ];

        return collect($rows)->map(function (array $row, int $index) use ($admin) {
            $patient = Patient::query()->updateOrCreate(
                ['file_number' => $row['file_number']],
                [
                    ...$row,
                    'birth_date' => '137'.($index + 1).'/0'.(($index % 8) + 1).'/15',
                    'national_id' => '00100'.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                    'patient_history' => 'پرونده تستی کامل برای نمایش نسخه دمو',
                    'medical_history' => $index % 2 ? 'حساسیت دارویی ندارد' : 'سابقه تزریق زیبایی',
                ]
            );

            WalletTransaction::query()->updateOrCreate(
                ['patient_id' => $patient->id, 'source_type' => 'demo_seed', 'source_key' => 'initial-'.$patient->file_number],
                ['type' => 'deposit', 'amount' => 500000 * ($index + 1), 'description' => 'شارژ اولیه دمو', 'created_by' => $admin->id, 'metadata' => ['demo' => true]]
            );

            return $patient;
        })->all();
    }

    private function appointments(array $patients, array $inventory, Doctor $doctorA, Doctor $doctorB, Staff $staffA, Staff $staffB): void
    {
        foreach ($this->months as $monthIndex => $month) {
            foreach (range(1, 12) as $day) {
                $patient = $patients[($day + $monthIndex) % count($patients)];
                $primary = $day % 3 === 0 ? $inventory['gel'] : $inventory['botox'];
                $secondary = $inventory['laserFace'];
                $doctor = $day % 3 === 0 ? $doctorB : $doctorA;
                $consultant = $day % 2 === 0 ? $staffB : $staffA;
                $originalAmount = (int) $primary->price + (int) $secondary->price;
                $discount = $day % 4 === 0 ? 250000 : 0;
                $amount = $originalAmount - $discount;
                $paid = $day % 5 === 0 ? $amount - 700000 : $amount;

                Appointment::query()->updateOrCreate(
                    ['month' => $month, 'day_num' => $day, 'time' => sprintf('%02d:30', 9 + ($day % 8)), 'file_number' => $patient->file_number],
                    [
                        'lastname' => $patient->last_name,
                        'gender' => $patient->gender,
                        'phone' => $patient->phone,
                        'status' => $day % 5 === 0 ? 'در انتظار تسویه' : 'انجام شد',
                        'done' => 'done',
                        'doctor' => $doctor->name,
                        'consultant' => $consultant->name,
                        'source' => 'دمو کامل',
                        'new_customer' => $day % 2 === 1,
                        'services' => [
                            ['name' => $primary->name, 'cc' => 1, 'discount' => $discount, 'doctor' => $doctor->name, 'consultant' => $consultant->name],
                            ['name' => $secondary->name, 'cc' => 1, 'discount' => 0, 'doctor' => $doctorA->name, 'consultant' => $staffA->name],
                        ],
                        'original_amount' => $originalAmount,
                        'discount' => $discount,
                        'amount' => $paid,
                        'debt' => max(0, $amount - $paid),
                        'payment_method' => $day % 6 === 0 ? 'چک' : ($day % 2 === 0 ? 'کارتخوان' : 'نقدی'),
                        'payment_account' => $day % 2 === 0 ? 'حساب ملت دمو' : 'صندوق نقدی دمو',
                        'payment_details' => [
                            'cash' => $day % 2 ? $paid : 0,
                            'card' => $day % 2 ? 0 : $paid,
                            'check' => $day % 6 === 0 ? ['amount' => 1200000, 'number' => 'CHK-'.$month.'-'.$day, 'dueDate' => '2026-08-'.str_pad((string) min(28, $day + 10), 2, '0', STR_PAD_LEFT)] : null,
                        ],
                    ]
                );
            }
        }
    }

    private function attendance(Doctor $doctorA, Doctor $doctorB, Staff $staffA, Staff $staffB): void
    {
        foreach ($this->months as $month) {
            $this->attendanceMonth($doctorA, 'doctor', $month, 8, [['day' => 1, 'in' => '09:00', 'out' => '18:30', 'employerApproved' => true], ['day' => 2, 'in' => '09:30', 'out' => '16:30'], ['day' => 3, 'absent' => true]]);
            $this->attendanceMonth($doctorB, 'doctor', $month, 8, [['day' => 1, 'in' => '10:00', 'out' => '19:00', 'employerApproved' => true], ['day' => 2, 'in' => '10:15', 'out' => '16:00'], ['day' => 3, 'absent' => true, 'leaveApproved' => true, 'leaveRequestTitle' => 'مرخصی تایید شده']]);
            $this->attendanceMonth($staffA, 'staff', $month, 8, [['day' => 1, 'in' => '08:30', 'out' => '18:00', 'employerApproved' => true], ['day' => 2, 'in' => '09:15', 'out' => '16:45'], ['day' => 3, 'absent' => true]]);
            $this->attendanceMonth($staffB, 'staff', $month, 8, [['day' => 1, 'in' => '08:45', 'out' => '17:00'], ['day' => 2, 'in' => '09:00', 'out' => '18:30', 'employerApproved' => true], ['day' => 3, 'in' => '10:00', 'out' => '15:30']]);
        }
    }

    private function attendanceMonth(Doctor|Staff $resource, string $type, string $month, float $dailyHours, array $days): void
    {
        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        $normalized = collect($days)->map(function (array $day) use ($resource, $dailyHours) {
            if ($day['absent'] ?? false) {
                return [...$day, 'in' => '', 'out' => '', 'workedHours' => 0, 'diff' => 0, 'amount' => ($day['leaveApproved'] ?? false) ? 0 : -((float) $resource->absence_deduction)];
            }

            [$inH, $inM] = array_map('intval', explode(':', $day['in']));
            [$outH, $outM] = array_map('intval', explode(':', $day['out']));
            $worked = max(0, (($outH * 60 + $outM) - ($inH * 60 + $inM)) / 60);
            $diff = round($worked - $dailyHours, 2);
            $amount = $diff > 0 && ($day['employerApproved'] ?? false)
                ? round($diff * (float) $resource->overtime_hourly_rate)
                : ($diff < 0 ? -round(abs($diff) * (float) $resource->shortage_hourly_deduction) : 0);

            return [...$day, 'workedHours' => $worked, 'diff' => $diff, 'amount' => $amount, 'absent' => false];
        })->values()->all();

        AttendanceMonth::query()->updateOrCreate(
            ['resource_type' => $type, 'resource_id' => $resource->id, 'year' => $year, 'month' => $monthNumber],
            ['name' => $resource->name, 'daily_hours' => $dailyHours, 'days' => $normalized]
        );
    }

    private function syncPayrollLines(): void
    {
        $appointmentController = app(AppointmentController::class);
        $syncAppointment = new \ReflectionMethod($appointmentController, 'syncResourceEarningLines');
        Appointment::query()
            ->whereIn('month', $this->months)
            ->where('source', 'دمو کامل')
            ->get()
            ->each(fn (Appointment $appointment) => $syncAppointment->invoke($appointmentController, $appointment));

        $syncBonus = new \ReflectionMethod($appointmentController, 'syncMonthlySalesBonusLines');
        ResourceEarningLine::query()->whereNull('appointment_id')->whereIn('month', $this->months)->where('earning_type', 'sales_bonus')->delete();
        foreach ($this->months as $month) {
            $syncBonus->invoke($appointmentController, $month);
        }

        $attendanceController = app(AttendanceMonthController::class);
        $syncAttendance = new \ReflectionMethod($attendanceController, 'syncAttendanceEarningLines');
        AttendanceMonth::query()
            ->whereIn('year', [1405])
            ->whereIn('month', [5, 6])
            ->get()
            ->each(fn (AttendanceMonth $month) => $syncAttendance->invoke($attendanceController, $month));
    }
}
