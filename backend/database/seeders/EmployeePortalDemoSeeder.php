<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\AttendanceMonthController;
use App\Models\AttendanceMonth;
use App\Models\Doctor;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeMessage;
use App\Models\EmployeePortalNotification;
use App\Models\ResourceAdjustment;
use App\Models\ResourceEarningLine;
use App\Models\SalaryAdvance;
use App\Models\Staff;
use App\Models\User;
use App\Services\EmployeePortalService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeePortalDemoSeeder extends Seeder
{
    private const MARKER = '[پرتال دمو]';
    private const MONTH = '1405-08';

    public function run(): void
    {
        $manager = User::permission('employee_portal.manage')->first() ?? User::query()->firstOrFail();
        $resources = collect()
            ->merge(Doctor::query()->whereIn('name', ['دکتر الهام صدری', 'دکتر بهراد نیک‌پور', 'دکتر ستاره امینی'])->whereNotNull('user_id')->get()->map(fn ($row) => ['type' => 'doctor', 'model' => $row]))
            ->merge(Staff::query()->whereIn('name', ['زهره کاظمی', 'پریا رحمانی', 'مهدی سلیمانی'])->whereNotNull('user_id')->get()->map(fn ($row) => ['type' => 'staff', 'model' => $row]));

        abort_if($resources->isEmpty(), 422, 'هیچ منبع تستی متصل به کاربر پیدا نشد. ابتدا AssistantTestDataSeeder را اجرا کنید.');

        DB::transaction(function () use ($resources, $manager) {
            $this->cleanup();
            foreach ($resources->values() as $index => $resource) {
                $this->seedResource($resource['type'], $resource['model'], $manager, $index);
            }
        });

        $this->command?->info($resources->count().' پرونده پرسنلی با داده‌های نمایشی کامل شد.');
    }

    private function cleanup(): void
    {
        ResourceEarningLine::query()->where('description', 'like', self::MARKER.'%')->delete();
        ResourceAdjustment::query()->where('description', 'like', self::MARKER.'%')->delete();
        EmployeeLeaveRequest::query()->where('reason', 'like', self::MARKER.'%')->delete();
        SalaryAdvance::query()->where('reason', 'like', self::MARKER.'%')->delete();
        EmployeeMessage::query()->where('body', 'like', self::MARKER.'%')->delete();
        EmployeePortalNotification::query()->where('title', 'like', self::MARKER.'%')->delete();
    }

    private function seedResource(string $type, Doctor|Staff $person, User $manager, int $index): void
    {
        $person->update([
            'salary' => (float) ($person->salary ?: ($type === 'doctor' ? 38000000 : 18500000)),
            'overtime_hourly_rate' => (float) ($person->overtime_hourly_rate ?: ($type === 'doctor' ? 850000 : 360000)),
            'shortage_hourly_deduction' => (float) ($person->shortage_hourly_deduction ?: ($type === 'doctor' ? 520000 : 230000)),
            'absence_deduction' => (float) ($person->absence_deduction ?: ($type === 'doctor' ? 1900000 : 900000)),
            'allowed_shortage_hours' => 1,
        ]);

        foreach (['1405-07', self::MONTH] as $monthIndex => $month) {
            $services = [
                ['بوتاکس پیشانی', 1, 4800000, 480000, 'base_commission'],
                ['فیلر لب یک سی‌سی', 1, 7200000, 720000, 'inventory_commission'],
                ['مزوتراپی صورت', 2, 5600000, 560000, 'base_commission'],
                ['هیدرافیشیال VIP', 1, 2300000, 230000, 'inventory_default_commission'],
                ['لیزر فول‌بادی', 1, 3900000, 390000, 'base_commission'],
                ['معرفی مشتری جدید', 1, 6100000, 305000, 'referral_commission'],
            ];
            foreach ($services as $lineIndex => [$service, $quantity, $gross, $commission, $earningType]) {
                $discount = ($lineIndex + $index) % 3 === 0 ? 250000 : 0;
                ResourceEarningLine::query()->create([
                    'appointment_id' => null,
                    'month' => $month,
                    'day_num' => 2 + $lineIndex * 3,
                    'earned_at' => now()->subDays(20 - $lineIndex),
                    'resource_type' => $type,
                    'resource_id' => $person->id,
                    'resource_name' => $person->name,
                    'earning_type' => $earningType,
                    'service_name' => $service,
                    'quantity' => $quantity,
                    'gross_amount' => $gross,
                    'discount_amount' => $discount,
                    'net_amount' => $gross - $discount,
                    'commission_base' => $gross - $discount,
                    'commission_type' => 'percent',
                    'commission_value' => 10,
                    'amount' => $commission + $index * 15000 + $monthIndex * 25000,
                    'status' => 'active',
                    'appointment_new_customer' => $lineIndex % 2 === 0,
                    'calculation_snapshot' => ['demo' => true, 'rule' => '۱۰٪ مبلغ خالص خدمت'],
                    'description' => self::MARKER.' محاسبه نمونه برای نمایش ریز پورسانت',
                ]);
            }
            ResourceEarningLine::query()->create([
                'month' => $month, 'day_num' => 28, 'earned_at' => now(), 'resource_type' => $type,
                'resource_id' => $person->id, 'resource_name' => $person->name, 'earning_type' => 'sales_bonus',
                'quantity' => 1, 'gross_amount' => 1200000, 'net_amount' => 1200000, 'commission_base' => 18000000,
                'commission_type' => 'tier', 'commission_value' => 1200000, 'amount' => 1200000 + $index * 50000,
                'status' => 'active', 'description' => self::MARKER.' پاداش پلکانی فروش ماهانه',
            ]);
        }

        $this->leaves($type, $person, $manager);
        $this->attendance($type, $person);
        $this->adjustments($type, $person, $manager, $index);
        $this->advances($type, $person, $manager, $index);
        $this->messages($type, $person, $manager);
        $this->notifications($type, $person, $manager);
    }

    private function attendance(string $type, Doctor|Staff $person): void
    {
        $days = collect(range(1, 18))->map(function (int $day) use ($person) {
            if ($day === 9) return ['day' => $day, 'in' => '', 'out' => '', 'workedHours' => 0, 'diff' => 0, 'amount' => 0, 'absent' => true, 'leaveApproved' => true, 'leaveRequestTitle' => 'مرخصی روزانه تأییدشده'];
            if ($day === 14) return ['day' => $day, 'in' => '', 'out' => '', 'workedHours' => 0, 'diff' => 0, 'amount' => -(float) $person->absence_deduction, 'absent' => true];
            $in = $day % 4 === 0 ? '09:20' : '09:00';
            $out = $day % 5 === 0 ? '18:30' : ($day % 4 === 0 ? '16:30' : '17:00');
            $worked = $out === '18:30' ? 9.5 : ($out === '16:30' ? 7.17 : 8);
            $diff = round($worked - 8, 2);
            return ['day' => $day, 'in' => $in, 'out' => $out, 'workedHours' => $worked, 'diff' => $diff,
                'amount' => $diff > 0 ? round($diff * (float) $person->overtime_hourly_rate) : ($diff < 0 ? -round(abs($diff) * (float) $person->shortage_hourly_deduction) : 0),
                'employerApproved' => $diff > 0, 'absent' => false];
        })->all();

        AttendanceMonth::query()->updateOrCreate(
            ['resource_type' => $type, 'resource_id' => $person->id, 'year' => 1405, 'month' => 8],
            ['name' => $person->name, 'daily_hours' => 8, 'days' => $days]
        );
        app(AttendanceMonthController::class)->resyncAttendanceEarnings($type, $person->id);
    }

    private function adjustments(string $type, Doctor|Staff $person, User $manager, int $index): void
    {
        foreach ([
            ['bonus', 'رضایت بالای مراجعان', 850000 + $index * 50000, 'امتیاز عالی در ارزیابی این ماه'],
            ['bonus', 'همکاری در شیفت شلوغ', 600000, 'همکاری خارج از برنامه کاری'],
            ['deduction', 'اصلاح کسری صندوق', -180000, 'اصلاح ثبت مالی پایان شیفت'],
        ] as $offset => [$kind, $title, $amount, $description]) {
            ResourceAdjustment::query()->create([
                'month' => self::MONTH, 'resource_type' => $type, 'resource_id' => $person->id,
                'amount' => $amount, 'reason' => $title, 'kind' => $kind, 'occurred_on' => sprintf('%s-%02d', self::MONTH, 18 + $offset),
                'title' => $title, 'description' => self::MARKER.' '.$description, 'status' => 'active',
                'created_by' => $manager->id, 'created_by_name' => $manager->name,
            ]);
        }
    }

    private function leaves(string $type, Doctor|Staff $person, User $manager): void
    {
        foreach ([
            ['daily', '1405-08-09', '1405-08-09', null, null, 'approved', 'مرخصی تأیید شد.'],
            ['hourly', '1405-08-17', '1405-08-17', '11:00', '13:30', 'pending', null],
            ['daily', '1405-07-22', '1405-07-23', null, null, 'rejected', 'به‌دلیل تداخل با برنامه شیفت تأیید نشد.'],
        ] as [$leaveType, $start, $end, $startTime, $endTime, $status, $note]) {
            EmployeeLeaveRequest::query()->create([
                'resource_type' => $type, 'resource_id' => $person->id, 'user_id' => $person->user_id,
                'leave_type' => $leaveType, 'start_date' => $start, 'end_date' => $end,
                'start_time' => $startTime, 'end_time' => $endTime, 'reason' => self::MARKER.' درخواست نمونه برای امور شخصی',
                'status' => $status, 'manager_note' => $note, 'reviewed_by' => $status === 'pending' ? null : $manager->id,
                'reviewed_at' => $status === 'pending' ? null : now()->subDays(3),
            ]);
        }
    }

    private function advances(string $type, Doctor|Staff $person, User $manager, int $index): void
    {
        $portal = app(EmployeePortalService::class);
        foreach ([
            ['pending', 6000000 + $index * 250000, null, 1, null, 'در انتظار بررسی مدیریت'],
            ['paid', 9000000, 9000000, 3, self::MONTH, 'پرداخت شد و در سه قسط کسر می‌شود'],
            ['rejected', 3000000, null, 1, null, 'به‌دلیل وجود مساعده فعال رد شد'],
        ] as [$status, $requested, $approved, $count, $start, $note]) {
            $advance = SalaryAdvance::query()->create([
                'resource_type' => $type, 'resource_id' => $person->id, 'user_id' => $person->user_id,
                'requested_amount' => $requested, 'approved_amount' => $approved, 'reason' => self::MARKER.' درخواست مساعده نمونه',
                'manager_note' => $note, 'status' => $status, 'deduct_from_salary' => $status === 'paid',
                'installment_count' => $count, 'deduction_start_month' => $start,
                'reviewed_by' => $status === 'pending' ? null : $manager->id, 'reviewed_at' => $status === 'pending' ? null : now()->subDays(2),
                'paid_at' => $status === 'paid' ? now()->subDay() : null,
            ]);
            $portal->syncAdvanceInstallments($advance);
        }
    }

    private function messages(string $type, Doctor|Staff $person, User $manager): void
    {
        $rows = [
            [$person->user_id, $person->name, 'سلام، گزارش کارکرد این ماه را بررسی کردم. ممنون می‌شوم مساعده را هم بررسی کنید.'],
            [$manager->id, $manager->name, 'سلام، درخواست شما دریافت شد و تا پایان امروز بررسی می‌شود.'],
            [$person->user_id, $person->name, 'سپاسگزارم. همچنین مرخصی ساعتی هفته آینده را ثبت کرده‌ام.'],
            [$manager->id, $manager->name, 'بله، درخواست در پنل مدیریت قابل مشاهده است. نتیجه از طریق اعلان اطلاع داده می‌شود.'],
            [$person->user_id, $person->name, 'خیلی ممنون، روز خوبی داشته باشید.'],
        ];
        foreach ($rows as $offset => [$senderId, $senderName, $body]) {
            EmployeeMessage::query()->create([
                'resource_type' => $type, 'resource_id' => $person->id, 'sender_user_id' => $senderId,
                'sender_name' => $senderName, 'body' => self::MARKER.' '.$body,
                'read_by_employee_at' => $senderId === $person->user_id ? now() : ($offset < 3 ? now() : null),
                'read_by_manager_at' => $senderId === $manager->id ? now() : ($offset < 2 ? now() : null),
                'created_at' => now()->subHours(10 - $offset * 2), 'updated_at' => now()->subHours(10 - $offset * 2),
            ]);
        }
    }

    private function notifications(string $type, Doctor|Staff $person, User $manager): void
    {
        $employeeRows = [
            ['adjustment_created', 'پاداش جدید ثبت شد', 'پاداش رضایت مراجعان به حقوق این ماه اضافه شد.', 'adjustments'],
            ['leave_approved', 'مرخصی شما تأیید شد', 'مرخصی روز ۹ آبان بدون کسر حقوق ثبت شد.', 'leaves'],
            ['advance_paid', 'مساعده پرداخت شد', 'مبلغ مساعده پرداخت و برنامه اقساط ثبت شد.', 'advances'],
            ['message_received', 'پیام جدید از مدیریت', 'پاسخ مدیریت در گفت‌وگوی پرسنلی ثبت شد.', 'messages'],
        ];
        foreach ($employeeRows as $offset => [$event, $title, $body, $tab]) {
            EmployeePortalNotification::query()->create([
                'user_id' => $person->user_id, 'resource_type' => $type, 'resource_id' => $person->id,
                'type' => $event, 'title' => self::MARKER.' '.$title, 'body' => $body, 'action_tab' => $tab,
                'data' => ['demo' => true], 'read_at' => $offset === 0 ? now() : null,
                'created_at' => now()->subHours(8 - $offset), 'updated_at' => now()->subHours(8 - $offset),
            ]);
        }
        foreach ([['leave_requested', 'درخواست مرخصی جدید', 'leaves'], ['advance_requested', 'درخواست مساعده جدید', 'advances'], ['message_received', 'پیام جدید پرسنل', 'messages']] as $offset => [$event, $title, $tab]) {
            EmployeePortalNotification::query()->create([
                'user_id' => $manager->id, 'resource_type' => $type, 'resource_id' => $person->id,
                'type' => $event, 'title' => self::MARKER.' '.$title, 'body' => $person->name, 'action_tab' => $tab,
                'data' => ['demo' => true], 'created_at' => now()->subHours(5 - $offset), 'updated_at' => now()->subHours(5 - $offset),
            ]);
        }
    }
}
