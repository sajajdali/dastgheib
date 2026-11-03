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
use App\Models\Staff;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
            $table->decimal('discount', 15, 2)->default(0); $table->json('services')->nullable(); $table->timestamps();
        });
        $this->createPayrollTables();

        $this->doctor = Doctor::query()->create(['name' => 'دکتر تست', 'new_customer_bonus' => 20, 'existing_customer_bonus' => 20, 'salary' => 5000000]);
        $this->staff = Staff::query()->create(['name' => 'مشاور تست', 'new_customer_bonus' => 5, 'existing_customer_bonus' => 5]);
    }

    private function visit(array $service, ?string $done = 'انجام شد', string $month = '1405-08'): Appointment
    {
        return Appointment::query()->create([
            'month' => $month, 'day_num' => 3, 'done' => $done,
            'doctor' => $this->doctor->name, 'consultant' => $this->staff->name,
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
}
