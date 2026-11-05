<?php

namespace Tests\Feature;

use App\Http\Controllers\AppointmentController;
use App\Models\Appointment;
use App\Models\AppointmentFinancialTransaction;
use App\Models\Patient;
use App\Models\WalletTransaction;
use App\Services\ClinicReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\Concerns\CreatesPayrollTables;
use Tests\TestCase;

/** کیف پول بیمار: خواندن مبلغ اعشاری، نوبت بدون پرونده و برگشت پول هنگام حذف نوبت. */
class WalletRefundTest extends TestCase
{
    use CreatesPayrollTables;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('appointments', function (Blueprint $table) {
            $table->id(); $table->string('month')->nullable(); $table->unsignedInteger('day_num')->nullable();
            $table->string('lastname')->nullable(); $table->string('file_number')->nullable(); $table->string('phone')->nullable();
            $table->string('doctor')->nullable(); $table->string('consultant')->nullable(); $table->string('done')->nullable();
            $table->string('amount')->nullable(); $table->string('debt')->nullable(); $table->decimal('wallet_applied', 15, 2)->default(0);
            $table->json('services')->nullable(); $table->json('payment_details')->nullable();
            $table->unsignedInteger('lock_version')->default(1); $table->timestamps();
        });
        Schema::create('patients', function (Blueprint $table) {
            $table->id(); $table->string('first_name')->nullable(); $table->string('last_name')->nullable();
            $table->string('phone')->nullable(); $table->string('file_number')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('patient_id'); $table->string('type'); $table->decimal('amount', 15, 2);
            $table->string('description')->nullable(); $table->string('source_type')->nullable(); $table->string('source_key')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable(); $table->unsignedBigInteger('reversed_transaction_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); $table->json('metadata')->nullable();
            $table->timestamp('reversed_at')->nullable(); $table->timestamp('expires_at')->nullable(); $table->timestamps();
        });
        Schema::create('appointment_financial_transactions', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('appointment_id'); $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); $table->string('type'); $table->integer('amount');
            $table->string('payment_method')->nullable(); $table->string('payment_account')->nullable(); $table->string('reference_number')->nullable();
            $table->date('due_date')->nullable(); $table->string('reason')->nullable(); $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable(); $table->timestamp('voided_at')->nullable(); $table->unsignedBigInteger('voided_by')->nullable();
            $table->timestamp('settled_at')->nullable(); $table->unsignedBigInteger('settled_by')->nullable();
            $table->unsignedBigInteger('settlement_transaction_id')->nullable(); $table->timestamps();
        });
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('inventory_id')->nullable(); $table->string('inventory_name')->nullable();
            $table->decimal('quantity', 12, 3)->default(0); $table->string('type')->nullable(); $table->string('source_key')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable(); $table->string('description')->nullable();
            $table->timestamp('occurred_at')->nullable(); $table->timestamps();
        });
        $this->createPayrollTables();
    }

    private function invokePrivate(object $target, string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionClass($target))->getMethod($method)->invoke($target, ...$arguments);
    }

    public function test_decimal_wallet_amount_is_not_read_as_a_hundred_times_larger(): void
    {
        $controller = app(AppointmentController::class);

        $this->assertSame(2000000, $this->invokePrivate($controller, 'signedMoneyToInteger', '2000000.00'));
        $this->assertSame(2000000, $this->invokePrivate($controller, 'signedMoneyToInteger', '2,000,000'));
        $this->assertSame(2000000, $this->invokePrivate($controller, 'signedMoneyToInteger', '۲٬۰۰۰٬۰۰۰'));
        $this->assertSame(-1500, $this->invokePrivate($controller, 'signedMoneyToInteger', '-1500.40'));
    }

    public function test_appointment_without_file_number_or_phone_belongs_to_no_patient(): void
    {
        Patient::query()->create(['first_name' => 'اولین', 'last_name' => 'بیمار', 'file_number' => '1', 'phone' => '09120000001']);

        $patient = $this->invokePrivate(app(AppointmentController::class), 'appointmentPatient', new Appointment(['file_number' => '', 'phone' => '']));

        $this->assertNull($patient);
    }

    public function test_deleting_an_appointment_returns_every_payment_to_the_patient_wallet(): void
    {
        $patient = Patient::query()->create(['first_name' => 'بیمار', 'last_name' => 'کیف پول', 'file_number' => 'W-1']);
        WalletTransaction::query()->create(['patient_id' => $patient->id, 'type' => 'deposit', 'amount' => 5000000, 'source_type' => 'manual']);
        $visit = Appointment::query()->create(['month' => '1405-08', 'day_num' => 5, 'file_number' => 'W-1', 'done' => 'انجام شد', 'amount' => '4500000', 'debt' => '0', 'services' => []]);
        $walletPayment = WalletTransaction::query()->create(['patient_id' => $patient->id, 'type' => 'withdraw', 'amount' => 2000000, 'source_type' => 'appointment_financial_payment', 'appointment_id' => $visit->id]);
        $walletDebt = WalletTransaction::query()->create(['patient_id' => $patient->id, 'type' => 'withdraw', 'amount' => 1000000, 'source_type' => 'debt_settlement', 'appointment_id' => $visit->id]);
        foreach ([['payment', 2000000, 'بیعانه / کیف پول'], ['payment', 1500000, 'کارتخوان'], ['debt_settlement', 1000000, 'بیعانه / کیف پول']] as [$type, $amount, $method]) {
            AppointmentFinancialTransaction::query()->create(['appointment_id' => $visit->id, 'patient_id' => $patient->id, 'type' => $type, 'amount' => $amount, 'payment_method' => $method, 'occurred_at' => now()]);
        }
        $this->assertSame(2000000.0, (float) $patient->fresh()->wallet_balance);

        $this->assertSame(4500000.0, $this->invokePrivate(app(ClinicReportService::class), 'receivedAmount', $visit, $this->invokePrivate(app(ClinicReportService::class), 'ledgerReceipts', [$visit->id])));

        app(AppointmentController::class)->destroy(Request::create('/', 'DELETE', ['lock_version' => 1]), $visit->fresh());

        $this->assertSame(6500000.0, (float) $patient->fresh()->wallet_balance);
        $this->assertNotNull($walletPayment->fresh()->reversed_at);
        $this->assertNotNull($walletDebt->fresh()->reversed_at);
        $refund = WalletTransaction::query()->where('source_type', 'appointment_refund')->firstOrFail();
        $this->assertSame(1500000.0, (float) $refund->amount);
        $this->assertCount(3, $refund->metadata['payments']);
    }

    public function test_deleting_a_paid_appointment_without_a_patient_is_refused(): void
    {
        $visit = Appointment::query()->create(['month' => '1405-08', 'day_num' => 5, 'done' => 'انجام شد', 'amount' => '900000', 'debt' => '0', 'services' => []]);
        AppointmentFinancialTransaction::query()->create(['appointment_id' => $visit->id, 'type' => 'payment', 'amount' => 900000, 'payment_method' => 'نقد', 'occurred_at' => now()]);

        try {
            app(AppointmentController::class)->destroy(Request::create('/', 'DELETE', ['lock_version' => 1]), $visit->fresh());
            $this->fail('حذف نوبت پرداخت‌شده بدون پرونده نباید انجام شود.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertNotNull($visit->fresh());
        $this->assertSame(0, WalletTransaction::query()->count());
    }
}
