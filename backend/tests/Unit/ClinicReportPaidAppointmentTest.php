<?php

namespace Tests\Unit;

use App\Services\ClinicReportService;
use Illuminate\Support\Collection;
use ReflectionClass;
use Tests\TestCase;

class ClinicReportPaidAppointmentTest extends TestCase
{
    private function invokeReport(string $method, mixed ...$arguments): mixed
    {
        $reflection = new ReflectionClass(ClinicReportService::class);

        return $reflection->getMethod($method)->invoke($reflection->newInstanceWithoutConstructor(), ...$arguments);
    }

    public function test_revenue_is_recognized_when_the_visit_is_done_even_with_debt(): void
    {
        $appointment = fn (string $done, int $amount, int $debt, array $details, int $wallet = 0) => (object) [
            'done' => $done,
            'amount' => $amount,
            'debt' => $debt,
            'payment_details' => $details,
            'wallet_applied' => $wallet,
        ];

        $this->assertTrue($this->invokeReport('isRevenueRecognized', $appointment('انجام شد', 100, 0, ['ledger_total'=>100])));
        $this->assertTrue($this->invokeReport('isRevenueRecognized', $appointment('انجام شد', 100, 20, ['ledger_total'=>80])));
        $this->assertTrue($this->invokeReport('isRevenueRecognized', $appointment('انجام شد', 100, 0, ['ledger_total'=>60])));
        $this->assertFalse($this->invokeReport('isRevenueRecognized', $appointment('', 100, 0, ['ledger_total'=>100])));
    }

    public function test_legacy_payment_breakdown_is_counted_as_received_money(): void
    {
        $appointment = (object) [
            'done' => 'انجام شد',
            'amount' => 100,
            'debt' => 0,
            'wallet_applied' => 0,
            'payment_details' => ['cash'=>40, 'card'=>30, 'check'=>['amount'=>30]],
        ];

        $this->assertSame(100.0, $this->invokeReport('recordedPaymentAmount', $appointment));
    }

    public function test_appointment_value_is_after_discount_and_adds_back_wallet(): void
    {
        $appointment = (object) [
            'amount' => 4050000,
            'wallet_applied' => 500000,
            'discount' => 450000,
            'services' => [['name' => 'بوتاکس', 'price' => 4500000, 'quantity' => 1, 'cc' => 1, 'material_cost' => 1800000]],
        ];

        $this->assertSame(4550000.0, $this->invokeReport('appointmentValue', $appointment));
    }

    public function test_material_cost_comes_from_inventory_including_addons(): void
    {
        $prices = [
            'inventory' => new Collection(['فیلر' => (object) ['amount' => 1000000, 'price' => 300000], 'ژل' => (object) ['amount' => 50000, 'price' => 20000]]),
            'addons' => new Collection([7 => (object) ['amount' => 100000, 'price' => 40000]]),
        ];
        $appointment = (object) ['services' => [
            ['name' => 'فیلر', 'cc' => 2, 'addons' => [['name' => 'ژل', 'cc' => 1], ['name' => 'سرنگ', 'addon_definition_id' => 7, 'cc' => 3]]],
            ['name' => 'خدمت حذف‌شده', 'cc' => 1, 'material_cost' => 90000],
        ]];

        $this->assertSame(830000.0, $this->invokeReport('appointmentMaterialCost', $appointment, $prices));
    }

    public function test_empty_quantity_counts_as_zero_like_the_appointment_amount(): void
    {
        $this->assertSame(0.0, $this->invokeReport('serviceQuantity', ['name' => 'پکیج', 'cc' => null]));
        $this->assertSame(0.0, $this->invokeReport('serviceQuantity', ['name' => 'پکیج', 'cc' => '']));
        $this->assertSame(2.0, $this->invokeReport('serviceQuantity', ['name' => 'پکیج', 'cc' => '2']));
        $this->assertSame(1.0, $this->invokeReport('serviceQuantity', ['name' => 'قدیمی']));
    }

    public function test_doctor_share_weight_uses_sale_price_after_discount_and_surcharge(): void
    {
        $prices = [
            'inventory' => new Collection(['فیلر' => (object) ['amount' => 1000000, 'price' => 300000]]),
            'addons' => new Collection(),
        ];

        $this->assertSame(1600000.0, $this->invokeReport('serviceNet', ['name' => 'فیلر', 'cc' => 2, 'discount' => 400000, 'adjustment_mode' => 'discount'], $prices));
        $this->assertSame(1250000.0, $this->invokeReport('serviceNet', ['name' => 'فیلر', 'cc' => 1, 'discount' => 250000, 'adjustment_mode' => 'surcharge'], $prices));
    }
}
