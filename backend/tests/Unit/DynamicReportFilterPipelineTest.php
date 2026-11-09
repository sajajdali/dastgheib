<?php

namespace Tests\Unit;

use App\Reporting\DynamicReports\DynamicReportFilterPipeline;
use App\Reporting\DynamicReports\Filters\DynamicReportQueryFilter;
use App\Reporting\DynamicReports\JalaliMonthWindow;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DynamicReportFilterPipelineTest extends TestCase
{
    public function test_every_registered_filter_implements_the_shared_contract(): void
    {
        foreach (config('dynamic_reports.query_filters') as $filterClass) {
            $this->assertInstanceOf(DynamicReportQueryFilter::class, app($filterClass));
        }
    }

    public function test_pipeline_applies_all_current_database_filters(): void
    {
        $query = app(DynamicReportFilterPipeline::class)->query([
            'values' => [
                'name' => 'سارا', 'family' => 'محمدی', 'gender' => 'زن',
                'phone' => '0912', 'nationalId' => '0012', 'fileNo' => '10', 'city' => 'تهران', 'referrer' => 'علی',
            ],
            'birthDate' => ['from' => '1360-01-01', 'to' => '1400-12-29'],
        ]);

        $sql = $query->toSql();
        $this->assertStringContainsString('first_name', $sql);
        $this->assertStringContainsString('last_name', $sql);
        $this->assertStringContainsString('birth_date', $sql);
        $this->assertStringContainsString('national_id', $sql);
        $this->assertStringContainsString('exists (', strtolower($sql));
        $this->assertContains('سارا%', $query->getBindings());
        $this->assertContains('1400-12-29', $query->getBindings());
    }

    public function test_report_range_limits_eligible_and_filtered_appointments(): void
    {
        $query = app(DynamicReportFilterPipeline::class)->query([
            'reportDate' => ['from' => '1405-05-26', 'to' => '1405-06-25'],
            'multi' => ['status' => ['آمد']],
        ]);

        $sql = $query->toSql();
        $this->assertStringContainsString('report_range_appointments', $sql);
        $this->assertStringContainsString('status_appointments', $sql);
        $this->assertGreaterThanOrEqual(2, collect($query->getBindings())->filter(fn ($value) => $value === '1405-05-26')->count());
        $this->assertGreaterThanOrEqual(2, collect($query->getBindings())->filter(fn ($value) => $value === '1405-06-25')->count());
    }

    public function test_patient_file_scope_does_not_require_an_appointment_in_the_report_range(): void
    {
        $query = app(DynamicReportFilterPipeline::class)->query([
            'scope' => 'patients',
            'reportDate' => ['from' => '1405-05-26', 'to' => '1405-06-25'],
        ]);

        $this->assertStringNotContainsString('report_range_appointments', $query->toSql());
        $this->assertNotContains('1405-05-26', $query->getBindings());
        $this->assertNotContains('1405-06-25', $query->getBindings());
    }

    public function test_no_return_filter_counts_only_one_arrival_in_the_requested_window(): void
    {
        $query = app(DynamicReportFilterPipeline::class)->query([
            'values' => ['noreturnMonths' => 4],
        ]);

        $sql = $query->toSql();
        $this->assertStringContainsString('TRIM(return_appointments.status)', $sql);
        $this->assertStringContainsString('SELECT COUNT(*)', $sql);
        $this->assertStringContainsString(') = 1', $sql);
        $this->assertContains('آمد', $query->getBindings());
    }

    public function test_appointment_status_filter_accepts_multiple_statuses_with_any_match_semantics(): void
    {
        $query = app(DynamicReportFilterPipeline::class)->query([
            'multi' => ['status' => ['آمد', 'پیگیری']],
        ]);

        $sql = strtolower($query->toSql());
        $this->assertStringContainsString('exists (', $sql);
        $this->assertStringContainsString('status_appointments', $sql);
        $this->assertContains('آمد', $query->getBindings());
        $this->assertContains('پیگیری', $query->getBindings());
    }

    public function test_source_and_work_filters_are_applied_to_related_appointments(): void
    {
        $query = app(DynamicReportFilterPipeline::class)->query([
            'multi' => [
                'source' => ['اینستاگرام', 'گوگل'],
                'work' => ['انجام شد', 'مشاوره'],
            ],
        ]);

        $sql = $query->toSql();
        $this->assertStringContainsString('source_appointments', $sql);
        $this->assertStringContainsString('work_appointments', $sql);
        $this->assertContains('اینستاگرام', $query->getBindings());
        $this->assertContains('انجام شد', $query->getBindings());
    }

    public function test_service_and_area_filters_are_applied(): void
    {
        $query = app(DynamicReportFilterPipeline::class)->query(['multi' => [
            'section2' => ['پوست'], 'subsection' => ['بوتاکس'], 'areas' => ['صورت'],
        ]]);
        $sql = $query->toSql();
        $this->assertStringContainsString('JSON_SEARCH', $sql);
        $this->assertStringContainsString("'$[*].tags[*]'", $sql);
        $this->assertContains('پوست', $query->getBindings());
        $this->assertContains('بوتاکس', $query->getBindings());
        $this->assertContains('صورت', $query->getBindings());
    }

    public function test_commission_and_wallet_filters_use_payroll_lines_and_the_gregorian_report_range(): void
    {
        $query = app(DynamicReportFilterPipeline::class)->query([
            'reportDate' => ['from' => '1405-07-01', 'to' => '1405-07-30'],
            'values' => ['doctorCommission' => '100000', 'consultantCommission' => '0', 'walletDeposit' => '500000', 'walletWithdraw' => ''],
            'comparators' => ['doctorCommission' => 'gt', 'consultantCommission' => 'eq', 'walletDeposit' => 'lt'],
        ]);

        $sql = $query->toSql();
        $this->assertStringContainsString("commission_lines.resource_type = 'doctor'", $sql);
        $this->assertStringContainsString("commission_lines.resource_type = 'staff'", $sql);
        $this->assertStringContainsString("commission_lines.status = 'active'", $sql);
        $this->assertStringContainsString("'inventory_default_commission'", $sql);
        $this->assertStringContainsString('wallet_ledger.patient_id = patients.id', $sql);
        $this->assertSame(1, substr_count($sql, 'FROM wallet_transactions AS wallet_ledger'));
        $this->assertContains('2026-09-23 00:00:00', $query->getBindings());
        $this->assertContains('2026-10-22 23:59:59', $query->getBindings());
    }

    public function test_jalali_dates_convert_to_gregorian(): void
    {
        $dates = app(JalaliMonthWindow::class);
        $this->assertSame('2026-03-21', $dates->toGregorian('1405-01-01'));
        $this->assertSame('2025-03-20', $dates->toGregorian('1403-12-30'));
        $this->assertSame('2026-10-22', $dates->toGregorian('1405-07-30'));
    }

    public function test_jalali_month_window_is_rolling_from_today(): void
    {
        $window = app(JalaliMonthWindow::class)->endingToday(
            4,
            CarbonImmutable::create(2026, 9, 14, 12, 0, 0, 'Asia/Tehran'),
        );

        $this->assertSame(['from' => '1405-02-23', 'to' => '1405-06-23'], $window);
    }
}
