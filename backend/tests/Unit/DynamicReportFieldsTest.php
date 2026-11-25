<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Patient;
use App\Reporting\DynamicReports\DynamicReportFieldRegistry;
use App\Reporting\DynamicReports\ReportPatientContext;
use App\Reporting\DynamicReports\Resolvers\ReportFieldResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DynamicReportFieldsTest extends TestCase
{
    use DynamicReportPayrollWalletFixtures;

    public function test_it_maps_selected_patient_and_financial_fields(): void
    {
        $patient = new Patient([
            'first_name' => 'سارا',
            'last_name' => 'محمدی',
            'gender' => 'female',
            'phone' => '09121234567',
            'national_id' => '0012345678',
            'file_number' => '1042',
            'city' => 'تهران',
            'financial_status' => 'بدهکار',
        ]);
        $appointment = new Appointment([
            'payment_method' => 'کارت‌خوان',
            'month' => '1405-07',
            'day_num' => 12,
            'created_at' => Carbon::create(2024, 3, 20),
        ]);
        $context = new ReportPatientContext($patient, $appointment, collect([$appointment]), collect(), 'gold');
        $fields = app(DynamicReportFieldRegistry::class);

        $this->assertSame('سارا', $fields->resolve('name', $context));
        $this->assertSame('محمدی', $fields->resolve('family', $context));
        $this->assertSame('بدهکار', $fields->resolve('finstatus', $context));
        $this->assertSame('طلایی', $fields->resolve('custseg', $context));
        $this->assertSame('زن', $fields->resolve('gender', $context));
        $this->assertSame('09121234567', $fields->resolve('phone', $context));
        $this->assertSame('0012345678', $fields->resolve('nationalId', $context));
        $this->assertSame('1042', $fields->resolve('fileNo', $context));
        $this->assertSame('تهران', $fields->resolve('city', $context));
        $this->assertSame('کارت‌خوان', $fields->resolve('payment', $context));
        $this->assertSame('1405/07/12', $fields->resolve('appointmentDate', $context));
        $this->assertSame('1403/01/01', $fields->resolve('appointmentCreatedDate', $context));
    }

    public function test_every_builder_field_is_whitelisted(): void
    {
        $fields = app(DynamicReportFieldRegistry::class);
        $this->assertCount(39, $fields->keys());
        $this->assertSame('درآمد', $fields->labels()['income']);
        $this->assertSame('هزینه مواد', $fields->labels()['expense']);
        $this->assertSame($fields->keys(), array_keys($fields->labels()));

        foreach (config('dynamic_reports.field_resolvers') as $resolverClass) {
            $this->assertInstanceOf(ReportFieldResolver::class, app($resolverClass));
        }
    }

    public function test_commission_fields_read_the_same_active_payroll_lines_as_settlement(): void
    {
        $this->createPayrollAndWalletTables();
        $appointment = (new Appointment())->forceFill(['id' => 7, 'month' => '1405-07', 'day_num' => 10]);
        $this->seedCommissionLines(7, 8);
        $context = new ReportPatientContext(new Patient(), $appointment, collect([$appointment]), collect(), 'silver');
        $fields = app(DynamicReportFieldRegistry::class);

        // همان فرمول صفحهٔ تسویه: base + قانون انبار + پیش‌فرض انبار، فقط ردیف‌های فعال، بدون پاداش پلکانی.
        $this->assertSame(350000.0, $fields->resolve('doctorCommission', $context));
        $this->assertSame(80000.0, $fields->resolve('consultantCommission', $context));
        $payrollDoctor = (float) DB::table('resource_earning_lines')->where('resource_type', 'doctor')->where('status', 'active')
            ->where('appointment_id', 7)->whereIn('earning_type', \App\Models\ResourceEarningLine::COMMISSION_TYPES)->sum('amount');
        $this->assertSame($payrollDoctor, $fields->resolve('doctorCommission', $context));

        $withoutAppointment = new ReportPatientContext(new Patient(), null, collect(), collect(), 'silver');
        $this->assertNull($fields->resolve('doctorCommission', $withoutAppointment));
    }

    public function test_doctor_and_consultant_columns_fall_back_to_service_line_names(): void
    {
        $fields = app(DynamicReportFieldRegistry::class);

        $serviceOnly = new Appointment([
            'doctor' => null,
            'consultant' => null,
            'services' => [
                ['name' => 'الانسه', 'doctor' => 'دکتر حمیدی', 'consultant' => 'سارا'],
                ['name' => 'بوتاکس', 'doctor' => 'دکتر حمیدی', 'consultant' => 'لیلا'],
            ],
        ]);
        $serviceOnlyContext = new ReportPatientContext(new Patient(), $serviceOnly, collect([$serviceOnly]), collect(), 'silver');
        $this->assertSame('دکتر حمیدی', $fields->resolve('doctor', $serviceOnlyContext));
        $this->assertSame('سارا، لیلا', $fields->resolve('consultant', $serviceOnlyContext));

        $appointmentLevel = new Appointment([
            'doctor' => 'دکتر رمضانی',
            'consultant' => 'سارا',
            'services' => [['name' => 'ویزیت', 'doctor' => 'دکتر حمیدی', 'consultant' => 'لیلا']],
        ]);
        $appointmentLevelContext = new ReportPatientContext(new Patient(), $appointmentLevel, collect([$appointmentLevel]), collect(), 'silver');
        $this->assertSame('دکتر رمضانی، دکتر حمیدی', $fields->resolve('doctor', $appointmentLevelContext));
        $this->assertSame('سارا، لیلا', $fields->resolve('consultant', $appointmentLevelContext));

        $empty = new Appointment(['doctor' => null, 'consultant' => '', 'services' => [['name' => 'ویزیت']]]);
        $emptyContext = new ReportPatientContext(new Patient(), $empty, collect([$empty]), collect(), 'silver');
        $this->assertNull($fields->resolve('doctor', $emptyContext));
        $this->assertNull($fields->resolve('consultant', $emptyContext));
    }

    public function test_wallet_fields_sum_deposits_and_withdrawals_inside_the_report_range(): void
    {
        $this->createPayrollAndWalletTables();
        $this->seedWalletTransactions(1, 2);
        $patient = (new Patient())->forceFill(['id' => 1]);
        $patient->exists = true;
        $range = ['from' => '1405-07-01', 'to' => '1405-07-30'];
        $context = new ReportPatientContext($patient, null, collect(), collect(), 'silver', $range);
        $fields = app(DynamicReportFieldRegistry::class);

        $this->assertSame(400000.0, $fields->resolve('walletDeposit', $context));
        $this->assertSame(300000.0, $fields->resolve('walletWithdraw', $context));
        $wholeHistory = new ReportPatientContext($patient, null, collect(), collect(), 'silver');
        $this->assertSame(900000.0, $fields->resolve('walletDeposit', $wholeHistory));

        // مراجع نوبت‌دهی که پرونده ندارد کیف پول ندارد.
        $this->assertNull($fields->resolve('walletDeposit', new ReportPatientContext(new Patient(), null, collect(), collect(), 'silver', $range)));
    }

    public function test_wallet_deposit_uses_occurred_at_not_created_at_alone(): void
    {
        $this->createPayrollAndWalletTables();
        DB::table('wallet_transactions')->insert([
            'patient_id' => 1,
            'type' => 'deposit',
            'amount' => 250000,
            'payment_account' => 'صندوق ۱',
            'source_type' => 'booking_deposit',
            // ذخیره امروز، اما تاریخ واقعی بیعانه داخل بازه گزارش است.
            'created_at' => '2026-11-01 10:00:00',
            'occurred_at' => '2026-10-05 12:00:00',
        ]);
        DB::table('wallet_transactions')->insert([
            'patient_id' => 1,
            'type' => 'deposit',
            'amount' => 180000,
            'payment_account' => 'صندوق ۲',
            'source_type' => 'booking_deposit',
            'created_at' => '2026-10-05 10:00:00',
            'occurred_at' => '2026-11-01 12:00:00',
        ]);

        $patient = (new Patient())->forceFill(['id' => 1]);
        $patient->exists = true;
        $range = ['from' => '1405-07-01', 'to' => '1405-07-30'];
        $fields = app(DynamicReportFieldRegistry::class);
        $context = new ReportPatientContext($patient, null, collect(), collect(), 'silver', $range);

        $this->assertSame(250000.0, $fields->resolve('walletDeposit', $context));
        $this->assertSame(250000.0, \App\Reporting\DynamicReports\ReportWalletTotals::forPatient(
            1,
            'deposit',
            ['reportDate' => $range, 'multi' => ['account' => ['صندوق ۱']]]
        ));
        $this->assertSame(0.0, \App\Reporting\DynamicReports\ReportWalletTotals::forPatient(
            1,
            'deposit',
            ['reportDate' => $range, 'multi' => ['account' => ['صندوق ۲']]]
        ));
    }

    public function test_new_money_columns_are_formatted_like_other_amounts(): void
    {
        $this->createPayrollAndWalletTables();
        $this->seedCommissionLines(7, 8);
        $appointment = (new Appointment())->forceFill(['id' => 7]);
        $context = new ReportPatientContext(new Patient(), $appointment, collect([$appointment]), collect(), 'silver');

        $row = app(\App\Reporting\DynamicReports\DynamicReportRowBuilder::class)->build(['doctorCommission', 'consultantCommission', 'walletDeposit'], $context);
        $this->assertSame(['doctorCommission' => '350,000', 'consultantCommission' => '80,000', 'walletDeposit' => '—'], $row);
    }

    public function test_income_and_material_expense_are_separate_money_columns(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 14, 2)->default(0);
        });
        Schema::create('inventory_addon_definitions', function (Blueprint $table) {
            $table->id();
            $table->decimal('price', 14, 2)->default(0);
        });
        DB::table('inventories')->insert(['name' => 'بوتاکس', 'price' => 1800000]);

        $appointment = new Appointment([
            'amount' => 4050000,
            'wallet_applied' => 500000,
            'services' => [['name' => 'بوتاکس', 'quantity' => 1, 'cc' => 1]],
        ]);
        $context = new ReportPatientContext(new Patient(), $appointment, collect([$appointment]), collect(), 'silver');
        $fields = app(DynamicReportFieldRegistry::class);

        $this->assertSame('4٬550٬000', $fields->resolve('income', $context));
        $this->assertSame('1٬800٬000', $fields->resolve('expense', $context));

        $row = app(\App\Reporting\DynamicReports\DynamicReportRowBuilder::class)->build(['income', 'expense'], $context);
        $this->assertSame(['income' => '4,550,000', 'expense' => '1,800,000'], $row);
    }
}
