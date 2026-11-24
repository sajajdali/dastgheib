<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PayrollReportController;
use App\Http\Controllers\AppointmentController;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Inventory;
use App\Models\ResourceEarningLine;
use App\Models\Staff;
use App\Models\User;
use App\Support\AppointmentPastAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesPayrollTables;
use Tests\TestCase;

/** منبع تخفیف، پورسانت معرفی پزشک/پرسنل، قفل ثبت نهایی خدمت و دسترسی نوبت‌های گذشته. */
class AppointmentServiceRulesTest extends TestCase
{
    use CreatesPayrollTables;

    private Doctor $doctor;
    private Doctor $referrerDoctor;
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
            $table->boolean('referral_reward_enabled')->default(true);
            $table->string('referrer_phone')->nullable(); $table->string('referrer_type')->nullable();
            $table->unsignedBigInteger('referrer_id')->nullable(); $table->string('referrer_name')->nullable();
            $table->json('referral_commission_snapshot')->nullable();
            $table->string('done')->nullable(); $table->boolean('new_customer')->default(false);
            $table->decimal('amount', 15, 2)->default(0); $table->decimal('original_amount', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0); $table->decimal('wallet_applied', 15, 2)->default(0);
            $table->json('payment_details')->nullable(); $table->json('services')->nullable(); $table->timestamps();
        });
        $this->createPayrollTables();

        $this->doctor = Doctor::query()->create(['name' => 'دکتر انجام‌دهنده', 'new_customer_bonus' => 20, 'existing_customer_bonus' => 20]);
        $this->referrerDoctor = Doctor::query()->create(['name' => 'دکتر معرف', 'new_customer_bonus' => 20, 'existing_customer_bonus' => 20,
            'referral_commission_type' => 'percent', 'referral_commission_value' => 10]);
        $this->staff = Staff::query()->create(['name' => 'مشاور', 'new_customer_bonus' => 5, 'existing_customer_bonus' => 5]);
        Inventory::query()->create(['name' => 'لیزر', 'amount' => 1000000]);
    }

    private function visit(array $service = [], array $attributes = [], ?int $ledgerTotal = null): Appointment
    {
        $discount = max(0, (int) ($service['discount'] ?? 0));
        $isSurcharge = ($service['adjustment_mode'] ?? '') === 'surcharge';
        $catalogNet = $isSurcharge ? 1000000 + $discount : max(0, 1000000 - $discount);
        $ledger = $ledgerTotal ?? $catalogNet;
        $payment = array_key_exists('payment_details', $attributes)
            ? $attributes['payment_details']
            : ['ledger_total' => $ledger, 'cash' => $ledger];
        unset($attributes['payment_details']);

        return Appointment::query()->create([
            'month' => '1405-08', 'day_num' => 3, 'done' => 'انجام شد', 'lastname' => 'بیمار',
            'doctor' => $this->doctor->name, 'consultant' => $this->staff->name,
            'payment_details' => $payment,
            'services' => [['name' => 'لیزر', 'cc' => 1, 'doctor' => $this->doctor->name, 'consultant' => $this->staff->name, ...$service]],
            ...$attributes,
        ]);
    }

    private function earned(Appointment $visit, string $type, ?string $earningType = null, ?int $resourceId = null): float
    {
        return (float) ResourceEarningLine::query()->where('appointment_id', $visit->id)->where('resource_type', $type)
            ->when($earningType, fn ($query) => $query->where('earning_type', $earningType))
            ->when($resourceId, fn ($query) => $query->where('resource_id', $resourceId))
            ->where('status', 'active')->sum('amount');
    }

    private function resync(): void
    {
        app(AppointmentController::class)->resyncAllResourceEarnings();
    }

    public function test_discount_source_decides_who_pays_the_discount(): void
    {
        $shared = $this->visit(['discount' => 200000, 'adjustment_mode' => 'discount']);
        $clinic = $this->visit(['discount' => 200000, 'adjustment_mode' => 'discount', 'discount_source' => 'clinic']);
        $doctor = $this->visit(['discount' => 100000, 'adjustment_mode' => 'discount', 'discount_source' => 'doctor']);
        $tooLarge = $this->visit(['discount' => 500000, 'adjustment_mode' => 'discount', 'discount_source' => 'doctor']);

        $this->resync();

        // مشترک (پیش‌فرض و نوبت‌های قدیمی): ۲۰٪ از ۸۰۰ هزار.
        $this->assertSame(160000.0, $this->earned($shared, 'doctor'));
        // کلینیک: پزشک ۲۰٪ از مبلغ قبل از تخفیف.
        $this->assertSame(200000.0, $this->earned($clinic, 'doctor'));
        // پزشک: ۲۰۰ هزار سهم کامل منهای ۱۰۰ هزار تخفیف.
        $this->assertSame(100000.0, $this->earned($doctor, 'doctor'));
        // تخفیف بیشتر از سهم پزشک: سهم صفر می‌شود، نه منفی.
        $this->assertSame(0.0, $this->earned($tooLarge, 'doctor'));
        // پورسانت مشاور در همهٔ حالت‌ها مثل قبل از مبلغ بعد از تخفیف است.
        $this->assertSame(40000.0, $this->earned($shared, 'staff'));
        $this->assertSame(40000.0, $this->earned($clinic, 'staff'));
        $this->assertSame(45000.0, $this->earned($doctor, 'staff'));

        $line = ResourceEarningLine::query()->where('appointment_id', $doctor->id)->where('resource_type', 'doctor')->firstOrFail();
        $this->assertSame('doctor', $line->calculation_snapshot['discount_source']);
        $this->assertSame(200000, (int) $line->calculation_snapshot['commission_before_discount']);
        $this->assertSame(900000.0, (float) $line->net_amount, 'مبلغ پرداختی مشتری همان مبلغ بعد از تخفیف است.');
    }

    public function test_referrer_doctor_gets_a_separate_referral_commission_with_a_snapshot(): void
    {
        $visit = $this->visit(['discount' => 200000, 'adjustment_mode' => 'discount'], [
            'referrer_type' => 'doctor', 'referrer_id' => $this->referrerDoctor->id, 'referrer_name' => $this->referrerDoctor->name,
        ]);
        $this->resync();

        $this->assertSame(160000.0, $this->earned($visit, 'doctor', null, $this->doctor->id));
        $this->assertSame(80000.0, $this->earned($visit, 'doctor', ResourceEarningLine::REFERRAL_TYPE, $this->referrerDoctor->id));
        $this->assertSame(0.0, $this->earned($visit, 'doctor', 'inventory_default_commission', $this->referrerDoctor->id));

        // تغییر بعدی تنظیمات، پورسانت معرفیِ نوبتِ انجام‌شده را عوض نمی‌کند.
        $this->referrerDoctor->update(['referral_commission_value' => 50]);
        $this->resync();
        $this->assertSame(80000.0, $this->earned($visit, 'doctor', ResourceEarningLine::REFERRAL_TYPE));
        $this->assertSame(10, (int) $visit->fresh()->referral_commission_snapshot['value']);

        $summary = app(PayrollReportController::class)->show(Request::create('/', 'GET', [
            'resource_type' => 'doctor', 'resource_id' => $this->referrerDoctor->id, 'month' => '1405-08',
        ]))->getData(true)['summary'];
        $this->assertSame(80000.0, (float) $summary['referral_commission']);
        $this->assertSame(0.0, (float) $summary['total_commission']);
        $this->assertSame(80000.0, (float) $summary['total_earned']);
    }

    public function test_schedule_report_names_the_referrer_and_withholds_the_amount_until_payment_and_done(): void
    {
        $controller = app(AppointmentController::class);
        $referrer = [
            'referrer_type' => 'doctor',
            'referrer_id' => $this->referrerDoctor->id,
            'referrer_name' => $this->referrerDoctor->name,
        ];

        $this->assertNull($controller->scheduleReferrerCommission($this->visit()));

        $pending = $this->visit([], [...$referrer, 'done' => null]);
        $pendingSummary = $controller->scheduleReferrerCommission($pending);
        $this->assertSame('دکتر معرف', $pendingSummary['name']);
        $this->assertSame('پزشک', $pendingSummary['role']);
        $this->assertSame('percent', $pendingSummary['commission_type']);
        $this->assertSame(10.0, (float) $pendingSummary['commission_value']);
        $this->assertNull($pendingSummary['amount']);

        $unpaid = $this->visit([], [...$referrer, 'payment_details' => null]);
        $unpaidSummary = $controller->scheduleReferrerCommission($unpaid);
        $this->assertSame('دکتر معرف', $unpaidSummary['name']);
        $this->assertNull($unpaidSummary['amount']);

        $paid = $this->visit([], $referrer);
        $this->resync();
        $paidSummary = $controller->scheduleReferrerCommission($paid->fresh());
        $this->assertSame(100000, $paidSummary['amount']);
        $this->assertSame(10.0, (float) $paidSummary['commission_value']);
        $this->assertSame('percent', $paidSummary['commission_type']);

        $customer = $this->visit();
        $customer->forceFill([
            'referrer_type' => 'customer',
            'referrer_id' => 5,
            'referrer_name' => 'سارا محمدی',
            'referral_score' => 50000,
            'referral_commission_type' => 'percent',
            'referral_commission_value' => 8,
        ]);
        $customerSummary = $controller->scheduleReferrerCommission($customer);
        $this->assertSame('سارا محمدی', $customerSummary['name']);
        $this->assertSame('مشتری', $customerSummary['role']);
        $this->assertSame(50000, $customerSummary['amount']);
        $this->assertTrue($customerSummary['settled']);
        $this->assertSame(8.0, (float) $customerSummary['commission_value']);

        $open = $this->visit([], [
            'payment_details' => ['ledger_total' => 600000],
        ]);
        $open->forceFill([
            'amount' => 1000000,
            'debt' => 400000,
            'referrer_type' => 'customer',
            'referrer_id' => 5,
            'referrer_name' => 'علی موسوی',
            'referral_score' => 0,
            'referral_commission_type' => 'percent',
            'referral_commission_value' => 5,
        ]);
        $openSummary = $controller->scheduleReferrerCommission($open);
        $this->assertFalse($openSummary['settled']);
        $this->assertSame(0, $openSummary['amount']);

        $settled = $this->visit([], [
            'payment_details' => ['ledger_total' => 1000000],
        ]);
        $settled->forceFill([
            'amount' => 1000000,
            'debt' => 0,
            'referrer_type' => 'customer',
            'referrer_id' => 5,
            'referrer_name' => 'علی موسوی',
            'referral_score' => 0,
            'referral_commission_type' => 'percent',
            'referral_commission_value' => 5,
        ]);
        $settledSummary = $controller->scheduleReferrerCommission($settled);
        $this->assertTrue($settledSummary['settled']);
        $this->assertSame(50000, $settledSummary['amount']);
    }

    public function test_staff_referrer_with_fixed_amount_and_channel_exclusion(): void
    {
        $this->staff->update(['referral_commission_type' => 'fixed', 'referral_commission_value' => 150000]);
        $paid = $this->visit([], ['referrer_type' => 'staff', 'referrer_id' => $this->staff->id]);
        $excluded = $this->visit([], ['referrer_type' => 'staff', 'referrer_id' => $this->staff->id, 'referral_reward_enabled' => false]);
        $pending = $this->visit([], ['referrer_type' => 'staff', 'referrer_id' => $this->staff->id, 'done' => null]);
        $this->resync();

        $this->assertSame(150000.0, $this->earned($paid, 'staff', ResourceEarningLine::REFERRAL_TYPE));
        $this->assertSame(0.0, $this->earned($excluded, 'staff', ResourceEarningLine::REFERRAL_TYPE));
        $this->assertSame(0.0, $this->earned($pending, 'staff', ResourceEarningLine::REFERRAL_TYPE));
        // پورسانت انجام خدمت مشاور جداست.
        $this->assertSame(50000.0, $this->earned($paid, 'staff', 'base_commission'));
    }

    private function finalize(array $services, ?Appointment $previous, bool $canUnlock): array
    {
        $user = new class extends User {
            public bool $canUnlock = false;
            public function can($abilities, $arguments = []) { return $abilities === 'appointments.finalized_services_update' ? $this->canUnlock : true; }
        };
        $user->forceFill(['id' => 7, 'name' => 'پذیرش']);
        $user->canUnlock = $canUnlock;
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $user);
        $method = new \ReflectionMethod(AppointmentController::class, 'enforceServiceFinalization');

        return $method->invoke(app(AppointmentController::class), $services, $previous, $request);
    }

    public function test_finalized_service_is_locked_on_the_server(): void
    {
        $saved = $this->finalize([['name' => 'لیزر', 'cc' => 2, 'finalized' => true, 'finalized_by_name' => 'جعلی']], null, false);
        $this->assertTrue($saved[0]['finalized']);
        $this->assertSame('پذیرش', $saved[0]['finalized_by_name'], 'نام ثبت‌کننده را سرور می‌نویسد.');
        $this->assertNotEmpty($saved[0]['line_id']);
        $previous = $this->visit($saved[0]);
        $previous->update(['services' => $saved]);

        // بدون مجوز: تغییر تعداد، باز کردن قفل یا حذف خدمت رد می‌شود.
        foreach ([
            [[...$saved[0], 'cc' => 3]],
            [[...$saved[0], 'finalized' => false]],
            [],
        ] as $attempt) {
            try {
                $this->finalize($attempt, $previous->fresh(), false);
                $this->fail('ویرایش خدمت ثبت نهایی‌شده بدون مجوز نباید ذخیره شود.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        // ذخیرهٔ بدون تغییر (حتی از صفحه‌ای که شناسهٔ ردیف را ندارد) مشکلی ندارد و زمان ثبت حفظ می‌شود.
        $unchanged = $this->finalize([[...$saved[0], 'line_id' => null]], $previous->fresh(), false);
        $this->assertSame($saved[0]['line_id'], $unchanged[0]['line_id']);
        $this->assertSame($saved[0]['finalized_at'], $unchanged[0]['finalized_at']);

        // با مجوز: باز کردن قفل و ویرایش مجاز است.
        $unlocked = $this->finalize([[...$saved[0], 'finalized' => false, 'cc' => 3]], $previous->fresh(), true);
        $this->assertFalse($unlocked[0]['finalized']);
        $this->assertArrayNotHasKey('finalized_by_name', $unlocked[0]);
    }

    public function test_past_appointment_access_levels(): void
    {
        $user = fn (array $permissions) => tap(new class extends User {
            public array $granted = [];
            public function can($abilities, $arguments = []) { return in_array($abilities, $this->granted, true); }
        }, fn ($user) => $user->granted = $permissions);
        [$year, $month, $day] = explode('-', AppointmentPastAccess::today());
        $this->assertTrue(AppointmentPastAccess::isPast('1300-01', 1));
        $this->assertFalse(AppointmentPastAccess::isPast("{$year}-{$month}", (int) $day));
        $this->assertFalse(AppointmentPastAccess::isPast('1499-12', 1));

        $full = $user([AppointmentPastAccess::VIEW, AppointmentPastAccess::UPDATE]);
        $viewOnly = $user([AppointmentPastAccess::VIEW]);
        $none = $user([]);
        $this->assertTrue(AppointmentPastAccess::canEdit($full));
        $this->assertTrue(AppointmentPastAccess::canView($viewOnly));
        $this->assertFalse(AppointmentPastAccess::canEdit($viewOnly));
        $this->assertFalse(AppointmentPastAccess::canView($none));

        AppointmentPastAccess::assertCanEdit($viewOnly, '1499-12', 1);
        $this->expectException(HttpException::class);
        AppointmentPastAccess::assertCanEdit($viewOnly, '1300-01', 1);
    }

    public function test_role_without_past_view_only_receives_today_and_future(): void
    {
        [$year, $month, $day] = explode('-', AppointmentPastAccess::today());
        $old = $this->visit([], ['month' => '1300-01', 'day_num' => 1]);
        $current = $this->visit([], ['month' => "{$year}-{$month}", 'day_num' => (int) $day]);
        $future = $this->visit([], ['month' => '1499-12', 'day_num' => 1]);

        $ids = AppointmentPastAccess::constrainToCurrentAndFuture(Appointment::query())->pluck('id')->all();

        $this->assertNotContains($old->id, $ids);
        $this->assertContains($current->id, $ids);
        $this->assertContains($future->id, $ids);
    }

    public function test_doctor_referrer_type_is_kept_until_the_person_is_chosen(): void
    {
        $normalize = new \ReflectionMethod(AppointmentController::class, 'normalizeReferrer');

        $pending = $normalize->invoke(app(AppointmentController::class), [
            'referrer_type' => 'doctor',
            'referrer_id' => null,
        ], null);
        $this->assertSame('doctor', $pending['referrer_type']);
        $this->assertNull($pending['referrer_id']);

        $fromObject = $normalize->invoke(app(AppointmentController::class), [
            'referrer_type' => 'doctor',
            'referrer_id' => ['id' => $this->referrerDoctor->id, 'name' => $this->referrerDoctor->name],
        ], null);
        $this->assertSame('doctor', $fromObject['referrer_type']);
        $this->assertSame($this->referrerDoctor->id, $fromObject['referrer_id']);
        $this->assertSame('دکتر معرف', $fromObject['referrer_name']);
        $this->assertNull($fromObject['referrer_phone']);

        $staff = $normalize->invoke(app(AppointmentController::class), [
            'referrer_type' => 'staff',
            'referrer_id' => $this->staff->id,
        ], null);
        $this->assertSame('staff', $staff['referrer_type']);
        $this->assertSame($this->staff->id, $staff['referrer_id']);
        $this->assertSame('مشاور', $staff['referrer_name']);

        $unknown = $normalize->invoke(app(AppointmentController::class), [
            'referrer_type' => 'doctor',
            'referrer_id' => 99999,
        ], null);
        $this->assertNull($unknown['referrer_type']);
        $this->assertNull($unknown['referrer_id']);
    }

    public function test_customer_referrer_type_is_kept_until_the_person_is_chosen(): void
    {
        $normalize = new \ReflectionMethod(AppointmentController::class, 'normalizeReferrer');

        $pending = $normalize->invoke(app(AppointmentController::class), [
            'referrer_type' => 'customer',
            'referrer_id' => null,
            'referrer_phone' => '',
        ], null);
        $this->assertSame('customer', $pending['referrer_type']);
        $this->assertNull($pending['referrer_id']);
        $this->assertNull($pending['referrer_phone']);

        $cleared = $normalize->invoke(app(AppointmentController::class), [
            'referrer_type' => '',
            'referrer_id' => null,
            'referrer_phone' => '',
        ], null);
        $this->assertNull($cleared['referrer_type']);
        $this->assertNull($cleared['referrer_id']);
    }
}
