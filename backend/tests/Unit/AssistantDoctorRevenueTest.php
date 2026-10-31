<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Assistant\AssistantDateRange;
use App\Services\Assistant\AssistantToolExecutor;
use App\Services\Assistant\AssistantToolRegistry;
use App\Services\ClinicReportService;
use Carbon\Carbon;
use Mockery;
use PHPUnit\Framework\TestCase;

final class AssistantDoctorRevenueTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    public function test_doctor_revenue_uses_current_month_report_and_excludes_payroll_fields(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 10:00:00', 'Asia/Tehran'));
        $user = Mockery::mock(User::class);
        $user->shouldReceive('can')->andReturnUsing(fn (string $permission) => in_array($permission, ['reports.financial','reports.doctors'], true));
        $reports = Mockery::mock(ClinicReportService::class);
        $reports->shouldReceive('normalizeFilters')->once()->with(['from'=>'1405-08-01','to'=>'1405-08-08'])
            ->andReturn(['from'=>'1405-08-01','to'=>'1405-08-08']);
        $reports->shouldReceive('doctorPerformanceReport')->once()->andReturn(['doctors'=>[
            ['name'=>'پزشک اول','revenue'=>4000000,'salary'=>1000000,'commission'=>500000],
            ['name'=>'پزشک دوم','revenue'=>7000000,'salary'=>2000000,'commission'=>800000],
        ]]);

        $result = (new AssistantToolExecutor(new AssistantToolRegistry, new AssistantDateRange, $reports))
            ->execute($user, 'get_doctor_revenue', ['period'=>'this_month','from'=>null,'to'=>null,'doctor'=>null]);

        $this->assertSame('1405-08-01 تا 1405-08-08', $result['model']['period']);
        $this->assertSame([
            ['doctor'=>'پزشک دوم','revenue'=>7000000],
            ['doctor'=>'پزشک اول','revenue'=>4000000],
        ], $result['model']['doctors']);
        $this->assertSame('تومان', $result['model']['unit']);
        $this->assertSame('table', $result['presentation']['type']);
    }
}
