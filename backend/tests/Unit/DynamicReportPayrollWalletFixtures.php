<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ردیف‌های دفتر حقوق و کیف پول برای ستون‌های «پورسانت پزشک/مشاور» و «واریز/برداشت کیف پول».
 * بازهٔ گزارش آزمون ۱۴۰۵/۰۷/۰۱ تا ۱۴۰۵/۰۷/۳۰ است، یعنی ۲۰۲۶-۰۹-۲۳ تا ۲۰۲۶-۱۰-۲۲.
 */
trait DynamicReportPayrollWalletFixtures
{
    protected function createPayrollAndWalletTables(): void
    {
        Schema::create('resource_earning_lines', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('appointment_id')->nullable(); $table->string('month')->nullable();
            $table->string('resource_type'); $table->unsignedBigInteger('resource_id')->nullable(); $table->string('resource_name')->nullable();
            $table->string('earning_type'); $table->decimal('amount', 14, 2)->default(0); $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('patient_id'); $table->string('type'); $table->decimal('amount', 14, 2);
            $table->string('source_type')->nullable(); $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('reversed_transaction_id')->nullable(); $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });
    }

    /** پزشک ۳۵۰٬۰۰۰ و مشاور ۸۰٬۰۰۰؛ ردیف حذف‌شده، پاداش پلکانی و نوبت دیگر حساب نمی‌شوند. */
    protected function seedCommissionLines(int $appointmentId, int $otherAppointmentId): void
    {
        $line = fn (int $appointment, string $resource, string $type, int $amount, string $status = 'active') => [
            'appointment_id' => $appointment, 'month' => '1405-07', 'resource_type' => $resource,
            'resource_name' => $resource === 'doctor' ? 'دکتر صدری' : 'زهره', 'earning_type' => $type, 'amount' => $amount, 'status' => $status,
        ];
        DB::table('resource_earning_lines')->insert([
            $line($appointmentId, 'doctor', 'base_commission', 100000),
            $line($appointmentId, 'doctor', 'inventory_default_commission', 250000),
            $line($appointmentId, 'doctor', 'inventory_commission', 50000, 'deleted'),
            $line($appointmentId, 'doctor', 'sales_bonus', 999999),
            $line($appointmentId, 'staff', 'inventory_commission', 80000),
            $line($otherAppointmentId, 'doctor', 'base_commission', 777000),
        ]);
    }

    /** واریز خالص ۴۰۰٬۰۰۰ و برداشت ۳۰۰٬۰۰۰ در بازه؛ برگشت‌ها، بیرون بازه و بیمار دیگر حساب نمی‌شوند. */
    protected function seedWalletTransactions(int $patientId, int $otherPatientId): void
    {
        $insert = fn (array $row) => DB::table('wallet_transactions')->insertGetId($row + ['patient_id' => $patientId]);
        // واریز ۱٬۰۰۰٬۰۰۰ که ۶۰۰٬۰۰۰ از آن برگشت خورده است.
        $deposit = $insert(['type' => 'deposit', 'amount' => 1000000, 'source_type' => 'manual', 'created_at' => '2026-10-01 10:00:00', 'reversed_at' => '2026-10-05 10:00:00']);
        $reversal = $insert(['type' => 'withdraw', 'amount' => 600000, 'source_type' => 'reversal', 'reversed_transaction_id' => $deposit, 'created_at' => '2026-10-05 10:00:00']);
        DB::table('wallet_transactions')->where('id', $deposit)->update(['reversed_transaction_id' => $reversal]);
        // پیش از بازه.
        $insert(['type' => 'deposit', 'amount' => 500000, 'source_type' => 'referral_reward', 'created_at' => '2026-09-22 23:59:00']);
        // پرداخت نوبت از کیف پول، روز آخر بازه.
        $insert(['type' => 'withdraw', 'amount' => 300000, 'source_type' => 'appointment_payment', 'created_at' => '2026-10-22 21:00:00']);
        // برداشتی که کامل برگشت خورده است.
        $withdraw = $insert(['type' => 'withdraw', 'amount' => 200000, 'source_type' => 'appointment_payment', 'created_at' => '2026-10-10 10:00:00', 'reversed_at' => '2026-10-11 10:00:00']);
        $back = $insert(['type' => 'deposit', 'amount' => 200000, 'source_type' => 'reversal', 'reversed_transaction_id' => $withdraw, 'created_at' => '2026-10-11 10:00:00']);
        DB::table('wallet_transactions')->where('id', $withdraw)->update(['reversed_transaction_id' => $back]);
        DB::table('wallet_transactions')->insert(['patient_id' => $otherPatientId, 'type' => 'deposit', 'amount' => 900000, 'source_type' => 'manual', 'created_at' => '2026-10-02 10:00:00']);
    }
}
