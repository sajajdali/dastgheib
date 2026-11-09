<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Patient;
use App\Reporting\DynamicReports\DynamicReportFieldRegistry;
use App\Reporting\DynamicReports\ReportPatientContext;
use App\Reporting\DynamicReports\Resolvers\ReportFieldResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        $this->assertCount(38, $fields->keys());
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

    public function test_new_money_columns_are_formatted_like_other_amounts(): void
    {
        $this->createPayrollAndWalletTables();
        $this->seedCommissionLines(7, 8);
        $appointment = (new Appointment())->forceFill(['id' => 7]);
        $context = new ReportPatientContext(new Patient(), $appointment, collect([$appointment]), collect(), 'silver');

        $row = app(\App\Reporting\DynamicReports\DynamicReportRowBuilder::class)->build(['doctorCommission', 'consultantCommission', 'walletDeposit'], $context);
        $this->assertSame(['doctorCommission' => '350,000', 'consultantCommission' => '80,000', 'walletDeposit' => '—'], $row);
    }
}
