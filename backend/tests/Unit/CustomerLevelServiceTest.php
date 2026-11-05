<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Services\CustomerLevelService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerLevelServiceTest extends TestCase
{
    /** 2026-11-05 = ۱۴۰۵/۰۸/۱۴ */
    private const TODAY = '2026-11-05 12:00:00';

    private const SETTINGS = [
        'blue_min_period_amount' => 5000000,
        'blue_visit_count' => 2,
        'blue_visit_period_months' => 6,
        'gold_min_period_amount' => 30000000,
        'gold_visit_count' => 3,
        'gold_visit_period_months' => 2,
        // مقادیر قدیمی سقف دیگر نباید اثری داشته باشند.
        'blue_max_period_amount' => 30000000,
        'gold_max_period_amount' => 500000,
    ];

    public static function cases(): array
    {
        return [
            'new file without used service' => [[], 'silver'],
            'big single payment reaches gold despite old max' => [[['1405-08', 1, 40000000]], 'gold'],
            'payment just over blue max but under gold min stays blue' => [[['1405-08', 1, 20000000], ['1405-03', 4, 15000000]], 'blue'],
            'three visit days within two months is gold' => [[['1405-07', 3, 100], ['1405-07', 20, 100], ['1405-08', 2, 100]], 'gold'],
            'three services on the same day are one visit' => [[['1405-08', 3, 100], ['1405-08', 3, 100], ['1405-08', 3, 100]], 'silver'],
            'old visits outside the window do not count' => [[['1403-01', 1, 100], ['1403-01', 2, 100], ['1403-01', 3, 100]], 'silver'],
            'gold window is calendar based not last active months' => [[['1405-06', 1, 100], ['1405-06', 2, 100], ['1405-06', 3, 100]], 'blue'],
            'blue by payment alone' => [[['1405-05', 1, 6000000]], 'blue'],
            'below every rule is silver' => [[['1405-05', 1, 1000000]], 'silver'],
        ];
    }

    #[DataProvider('cases')]
    public function test_customer_level_rules(array $rows, string $expected): void
    {
        $level = (new CustomerLevelService)->calculate($this->appointments($rows), self::SETTINGS, Carbon::parse(self::TODAY, 'Asia/Tehran'));

        $this->assertSame($expected, $level);
    }

    public function test_unfinished_appointments_are_ignored(): void
    {
        $appointment = new Appointment();
        $appointment->id = 1;
        $appointment->month = '1405-08';
        $appointment->day_num = 1;
        $appointment->amount = 90000000;
        $appointment->done = 'انجام نشد';

        $level = (new CustomerLevelService)->calculate(collect([$appointment]), self::SETTINGS, Carbon::parse(self::TODAY, 'Asia/Tehran'));

        $this->assertSame('silver', $level);
    }

    public function test_window_crosses_the_jalali_new_year(): void
    {
        // فروردین ۱۴۰۶ با بازهٔ ۲ ماهه، اسفند ۱۴۰۵ را هم شامل می‌شود.
        $rows = [['1405-12', 10, 100], ['1405-12', 20, 100], ['1406-01', 5, 100]];

        $level = (new CustomerLevelService)->calculate($this->appointments($rows), self::SETTINGS, Carbon::parse('2027-04-01 12:00:00', 'Asia/Tehran'));

        $this->assertSame('gold', $level);
    }

    private function appointments(array $rows): Collection
    {
        return collect($rows)->values()->map(function (array $row, int $index) {
            $appointment = new Appointment();
            $appointment->id = $index + 1;
            $appointment->month = $row[0];
            $appointment->day_num = $row[1];
            $appointment->amount = $row[2];
            $appointment->done = 'انجام شد';

            return $appointment;
        });
    }
}
