<?php

namespace Tests\Unit;

use App\Models\CentralBillingPlan;
use App\Models\CentralUserPricing;
use App\Services\TenantSubscriptionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TenantSubscriptionPricingTest extends TestCase
{
    #[DataProvider('pricingCases')]
    public function test_quote_uses_the_purchased_user_capacity(
        int $licensedUsers,
        int $includedUsers,
        int $basePrice,
        int $extraUserPrice,
        int $expected,
    ): void {
        $plan = new CentralBillingPlan(['base_price' => $basePrice]);
        $pricing = new CentralUserPricing([
            'included_users' => $includedUsers,
            'extra_user_price' => $extraUserPrice,
        ]);

        $actual = (new TenantSubscriptionService())->quote($plan, $licensedUsers, $pricing);

        self::assertSame($expected, $actual);
    }

    public static function pricingCases(): array
    {
        return [
            'included user only' => [1, 1, 100_000, 25_000, 100_000],
            'three additional users' => [4, 1, 100_000, 25_000, 175_000],
            'capacity below included tier' => [2, 3, 100_000, 25_000, 100_000],
        ];
    }
}
