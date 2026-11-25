<?php

namespace Tests\Feature;

use App\Http\Controllers\AppointmentController;
use App\Models\Appointment;
use App\Models\AppointmentFinancialAllocation;
use App\Models\AppointmentFinancialTransaction;
use App\Models\Patient;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Reporting\DynamicReports\ReportPatientContext;
use App\Reporting\DynamicReports\Resolvers\FinancialFieldResolver;
use App\Services\FinancialCheckoutBalanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingDepositDetailsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('file_number')->nullable();
            $table->string('gender')->nullable();
            $table->decimal('wallet_balance', 15, 2)->default(0);
            $table->decimal('outstanding_debt', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('month')->nullable();
            $table->unsignedInteger('day_num')->nullable();
            $table->unsignedInteger('sort_order')->nullable();
            $table->string('time')->nullable();
            $table->string('lastname')->nullable();
            $table->string('file_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('done')->nullable();
            $table->string('amount')->nullable();
            $table->string('original_amount')->nullable();
            $table->string('debt')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_account')->nullable();
            $table->decimal('wallet_applied', 15, 2)->default(0);
            $table->decimal('referral_score', 15, 2)->default(0);
            $table->string('referral_commission_type')->nullable();
            $table->decimal('referral_commission_value', 15, 2)->default(0);
            $table->json('services')->nullable();
            $table->json('payment_details')->nullable();
            $table->boolean('new_customer')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
        });
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->string('type');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method')->nullable();
            $table->string('payment_account')->nullable();
            $table->string('description')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_key')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('reversed_transaction_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        Schema::create('wallet_transaction_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('credit_transaction_id');
            $table->unsignedBigInteger('debit_transaction_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
        Schema::create('appointment_financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('type');
            $table->integer('amount');
            $table->string('payment_method')->nullable();
            $table->string('payment_account')->nullable();
            $table->string('reference_number')->nullable();
            $table->date('due_date')->nullable();
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->unsignedBigInteger('settled_by')->nullable();
            $table->unsignedBigInteger('settlement_transaction_id')->nullable();
            $table->timestamps();
        });
        Schema::create('appointment_financial_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->string('service_key')->nullable();
            $table->string('section')->nullable();
            $table->string('subsection')->nullable();
            $table->string('service')->nullable();
            $table->string('parent_service')->nullable();
            $table->boolean('is_addon')->default(false);
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('gross_amount', 15, 0)->default(0);
            $table->decimal('discount_amount', 15, 0)->default(0);
            $table->decimal('allocated_amount', 15, 0);
            $table->timestamps();
        });
        Schema::create('appointment_balance_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();
        });
        Schema::create('resource_earning_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::create('referral_reward_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_transaction_id');
            $table->timestamps();
        });
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id')->nullable();
            $table->string('inventory_name')->nullable();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->string('type')->nullable();
            $table->string('source_key')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_financial_checkout_stores_deposit_details_and_credit_without_service(): void
    {
        $user = User::query()->create(['name' => 'ثبت‌کننده بیعانه', 'email' => 'deposit@example.com']);
        $patient = Patient::query()->create([
            'first_name' => 'بیمار', 'last_name' => 'بیعانه', 'phone' => '09121112233',
            'file_number' => 'DEP-CREDIT-1', 'gender' => 'زن',
        ]);
        $appointment = Appointment::query()->create([
            'month' => '1405-10', 'day_num' => 12, 'sort_order' => 1, 'time' => '10:00',
            'lastname' => 'بیعانه', 'phone' => $patient->phone, 'file_number' => $patient->file_number,
            'services' => [], 'amount' => 0, 'original_amount' => 0, 'done' => '',
        ]);

        $request = Request::create("/api/appointments/{$appointment->id}/financial-checkout", 'POST', [
            'payments' => [],
            'debt_allocations' => [],
            'deposit_allocations' => [],
            'service_lines' => [],
            'deposit' => [
                'credit_amount' => 750000,
                'method' => 'کارت به کارت',
                'account' => 'حساب اصلی',
                'occurred_at' => '2026-10-01',
            ],
        ]);
        $request->setUserResolver(fn () => $user);

        $response = app(AppointmentController::class)->financialCheckout(
            $request,
            $appointment,
            app(FinancialCheckoutBalanceService::class)
        );

        $this->assertSame(750000, (int) $response->getData(true)['wallet_balance']);

        $ledger = AppointmentFinancialTransaction::query()->where('type', 'deposit')->first();
        $this->assertNotNull($ledger);
        $this->assertSame(750000, (int) $ledger->amount);
        $this->assertSame('کارت به کارت', $ledger->payment_method);
        $this->assertSame('حساب اصلی', $ledger->payment_account);
        $this->assertTrue($ledger->occurred_at->isSameDay('2026-10-01'));
        $this->assertSame($user->id, (int) $ledger->created_by);
        $this->assertSame(0, AppointmentFinancialAllocation::query()->where('transaction_id', $ledger->id)->count());

        $wallet = WalletTransaction::query()->where('source_type', 'booking_deposit')->first();
        $this->assertNotNull($wallet);
        $this->assertSame(750000, (int) $wallet->amount);
        $this->assertSame('کارت به کارت', $wallet->payment_method);
        $this->assertSame('حساب اصلی', $wallet->payment_account);
        $this->assertTrue($wallet->occurred_at->isSameDay('2026-10-01'));
        $this->assertTrue((bool) data_get($wallet->metadata, 'credit_only'));
        $this->assertSame([], data_get($wallet->metadata, 'services'));

        $context = new ReportPatientContext($patient->fresh(), $appointment->fresh(), collect([$appointment]), collect(), 'silver');
        $fields = app(FinancialFieldResolver::class);
        $this->assertSame('0', $fields->resolve('income', $context));
        $this->assertSame('750٬000', $fields->resolve('deposit', $context));
        $this->assertSame('حساب اصلی', $fields->resolve('account', $context));
        $this->assertSame('کارت به کارت', $fields->resolve('payment', $context));
    }

    public function test_deposit_requires_method_and_account(): void
    {
        $user = User::query()->create(['name' => 'کاربر نوبت', 'email' => 'missing@example.com']);
        $patient = Patient::query()->create([
            'first_name' => 'بیمار', 'last_name' => 'ناقص', 'phone' => '09123334455',
            'file_number' => 'DEP-MISS-1', 'gender' => 'مرد',
        ]);
        $appointment = Appointment::query()->create([
            'month' => '1405-10', 'day_num' => 13, 'sort_order' => 1, 'time' => '11:00',
            'lastname' => 'ناقص', 'phone' => $patient->phone, 'file_number' => $patient->file_number,
            'services' => [], 'amount' => 0, 'original_amount' => 0,
        ]);

        $request = Request::create("/api/appointments/{$appointment->id}/financial-checkout", 'POST', [
            'deposit' => ['credit_amount' => 100000, 'method' => 'نقد', 'account' => ''],
        ]);
        $request->setUserResolver(fn () => $user);

        try {
            app(AppointmentController::class)->financialCheckout(
                $request,
                $appointment,
                app(FinancialCheckoutBalanceService::class)
            );
            $this->fail('بیعانه بدون حساب مقصد نباید ثبت شود.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame(0, WalletTransaction::query()->count());
        $this->assertSame(0, AppointmentFinancialTransaction::query()->count());
    }
}
