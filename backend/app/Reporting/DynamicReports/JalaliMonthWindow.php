<?php

namespace App\Reporting\DynamicReports;

use Carbon\CarbonInterface;

final class JalaliMonthWindow
{
    /** @return array{from: string, to: string} */
    public function endingToday(int $months, ?CarbonInterface $today = null): array
    {
        [$year, $month, $day] = $this->fromGregorian($today ?? now('Asia/Tehran'));
        $to = $this->format($year, $month, $day);

        $monthIndex = ($year * 12 + $month - 1) - max(1, $months);
        $fromYear = intdiv($monthIndex, 12);
        $fromMonth = ($monthIndex % 12) + 1;
        $fromDay = min($day, $this->daysInMonth($fromYear, $fromMonth));

        return ['from' => $this->format($fromYear, $fromMonth, $fromDay), 'to' => $to];
    }

    public function date(CarbonInterface $date): string
    {
        return $this->format(...$this->fromGregorian($date));
    }

    /** تاریخ شمسی «YYYY-MM-DD» به میلادی «Y-m-d» (همان الگوریتم j2g گزارش‌ساز). */
    public function toGregorian(string $jalali): string
    {
        [$jy, $jm, $jd] = array_map('intval', explode('-', $jalali));
        $jy += 1595;
        $days = -355668 + 365 * $jy + intdiv($jy, 33) * 8 + intdiv(($jy % 33) + 3, 4) + $jd
            + ($jm < 7 ? ($jm - 1) * 31 : ($jm - 7) * 30 + 186);
        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;
            if ($days >= 365) $days++;
        }
        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $monthLengths = [0, 31, ($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0 ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        for ($gm = 1; $gm <= 12 && $gd > $monthLengths[$gm]; $gm++) $gd -= $monthLengths[$gm];
        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    /** @return array{int, int, int} */
    private function fromGregorian(CarbonInterface $date): array
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');
        $day = (int) $date->format('j');
        $monthOffsets = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $leapYear = $month > 2 ? $year + 1 : $year;
        $days = 355666 + 365 * $year + intdiv($leapYear + 3, 4)
            - intdiv($leapYear + 99, 100) + intdiv($leapYear + 399, 400)
            + $day + $monthOffsets[$month - 1];
        $jalaliYear = -1595 + 33 * intdiv($days, 12053);
        $days %= 12053;
        $jalaliYear += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jalaliYear += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $jalaliMonth = $days < 186 ? 1 + intdiv($days, 31) : 7 + intdiv($days - 186, 30);
        $jalaliDay = 1 + ($days < 186 ? $days % 31 : ($days - 186) % 30);
        return [$jalaliYear, $jalaliMonth, $jalaliDay];
    }

    private function daysInMonth(int $year, int $month): int
    {
        if ($month <= 6) return 31;
        if ($month <= 11) return 30;
        return in_array(($year + 12) % 33, [1, 5, 9, 13, 17, 22, 26, 30], true) ? 30 : 29;
    }

    private function format(int $year, int $month, int $day): string
    {
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
