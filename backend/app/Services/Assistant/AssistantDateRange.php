<?php

namespace App\Services\Assistant;

use App\Reporting\DynamicReports\JalaliMonthWindow;
use InvalidArgumentException;

final class AssistantDateRange
{
    public function resolve(array $arguments): array
    {
        $today = (new JalaliMonthWindow)->endingToday(1, now('Asia/Tehran'))['to'];
        $period = $arguments['period'] ?? 'today';
        if ($period === 'today') return ['from' => $today, 'to' => $today];
        if ($period === 'this_month') return ['from' => substr($today, 0, 7).'-01', 'to' => $today];
        if ($period !== 'custom') throw new InvalidArgumentException('بازهٔ زمانی ناشناخته است.');

        $from = $this->validDate($arguments['from'] ?? null);
        $to = $this->validDate($arguments['to'] ?? null);
        if ($from > $to) throw new InvalidArgumentException('بازهٔ تاریخ معتبر نیست.');
        $fromIndex = ((int) substr($from, 0, 4)) * 12 + (int) substr($from, 5, 2);
        $toIndex = ((int) substr($to, 0, 4)) * 12 + (int) substr($to, 5, 2);
        if ($toIndex - $fromIndex > 12) throw new InvalidArgumentException('حداکثر بازهٔ قابل جست‌وجو یک سال است.');
        return ['from' => $from, 'to' => $to];
    }

    private function validDate(mixed $value): string
    {
        $value = strtr(trim((string) $value), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','/'=>'-']);
        if (! preg_match('/^1[34]\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $value)) {
            throw new InvalidArgumentException('تاریخ باید شمسی و به شکل YYYY-MM-DD باشد.');
        }
        return $value;
    }
}
