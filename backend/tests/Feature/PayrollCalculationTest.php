<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AttendanceMonthController;
use App\Http\Controllers\Api\PayrollReportController;
use App\Http\Controllers\AppointmentController;
use App\Models\Appointment;
use App\Models\AttendanceMonth;
use App\Models\Doctor;
use App\Models\Inventory;
use App\Models\InventoryCommission;
use App\Models\ResourceEarningLine;
use App\Models\SalaryAdvance;
use App\Models\Staff;
use App\Services\EmployeePortalService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPayrollTables;
use Tests\TestCase;

/** محاسبهٔ پورسانت و جمع حقوق پزشک از روی نوبت‌های انجام‌شده. */
class PayrollCalculationTest extends TestCase
{
    use CreatesPayrollTables;

    private Doctor $doctor;
    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('appointments', function (Blueprint $table) {
            $table->id(); $table->string('month')->nullable(); $table->unsignedInteger('day_num')->nullable();
            $table->string('time')->nullable(); $table->string('lastname')->nullable();
            $table->string('source')->nullable(); $table->string('doctor')->nullable(); $table->string('consultant')->nullable();
            $table->string('file_number')->nullable(); $table->string('phone')->nullable();
            $table->boolean('doctor_commission_enabled')->default(true); $table->boolean('staff_commission_enabled')->default(true);
            $table->string('done')->nullable(); $table->boolean('new_customer')->default(false);
            $table->decimal('amount', 15, 2)->default(0); $table->decimal('original_amount', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0); $table->decimal('wallet_applied', 15, 2)->default(0);
            $table->json('payment_details')->nullable(); $table->json('services')->nullable(); $table->timestamps();
        });
        $this->createPayrollTables();

