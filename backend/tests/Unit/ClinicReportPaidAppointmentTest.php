<?php

namespace Tests\Unit;

use App\Services\ClinicReportService;
use ReflectionClass;
use Tests\TestCase;

class ClinicReportPaidAppointmentTest extends TestCase
{
    public function test_report_requires_completed_and_fully_paid_appointment(): void
    {
        $reflection = new ReflectionClass(ClinicReportService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('isPaidCompleted');

        $appointment = fn (string $done, int $amount, int $debt, array $details, int $wallet = 0) => (object) [
            'done' => $done,
            'amount' => $amount,
            'debt' => $debt,
            'payment_details' => $details,
            'wallet_applied' => $wallet,
        ];

        $this->assertTrue($method->invoke($service, $appointment('انجام شد', 100, 0, ['ledger_total'=>100])));
        $this->assertFalse($method->invoke($service, $appointment('انجام شد', 100, 20, ['ledger_total'=>100])));
        $this->assertFalse($method->invoke($service, $appointment('انجام شد', 100, 0, ['ledger_total'=>60])));
        $this->assertFalse($method->invoke($service, $appointment('', 100, 0, ['ledger_total'=>100])));
        $this->assertTrue($method->invoke($service, $appointment('انجام شد', 0, 0, ['ledger_total'=>0], 100)));
    }

    public function test_legacy_payment_breakdown_is_supported(): void
    {
        $reflection = new ReflectionClass(ClinicReportService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('isPaidCompleted');
        $appointment = (object) [
            'done' => 'انجام شد',
            'amount' => 100,
            'debt' => 0,
            'wallet_applied' => 0,
            'payment_details' => ['cash'=>40, 'card'=>30, 'check'=>['amount'=>30]],
        ];

        $this->assertTrue($method->invoke($service, $appointment));
    }
}
