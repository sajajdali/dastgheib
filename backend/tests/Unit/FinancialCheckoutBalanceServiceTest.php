<?php

namespace Tests\Unit;

use App\Services\FinancialCheckoutBalanceService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FinancialCheckoutBalanceServiceTest extends TestCase
{
    private FinancialCheckoutBalanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FinancialCheckoutBalanceService();
    }

    public function test_equal_service_debt_is_accepted(): void
    {
        $result = $this->service->reconcile(10_000_000, 7_000_000, 3_000_000);

        $this->assertSame(10_000_000, $result['payable']);
        $this->assertSame(3_000_000, $result['total_debt']);
        $this->assertSame(3_000_000, $result['balance']);
    }

    public function test_lower_service_debt_is_rejected_with_exact_amounts(): void
    {
        try {
            $this->service->reconcile(10_000_000, 7_000_000, 2_500_000);
            $this->fail('Expected the incomplete debt allocation to be rejected.');
        } catch (HttpException $error) {
            $this->assertSame(422, $error->getStatusCode());
            $this->assertSame(
                'جمع بدهی خدمات 2,500,000 تومان است، اما مانده قابل پرداخت 3,000,000 تومان است؛ مبلغ بدهی خدمات باید 500,000 تومان افزایش یابد.',
                $error->getMessage()
            );
        }
    }

    public function test_higher_service_debt_requires_confirmation(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('برای ثبت مبلغ بیشتر، تأیید کاربر لازم است');

        $this->service->reconcile(10_000_000, 7_000_000, 4_000_000);
    }

    public function test_confirmed_higher_service_debt_increases_payable(): void
    {
        $result = $this->service->reconcile(10_000_000, 7_000_000, 4_000_000, true);

        $this->assertSame(11_000_000, $result['payable']);
        $this->assertSame(1_000_000, $result['confirmed_debt_overage']);
        $this->assertSame(1_000_000, $result['payable_increase']);
        $this->assertSame(4_000_000, $result['balance']);
    }

    public function test_confirmed_debt_with_an_existing_payment_excess_preserves_the_full_payable_increase(): void
    {
        $result = $this->service->reconcile(10_000_000, 11_000_000, 2_000_000, true);

        $this->assertSame(13_000_000, $result['payable']);
        $this->assertSame(2_000_000, $result['confirmed_debt_overage']);
        $this->assertSame(3_000_000, $result['payable_increase']);
        $this->assertSame(0, $result['overpayment']);
        $this->assertSame(2_000_000, $result['balance']);
    }

    public function test_overpayment_requires_confirmation_and_is_stored_as_credit(): void
    {
        try {
            $this->service->reconcile(10_000_000, 11_500_000, 0);
            $this->fail('Expected overpayment confirmation.');
        } catch (HttpException $error) {
            $this->assertStringContainsString('1,500,000 تومان بیشتر', $error->getMessage());
        }

        $result = $this->service->reconcile(10_000_000, 11_500_000, 0, false, true);
        $this->assertSame(1_500_000, $result['overpayment']);
        $this->assertSame(-1_500_000, $result['balance']);
    }
}