        $this->doctor = Doctor::query()->create(['name' => 'دکتر تست', 'new_customer_bonus' => 20, 'existing_customer_bonus' => 20, 'salary' => 5000000]);
        $this->staff = Staff::query()->create(['name' => 'مشاور تست', 'new_customer_bonus' => 5, 'existing_customer_bonus' => 5]);
    }

    private function visit(array $service, ?string $done = 'انجام شد', string $month = '1405-08', bool $paid = true): Appointment
    {
        return Appointment::query()->create([
            'month' => $month, 'day_num' => 3, 'done' => $done,
            'doctor' => $this->doctor->name, 'consultant' => $this->staff->name,
            'payment_details' => $paid ? ['ledger_total' => 1000000, 'cash' => 1000000] : ['ledger_total' => 0, 'cash' => 0, 'card' => 0],
            'services' => [['cc' => 1, 'doctor' => $this->doctor->name, 'consultant' => $this->staff->name, ...$service]],
        ]);
    }

    private function resync(): void
    {
        app(AppointmentController::class)->resyncAllResourceEarnings();
    }

    private function commission(Appointment $visit, string $type = 'doctor'): float
    {
        return (float) ResourceEarningLine::query()->where('appointment_id', $visit->id)
            ->where('resource_type', $type)->where('status', 'active')->sum('amount');
    }

    private function payroll(string $month = '1405-08'): array
    {
        return app(PayrollReportController::class)->show(Request::create('/', 'GET', [
            'resource_type' => 'doctor', 'resource_id' => $this->doctor->id, 'month' => $month,
        ]))->getData(true);
    }

    public function test_inventory_default_commission_is_counted_in_payroll_and_sales_bonus(): void
    {
        Inventory::query()->create(['name' => 'بوتاکس', 'amount' => 1000000, 'default_commission_type' => 'percent', 'default_commission_value' => 10]);
        $this->doctor->update(['sales_bonus_enabled' => true, 'sales_bonus_tiers' => [['sales_from' => 1000000, 'salary_addition' => 300000]]]);
        $this->visit(['name' => 'بوتاکس']);

        $this->resync();
        $summary = $this->payroll()['summary'];

        $this->assertSame(100000.0, (float) $summary['inventory_commission']);
        $this->assertSame(100000.0, (float) $summary['total_commission']);
        $this->assertSame(300000.0, (float) $summary['sales_bonus']);
        $this->assertSame(5400000.0, (float) $summary['total_earned']);
    }

    public function test_surcharge_is_not_treated_as_discount(): void
    {
        Inventory::query()->create(['name' => 'لیزر', 'amount' => 1000000]);
        $withoutFlag = $this->visit(['name' => 'لیزر', 'adjustment_mode' => 'surcharge', 'discount' => 200000]);
        $withFlag = $this->visit(['name' => 'لیزر', 'adjustment_mode' => 'surcharge', 'discount' => 200000, 'surcharge_for_doctor_commission' => true]);
        $discounted = $this->visit(['name' => 'لیزر', 'adjustment_mode' => 'discount', 'discount' => 200000]);

        $this->resync();

        $this->assertSame(200000.0, $this->commission($withoutFlag));
        $this->assertSame(240000.0, $this->commission($withFlag));
        $this->assertSame(160000.0, $this->commission($discounted));
        $this->assertSame(60000.0, $this->commission($withoutFlag, 'staff'));
    }

    public function test_only_done_appointments_with_quantity_earn_commission(): void
    {
        Inventory::query()->create(['name' => 'ویزیت', 'amount' => 500000]);
        $pending = $this->visit(['name' => 'ویزیت'], null);
        $zeroQuantity = $this->visit(['name' => 'ویزیت', 'cc' => 0]);

        $this->resync();
        $this->assertSame(0.0, $this->commission($pending));
        $this->assertSame(0.0, $this->commission($zeroQuantity));

        $pending->update(['done' => 'انجام شد']);
        $this->resync();
        $this->assertSame(100000.0, $this->commission($pending));

        $pending->update(['done' => null]);
        $this->resync();
        $this->assertSame(0, ResourceEarningLine::query()->where('appointment_id', $pending->id)->count());
    }

    public function test_unpaid_done_appointments_do_not_earn_commission_until_payment_is_registered(): void
    {
        Inventory::query()->create(['name' => 'ویزیت', 'amount' => 500000]);
        $unpaid = $this->visit(['name' => 'ویزیت'], 'انجام شد', '1405-08', false);

        $this->resync();
        $this->assertSame(0.0, $this->commission($unpaid));
        $this->assertSame(0.0, app(AppointmentController::class)->estimatedResourceCommission($unpaid));

        $unpaid->update(['payment_details' => ['ledger_total' => 500000, 'cash' => 500000]]);
        $this->resync();
        $this->assertSame(100000.0, $this->commission($unpaid));
    }

    public function test_manual_payroll_edits_survive_recalculation_and_follow_the_appointment_date(): void
    {
        Inventory::query()->create(['name' => 'فیلر', 'amount' => 1000000]);
        $visit = $this->visit(['name' => 'فیلر']);
        $this->resync();

        $doctorLine = ResourceEarningLine::query()->where('appointment_id', $visit->id)->where('resource_type', 'doctor')->firstOrFail();
        app(PayrollReportController::class)->updateLine(Request::create('/', 'PATCH', ['amount' => 250000]), $doctorLine);
        $staffLine = ResourceEarningLine::query()->where('appointment_id', $visit->id)->where('resource_type', 'staff')->firstOrFail();
        app(PayrollReportController::class)->destroyLine($staffLine);

        $visit->update(['month' => '1405-09', 'day_num' => 7]);
        $this->resync();

        $doctorLine->refresh();
        $this->assertSame(250000.0, (float) $doctorLine->amount);
        $this->assertSame('1405-09', $doctorLine->month);
        $this->assertSame(7, (int) $doctorLine->day_num);
        $this->assertSame('deleted', $staffLine->fresh()->status);
        $this->assertSame(2, ResourceEarningLine::query()->where('appointment_id', $visit->id)->count());
        $this->assertSame(250000.0, (float) $this->payroll('1405-09')['summary']['service_commission']);
        $this->assertSame(0.0, (float) $this->payroll('1405-08')['summary']['service_commission']);
    }

    public function test_commission_priority_is_person_exception_then_inventory_default_then_resource(): void
    {
        $withException = Inventory::query()->create(['name' => 'استایلج', 'amount' => 1000000, 'default_commission_type' => 'percent', 'default_commission_value' => 10]);
        InventoryCommission::query()->create(['inventory_id' => $withException->id, 'recipient_type' => 'doctor', 'recipient_id' => $this->doctor->id, 'recipient_name' => $this->doctor->name, 'commission_type' => 'fixed', 'commission_value' => 50000]);
        Inventory::query()->create(['name' => 'میکرونیدلینگ', 'amount' => 1000000, 'default_commission_type' => 'percent', 'default_commission_value' => 10]);
        Inventory::query()->create(['name' => 'پیلینگ', 'amount' => 1000000]);
        $exception = $this->visit(['name' => 'استایلج', 'cc' => 2]);
        $default = $this->visit(['name' => 'میکرونیدلینگ']);
        $resource = $this->visit(['name' => 'پیلینگ']);

        $this->resync();

        $this->assertSame(100000.0, $this->commission($exception));
        $this->assertSame(200000.0, $this->commission($exception, 'staff'));
        $this->assertSame(100000.0, $this->commission($default));
        $this->assertSame(200000.0, $this->commission($resource));
    }

    public function test_exception_for_a_system_user_applies_to_their_linked_resource(): void
    {
        $this->staff->update(['user_id' => 42]);
        $inventory = Inventory::query()->create(['name' => 'لیزر', 'amount' => 1000000]);
        InventoryCommission::query()->create(['inventory_id' => $inventory->id, 'recipient_type' => 'user', 'recipient_id' => 42, 'recipient_name' => 'کاربر مشاور', 'commission_type' => 'percent', 'commission_value' => 12]);
        $visit = $this->visit(['name' => 'لیزر']);

        $this->resync();

        $this->assertSame(120000.0, $this->commission($visit, 'staff'));
        $this->assertSame(200000.0, $this->commission($visit));
    }

    public function test_inventory_rules_ignore_customer_type_but_resource_percent_does_not(): void
    {
        $this->doctor->update(['existing_customer_bonus' => null]);
        Inventory::query()->create(['name' => 'بوتاکس', 'amount' => 1000000, 'default_commission_type' => 'percent', 'default_commission_value' => 10]);
        Inventory::query()->create(['name' => 'پیلینگ', 'amount' => 1000000]);

        $inventoryRule = $this->visit(['name' => 'بوتاکس']);
        $resourceRule = $this->visit(['name' => 'پیلینگ']);
        $this->resync();

        $this->assertSame(100000.0, $this->commission($inventoryRule));
        $this->assertSame(0.0, $this->commission($resourceRule));
    }

    public function test_materials_flag_applies_to_percent_rules_everywhere_but_not_fixed_amounts(): void
    {
        $this->doctor->update(['commission_after_materials' => true]);
        $percent = Inventory::query()->create(['name' => 'فیلر', 'amount' => 1000000, 'price' => 400000, 'default_commission_type' => 'percent', 'default_commission_value' => 10]);
        Inventory::query()->create(['name' => 'نخ', 'amount' => 1000000, 'price' => 400000, 'default_commission_type' => 'fixed', 'default_commission_value' => 70000]);
        Inventory::query()->create(['name' => 'مزو', 'amount' => 1000000, 'price' => 400000]);
        InventoryCommission::query()->create(['inventory_id' => $percent->id, 'recipient_type' => 'staff', 'recipient_id' => $this->staff->id, 'recipient_name' => $this->staff->name, 'commission_type' => 'percent', 'commission_value' => 10]);
        $percentVisit = $this->visit(['name' => 'فیلر']);
        $fixedVisit = $this->visit(['name' => 'نخ']);
        $resourceVisit = $this->visit(['name' => 'مزو']);

        $this->resync();

        $this->assertSame(60000.0, $this->commission($percentVisit));
        $this->assertSame(100000.0, $this->commission($percentVisit, 'staff'));
        $this->assertSame(70000.0, $this->commission($fixedVisit));
        $this->assertSame(120000.0, $this->commission($resourceVisit));
    }

    public function test_profit_estimate_uses_the_same_commission_rules_before_the_visit_is_done(): void
    {
        $inventory = Inventory::query()->create(['name' => 'استایلج', 'amount' => 1000000, 'default_commission_type' => 'percent', 'default_commission_value' => 10]);
        InventoryCommission::query()->create(['inventory_id' => $inventory->id, 'recipient_type' => 'doctor', 'recipient_id' => $this->doctor->id, 'recipient_name' => $this->doctor->name, 'commission_type' => 'fixed', 'commission_value' => 50000]);
        $visit = $this->visit(['name' => 'استایلج'], null);
        $unpaid = $this->visit(['name' => 'استایلج'], null, '1405-08', false);

        $this->assertSame(150000.0, app(AppointmentController::class)->estimatedResourceCommission($visit));
        $this->assertSame(0.0, app(AppointmentController::class)->estimatedResourceCommission($unpaid));
        $this->resync();
        $this->assertSame(0, ResourceEarningLine::query()->where('appointment_id', $visit->id)->count());
    }

    public function test_legacy_appointment_discount_reduces_the_first_service_only_when_lines_have_none(): void
    {
        Inventory::query()->create(['name' => 'بوتاکس', 'amount' => 1000000]);
        Inventory::query()->create(['name' => 'فیلر', 'amount' => 1000000]);
        $legacy = $this->visit(['name' => 'بوتاکس']);
        $legacy->update(['discount' => 100000, 'services' => [
            ['name' => 'بوتاکس', 'cc' => 1, 'discount' => 0, 'doctor' => $this->doctor->name],
            ['name' => 'فیلر', 'cc' => 1, 'doctor' => $this->doctor->name],
        ]]);
        $lineDiscount = $this->visit(['name' => 'فیلر']);
        $lineDiscount->update(['discount' => 200000, 'services' => [
            ['name' => 'بوتاکس', 'cc' => 1, 'doctor' => $this->doctor->name],
            ['name' => 'فیلر', 'cc' => 1, 'discount' => '200,000', 'doctor' => $this->doctor->name],
        ]]);

        $this->resync();

        $this->assertSame(380000.0, $this->commission($legacy));
        $this->assertSame(360000.0, $this->commission($lineDiscount));
    }

    public function test_sales_target_counts_every_done_service_after_materials_and_reaching_the_tier_is_enough(): void
    {
        $this->doctor->update(['sales_bonus_enabled' => true, 'sales_bonus_tiers' => [
            ['sales_from' => 1000000, 'salary_addition' => 200000],
            ['sales_from' => 1500000, 'salary_addition' => 500000],
        ]]);
        $filler = Inventory::query()->create(['name' => 'فیلر', 'amount' => 1000000, 'price' => 400000]);
        InventoryCommission::query()->create(['inventory_id' => $filler->id, 'recipient_type' => 'doctor', 'recipient_id' => $this->doctor->id, 'recipient_name' => $this->doctor->name, 'commission_type' => 'fixed', 'commission_value' => 0]);
        Inventory::query()->create(['name' => 'لیزر', 'amount' => 1000000, 'price' => 100000]);
        $this->visit(['name' => 'فیلر']);
        $this->visit(['name' => 'لیزر', 'discount' => 100000]);
        $this->visit(['name' => 'لیزر'], null);

        $this->resync();
        $summary = $this->payroll()['summary'];

        $this->assertSame(0.0, $this->commission(Appointment::query()->first()));
        $this->assertSame(1400000.0, (float) $summary['target_sales']);
        $this->assertSame(200000.0, (float) $summary['sales_bonus']);

        $this->visit(['name' => 'لیزر', 'discount' => 0, 'cc' => 1, 'adjustment_mode' => 'discount']);
        Inventory::query()->where('name', 'لیزر')->update(['price' => 0]);
        $this->resync();
        $summary = $this->payroll()['summary'];
        $this->assertSame(2500000.0, (float) $summary['target_sales']);
        $this->assertSame(500000.0, (float) $summary['sales_bonus']);
        $this->assertSame(1500000.0, (float) $summary['reached_tier']['sales_from']);
    }

    public function test_sales_target_exactly_reached_and_manual_bonus_edits_are_kept(): void
    {
        $this->doctor->update(['sales_bonus_enabled' => true, 'sales_bonus_tiers' => [['sales_from' => 600000, 'salary_addition' => 150000]]]);
        Inventory::query()->create(['name' => 'میکرو', 'amount' => 1000000, 'price' => 400000]);
        $this->visit(['name' => 'میکرو']);

        $this->resync();
        $bonus = ResourceEarningLine::query()->where('earning_type', 'sales_bonus')->where('resource_type', 'doctor')->firstOrFail();
        $this->assertSame(150000.0, (float) $bonus->amount);
        $this->assertSame(600000.0, (float) $bonus->commission_base);

        app(PayrollReportController::class)->updateLine(Request::create('/', 'PATCH', ['amount' => 175000]), $bonus);
        $this->resync();
        $this->assertSame(1, ResourceEarningLine::query()->where('earning_type', 'sales_bonus')->where('resource_type', 'doctor')->count());
        $this->assertSame(175000.0, (float) $bonus->fresh()->amount);
    }

    public function test_allowed_shortage_hours_are_taken_from_the_first_short_days_of_the_month(): void
    {
        $this->doctor->update(['shortage_hourly_deduction' => 100000, 'absence_deduction' => 700000, 'allowed_shortage_hours' => 2.5]);
        $short = fn (int $day) => ['day' => $day, 'diff' => -1, 'amount' => -100000];
        AttendanceMonth::query()->create([
            'resource_type' => 'doctor', 'resource_id' => $this->doctor->id, 'year' => 1405, 'month' => 8, 'daily_hours' => 8,
            'days' => [$short(9), $short(2), ['day' => 4, 'absent' => true, 'amount' => -700000], $short(1), $short(5)],
        ]);

        app(AttendanceMonthController::class)->resyncAttendanceEarnings();
        $shortageLines = ResourceEarningLine::query()->where('earning_type', 'attendance_shortage')->orderBy('day_num')->get();

        $this->assertSame([5, 9], $shortageLines->pluck('day_num')->map(fn ($day) => (int) $day)->all());
        $this->assertSame([-50000.0, -100000.0], $shortageLines->pluck('amount')->map(fn ($amount) => (float) $amount)->all());
        $this->assertSame(0.5, (float) $shortageLines->first()->quantity);
        $summary = $this->payroll()['summary'];
        $this->assertSame(-150000.0, (float) $summary['shortage']);
        $this->assertSame(-700000.0, (float) $summary['absence']);

        $this->doctor->update(['allowed_shortage_hours' => 10]);
        app(AttendanceMonthController::class)->resyncAttendanceEarnings('doctor', $this->doctor->id);
        $this->assertSame(0.0, (float) $this->payroll()['summary']['shortage']);
    }

    public function test_manual_adjustments_and_paid_advance_installment_change_payroll_total(): void
    {
        DB::table('resource_adjustments')->insert([
            ['month' => '1405-08', 'resource_type' => 'doctor', 'resource_id' => $this->doctor->id, 'amount' => 300000, 'kind' => 'bonus', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            ['month' => '1405-08', 'resource_type' => 'doctor', 'resource_id' => $this->doctor->id, 'amount' => -100000, 'kind' => 'deduction', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $advanceId = DB::table('salary_advances')->insertGetId([
            'resource_type' => 'doctor', 'resource_id' => $this->doctor->id, 'requested_amount' => 600000,
            'approved_amount' => 600000, 'status' => 'paid', 'deduct_from_salary' => true,
            'installment_count' => 2, 'deduction_start_month' => '1405-08', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('salary_advance_installments')->insert([
            'salary_advance_id' => $advanceId, 'month' => '1405-08', 'amount' => 300000, 'status' => 'scheduled', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $summary = $this->payroll()['summary'];
        $this->assertSame(300000.0, (float) $summary['manual_bonus']);
        $this->assertSame(-100000.0, (float) $summary['manual_deduction']);
        $this->assertSame(-300000.0, (float) $summary['advance_deduction']);
        $this->assertSame(4900000.0, (float) $summary['total_earned']);
    }

    public function test_approved_daily_and_hourly_leave_prevent_only_the_covered_deductions(): void
    {
        $this->doctor->update(['shortage_hourly_deduction' => 100000, 'absence_deduction' => 700000]);
        AttendanceMonth::query()->create([
            'resource_type' => 'doctor', 'resource_id' => $this->doctor->id, 'year' => 1405, 'month' => 8, 'daily_hours' => 8,
            'days' => [
                ['day' => 2, 'absent' => true, 'amount' => -700000],
                ['day' => 3, 'diff' => -3, 'amount' => -300000],
                ['day' => 4, 'absent' => true, 'amount' => -700000],
            ],
        ]);
        DB::table('employee_leave_requests')->insert([
            ['resource_type' => 'doctor', 'resource_id' => $this->doctor->id, 'leave_type' => 'daily', 'start_date' => '1405-08-02', 'end_date' => '1405-08-02', 'start_time' => null, 'end_time' => null, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
            ['resource_type' => 'doctor', 'resource_id' => $this->doctor->id, 'leave_type' => 'hourly', 'start_date' => '1405-08-03', 'end_date' => '1405-08-03', 'start_time' => '09:00', 'end_time' => '11:00', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
        ]);

        app(AttendanceMonthController::class)->resyncAttendanceEarnings('doctor', $this->doctor->id);
        $summary = $this->payroll()['summary'];
        $this->assertSame(-100000.0, (float) $summary['shortage']);
        $this->assertSame(-700000.0, (float) $summary['absence']);
        $this->assertSame([3, 4], ResourceEarningLine::query()->whereIn('earning_type', ['attendance_shortage', 'attendance_absence'])->orderBy('day_num')->pluck('day_num')->map(fn ($day) => (int) $day)->all());
    }

    public function test_salary_range_is_prorated_and_advance_remainder_is_distributed_exactly(): void
    {
        $portal = app(EmployeePortalService::class);
        $this->doctor->update(['salary' => 3100000]);
        $this->assertSame(1100000.0, $portal->salaryForRange($this->doctor->fresh(), '1405-01-10', '1405-01-20'));

        $advance = SalaryAdvance::query()->create([
            'resource_type' => 'doctor', 'resource_id' => $this->doctor->id,
            'requested_amount' => 1000000, 'approved_amount' => 1000000,
            'status' => 'approved', 'deduct_from_salary' => true,
            'installment_count' => 3, 'deduction_start_month' => '1405-12',
        ]);
        $portal->syncAdvanceInstallments($advance);

        $this->assertSame(['1405-12', '1406-01', '1406-02'], $advance->installments()->orderBy('month')->pluck('month')->all());
        $this->assertSame(1000000.0, (float) $advance->installments()->sum('amount'));
    }
}
