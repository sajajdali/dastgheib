<?php

namespace Tests\Unit;

use App\Services\Assistant\AssistantDateRange;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AssistantDateRangeTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_today_and_current_jalali_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 10:00:00', 'Asia/Tehran'));
        $dates = new AssistantDateRange;

        $this->assertSame(['from'=>'1405-08-08','to'=>'1405-08-08'], $dates->resolve(['period'=>'today']));
        $this->assertSame(['from'=>'1405-08-01','to'=>'1405-08-08'], $dates->resolve(['period'=>'this_month']));
    }

    public function test_invalid_custom_range_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AssistantDateRange)->resolve(['period'=>'custom','from'=>'1405-09-01','to'=>'1405-08-01']);
    }
}
