<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentFinancialTransaction;
use App\Services\CustomerTypeResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** مشتری قدیم فقط با نوبت قبلیِ دارای خدمت و پرداخت ثبت‌شده؛ نوبت بدون پرداخت همچنان جدید است. */
class CustomerTypeResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('month')->nullable();
            $table->unsignedInteger('day_num')->nullable();
            $table->string('lastname')->nullable();
            $table->string('phone')->nullable();
            $table->string('file_number')->nullable();
            $table->string('time')->nullable();
            $table->boolean('new_customer')->default(false);
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('wallet_applied', 15, 2)->default(0);
            $table->json('services')->nullable();
            $table->json('payment_details')->nullable();
            $table->timestamps();
        });

        Schema::create('appointment_financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('type');
            $table->integer('amount')->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_account')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function test_customer_stays_new_when_prior_appointment_has_no_payment(): void
    {
        Appointment::query()->create([
            'month' => '1405-07', 'day_num' => 10, 'lastname' => 'بیمار', 'phone' => '09121111111',
            'file_number' => 'F-1', 'time' => '10:00', 'new_customer' => true, 'amount' => 1000000,
            'services' => [['name' => 'بوتاکس', 'cc' => 1]],
            'payment_details' => ['ledger_total' => 0, 'cash' => 0, 'card' => 0],
        ]);

        $this->assertTrue(app(CustomerTypeResolver::class)->isNewCustomer('F-1', '09121111111', null, '1405-08', 1));
    }

    public function test_customer_stays_new_when_prior_payment_has_no_service(): void
    {
        $prior = Appointment::query()->create([
            'month' => '1405-07', 'day_num' => 10, 'lastname' => 'بیمار', 'phone' => '09121111112',
            'file_number' => 'F-2', 'time' => '10:00', 'new_customer' => true, 'amount' => 500000,
            'services' => [], 'wallet_applied' => 0,
            'payment_details' => ['ledger_total' => 500000],
        ]);
        AppointmentFinancialTransaction::query()->create([
            'appointment_id' => $prior->id, 'type' => 'payment', 'amount' => 500000,
            'payment_method' => 'نقد', 'occurred_at' => now(),
        ]);

        $resolver = app(CustomerTypeResolver::class);
        $this->assertTrue($resolver->isNewCustomer('F-2', '09121111112', null, '1405-08', 1));
        $this->assertFalse($resolver->appointmentHasPaidService($prior->fresh()));
    }

    public function test_customer_becomes_old_after_prior_paid_service(): void
    {
        $prior = Appointment::query()->create([
            'month' => '1405-07', 'day_num' => 10, 'lastname' => 'بیمار', 'phone' => '09121111113',
            'file_number' => 'F-3', 'time' => '10:00', 'new_customer' => true, 'amount' => 800000,
            'services' => [['name' => 'فیلر', 'cc' => 1]],
            'payment_details' => ['ledger_total' => 800000],
        ]);
        AppointmentFinancialTransaction::query()->create([
            'appointment_id' => $prior->id, 'type' => 'payment', 'amount' => 800000,
            'payment_method' => 'کارتخوان', 'occurred_at' => now(),
        ]);

        $resolver = app(CustomerTypeResolver::class);
        $this->assertFalse($resolver->isNewCustomer('F-3', '09121111113', null, '1405-08', 5));
        $this->assertTrue($resolver->appointmentHasPaidService($prior->fresh()));
    }

    public function test_deposit_alone_does_not_make_customer_old(): void
    {
        $prior = Appointment::query()->create([
            'month' => '1405-07', 'day_num' => 12, 'lastname' => 'بیمار', 'phone' => '09121111114',
            'file_number' => 'F-4', 'time' => '11:00', 'new_customer' => true, 'amount' => 0,
            'services' => [['name' => 'مشاوره', 'cc' => 1]],
            'payment_details' => ['ledger_total' => 0],
        ]);
        AppointmentFinancialTransaction::query()->create([
            'appointment_id' => $prior->id, 'type' => 'deposit', 'amount' => 200000,
            'payment_method' => 'نقد', 'occurred_at' => now(),
        ]);

        $this->assertTrue(app(CustomerTypeResolver::class)->isNewCustomer('F-4', '09121111114', null, '1405-08', 1));
    }

    public function test_legacy_cash_card_payment_with_service_counts_as_old(): void
    {
        Appointment::query()->create([
            'month' => '1405-05', 'day_num' => 2, 'lastname' => 'بیمار', 'phone' => '09121111115',
            'file_number' => 'F-5', 'time' => '10:00', 'new_customer' => true, 'amount' => 900000,
            'services' => [['name' => 'ژل', 'cc' => 1]],
            'payment_details' => ['cash' => 400000, 'card' => 500000],
        ]);

        $this->assertFalse(app(CustomerTypeResolver::class)->isNewCustomer('F-5', '09121111115', null, '1405-08', 1));
    }

    public function test_reclassify_flips_later_appointments_after_paid_service(): void
    {
        $prior = Appointment::query()->create([
            'month' => '1405-07', 'day_num' => 1, 'lastname' => 'بیمار', 'phone' => '09121111116',
            'file_number' => 'F-6', 'time' => '10:00', 'new_customer' => true, 'amount' => 700000,
            'services' => [['name' => 'بوتاکس', 'cc' => 1]],
            'payment_details' => ['ledger_total' => 700000],
        ]);
        $later = Appointment::query()->create([
            'month' => '1405-08', 'day_num' => 1, 'lastname' => 'بیمار', 'phone' => '09121111116',
            'file_number' => 'F-6', 'time' => '11:00', 'new_customer' => true, 'amount' => 0,
            'services' => [['name' => 'مشاوره', 'cc' => 1]],
        ]);
        AppointmentFinancialTransaction::query()->create([
            'appointment_id' => $prior->id, 'type' => 'payment', 'amount' => 700000,
            'payment_method' => 'نقد', 'occurred_at' => now(),
        ]);

        $changed = app(CustomerTypeResolver::class)->reclassifyPatientAppointments('F-6', '09121111116');
        $this->assertTrue($changed->contains(fn (Appointment $row) => (int) $row->id === (int) $later->id));
        $this->assertTrue((bool) $prior->fresh()->new_customer, 'اولین نوبت پرداخت‌شده همچنان جدید است');
        $this->assertFalse((bool) $later->fresh()->new_customer, 'نوبت بعدی باید قدیم شود');
    }
}
