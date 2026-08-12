<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\AttendanceMonthController;
use App\Http\Controllers\AppointmentController;
use App\Models\Appointment;
use App\Models\AttendanceMonth;
use App\Models\Doctor;
use App\Models\Inventory;
use App\Models\InventorySection;
use App\Models\ResourceEarningLine;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PayrollDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->firstWhere('id', 'clinic1');
        if ($tenant) {
            tenancy()->initialize($tenant);
        }

        Permission::firstOrCreate(['name' => 'payroll.view', 'guard_name' => 'web']);
        User::query()->get()->each(fn (User $user) => $user->givePermissionTo('payroll.view'));

        $laser = InventorySection::query()->firstOrCreate(['name' => 'لیزر و پوست'], ['sort_order' => 1, 'level' => 1]);
        $inject = InventorySection::query()->firstOrCreate(['name' => 'تزریقات'], ['sort_order' => 2, 'level' => 1]);

        $botox = Inventory::query()->updateOrCreate(
            ['name' => 'بوتاکس مصپورت دمو'],
            ['section_id' => $inject->id, 'amount' => 3200000, 'price' => 850000, 'stock' => 18, 'min_stock' => 4, 'active' => true, 'default_commission_type' => 'percent', 'default_commission_value' => 10]
        );
        $laserFace = Inventory::query()->updateOrCreate(
            ['name' => 'لیزر صورت دمو'],
            ['section_id' => $laser->id, 'amount' => 1800000, 'price' => 250000, 'stock' => 40, 'min_stock' => 8, 'active' => true, 'default_commission_type' => 'percent', 'default_commission_value' => 8]
        );
        $gel = Inventory::query()->updateOrCreate(
            ['name' => 'ژل لب دمو'],
            ['section_id' => $inject->id, 'amount' => 4500000, 'price' => 1600000, 'stock' => 10, 'min_stock' => 3, 'active' => true, 'default_commission_type' => 'fixed', 'default_commission_value' => 300000]
        );

        $doctorA = Doctor::query()->updateOrCreate(
            ['name' => 'دکتر نازنین رضایی'],
            ['bonus' => 10, 'commission_customer_scope' => 'both', 'commission_after_materials' => true, 'sales_bonus_enabled' => true, 'sales_bonus_tiers' => [['sales_from' => 12000000, 'salary_addition' => 1500000]], 'salary' => 28000000, 'overtime_hourly_rate' => 850000, 'shortage_hourly_deduction' => 550000, 'absence_deduction' => 2200000, 'allowed_shortage_hours' => 1]
        );
        $doctorB = Doctor::query()->updateOrCreate(
            ['name' => 'دکتر آرمان کاویانی'],
            ['bonus' => 12, 'commission_customer_scope' => 'new', 'commission_after_materials' => false, 'sales_bonus_enabled' => true, 'sales_bonus_tiers' => [['sales_from' => 9000000, 'salary_addition' => 1200000]], 'salary' => 24000000, 'overtime_hourly_rate' => 780000, 'shortage_hourly_deduction' => 500000, 'absence_deduction' => 1800000, 'allowed_shortage_hours' => 0.5]
        );
        $staffA = Staff::query()->updateOrCreate(
            ['name' => 'سارا احمدی'],
            ['bonus' => 4, 'commission_customer_scope' => 'both', 'commission_after_materials' => false, 'sales_bonus_enabled' => true, 'sales_bonus_tiers' => [['sales_from' => 8000000, 'salary_addition' => 900000]], 'salary' => 14500000, 'overtime_hourly_rate' => 320000, 'shortage_hourly_deduction' => 210000, 'absence_deduction' => 900000, 'allowed_shortage_hours' => 1]
        );
        $staffB = Staff::query()->updateOrCreate(
            ['name' => 'مریم صالحی'],
            ['bonus' => 5, 'commission_customer_scope' => 'existing', 'commission_after_materials' => false, 'sales_bonus_enabled' => false, 'sales_bonus_tiers' => [], 'salary' => 13200000, 'overtime_hourly_rate' => 300000, 'shortage_hourly_deduction' => 190000, 'absence_deduction' => 850000, 'allowed_shortage_hours' => 1]
        );

        $botox->commissions()->updateOrCreate(['recipient_type' => 'doctor', 'recipient_id' => $doctorA->id], ['recipient_name' => $doctorA->name, 'commission_type' => 'percent', 'commission_value' => 20]);
        $laserFace->commissions()->updateOrCreate(['recipient_type' => 'staff', 'recipient_id' => $staffA->id], ['recipient_name' => $staffA->name, 'commission_type' => 'percent', 'commission_value' => 7]);
        $gel->commissions()->updateOrCreate(['recipient_type' => 'doctor', 'recipient_id' => $doctorB->id], ['recipient_name' => $doctorB->name, 'commission_type' => 'fixed', 'commission_value' => 450000]);
        $gel->commissions()->updateOrCreate(['recipient_type' => 'staff', 'recipient_id' => $staffB->id], ['recipient_name' => $staffB->name, 'commission_type' => 'percent', 'commission_value' => 6]);

        $months = ['1405-04', '1405-05'];
        foreach ($months as $monthIndex => $month) {
            foreach (range(1, 8) as $day) {
                $newCustomer = $day % 2 === 1;
                Appointment::query()->updateOrCreate(
                    ['month' => $month, 'day_num' => $day, 'time' => sprintf('%02d:30', 9 + $day), 'lastname' => 'دمو حقوق '.$day.' '.$month],
                    [
                        'gender' => $day % 2 ? 'خانم' : 'آقا',
                        'phone' => '0912000'.str_pad((string) ($monthIndex * 100 + $day), 4, '0', STR_PAD_LEFT),
                        'file_number' => 'PAY-'.$month.'-'.$day,
                        'status' => 'انجام شد',
                        'done' => 'done',
                        'doctor' => $day % 3 === 0 ? $doctorB->name : $doctorA->name,
                        'consultant' => $day % 2 === 0 ? $staffB->name : $staffA->name,
                        'source' => 'دمو حقوق',
                        'new_customer' => $newCustomer,
                        'services' => [
                            ['name' => $day % 3 === 0 ? $gel->name : $botox->name, 'cc' => 1, 'discount' => $day % 4 === 0 ? 250000 : 0, 'doctor' => $day % 3 === 0 ? $doctorB->name : $doctorA->name, 'consultant' => $day % 2 === 0 ? $staffB->name : $staffA->name],
                            ['name' => $laserFace->name, 'cc' => 1, 'discount' => 100000, 'doctor' => $doctorA->name, 'consultant' => $staffA->name],
                        ],
                    ]
                );
            }

            $this->attendance($doctorA, 'doctor', $month, 8, [
                ['day' => 1, 'in' => '09:00', 'out' => '18:30', 'employerApproved' => true],
                ['day' => 2, 'in' => '09:30', 'out' => '16:30'],
                ['day' => 3, 'absent' => true],
                ['day' => 4, 'in' => '09:00', 'out' => '17:00'],
            ]);
            $this->attendance($doctorB, 'doctor', $month, 8, [
                ['day' => 1, 'in' => '10:00', 'out' => '19:00', 'employerApproved' => true],
                ['day' => 2, 'in' => '10:15', 'out' => '16:00'],
                ['day' => 3, 'absent' => true, 'leaveApproved' => true, 'leaveRequestTitle' => 'مرخصی تایید شده'],
            ]);
            $this->attendance($staffA, 'staff', $month, 8, [
                ['day' => 1, 'in' => '08:30', 'out' => '18:00', 'employerApproved' => true],
                ['day' => 2, 'in' => '09:15', 'out' => '16:45'],
                ['day' => 3, 'absent' => true],
            ]);
            $this->attendance($staffB, 'staff', $month, 8, [
                ['day' => 1, 'in' => '08:45', 'out' => '17:00'],
                ['day' => 2, 'in' => '09:00', 'out' => '18:30', 'employerApproved' => true],
                ['day' => 3, 'in' => '10:00', 'out' => '15:30'],
            ]);
        }

        $appointmentController = app(AppointmentController::class);
        $syncAppointment = new \ReflectionMethod($appointmentController, 'syncResourceEarningLines');
        Appointment::query()
            ->whereIn('month', $months)
            ->where('source', 'دمو حقوق')
            ->get()
            ->each(fn (Appointment $appointment) => $syncAppointment->invoke($appointmentController, $appointment));

        $syncBonus = new \ReflectionMethod($appointmentController, 'syncMonthlySalesBonusLines');
        ResourceEarningLine::query()->whereNull('appointment_id')->whereIn('month', $months)->where('earning_type', 'sales_bonus')->delete();
        foreach ($months as $month) {
            $syncBonus->invoke($appointmentController, $month);
        }

        $attendanceController = app(AttendanceMonthController::class);
        $syncAttendance = new \ReflectionMethod($attendanceController, 'syncAttendanceEarningLines');
        AttendanceMonth::query()
            ->whereIn('year', [1405])
            ->whereIn('month', [4, 5])
            ->get()
            ->each(fn (AttendanceMonth $month) => $syncAttendance->invoke($attendanceController, $month));
    }

    private function attendance(Doctor|Staff $resource, string $type, string $month, float $dailyHours, array $days): void
    {
        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        $normalized = collect($days)->map(function (array $day) use ($resource, $dailyHours) {
            $absent = (bool) ($day['absent'] ?? false);
            if ($absent) {
                return [
                    ...$day,
                    'in' => '',
                    'out' => '',
                    'workedHours' => 0,
                    'diff' => 0,
                    'amount' => ($day['leaveApproved'] ?? false) ? 0 : -((float) $resource->absence_deduction),
                ];
            }

            [$inH, $inM] = array_map('intval', explode(':', $day['in']));
            [$outH, $outM] = array_map('intval', explode(':', $day['out']));
            $worked = max(0, (($outH * 60 + $outM) - ($inH * 60 + $inM)) / 60);
            $diff = round($worked - $dailyHours, 2);
            $amount = 0;
            if ($diff > 0 && ($day['employerApproved'] ?? false)) {
                $amount = round($diff * (float) $resource->overtime_hourly_rate);
            } elseif ($diff < 0) {
                $amount = -round(abs($diff) * (float) $resource->shortage_hourly_deduction);
            }

            return [
                ...$day,
                'workedHours' => $worked,
                'diff' => $diff,
                'amount' => $amount,
                'absent' => false,
            ];
        })->values()->all();

        AttendanceMonth::query()->updateOrCreate(
            ['resource_type' => $type, 'resource_id' => $resource->id, 'year' => $year, 'month' => $monthNumber],
            ['name' => $resource->name, 'daily_hours' => $dailyHours, 'days' => $normalized]
        );
    }
}
