<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\Appointment;
use App\Reporting\DynamicReports\JalaliMonthWindow;
use Carbon\Carbon;

/**
 * روزهای کاری نوبت‌دهی از تنظیمات داخلی («برنامه نوبت‌دهی»)؛
 * فعال/غیرفعال کردن weekday فقط از همان‌جا و از لحظهٔ ذخیره به بعد اعمال می‌شود.
 */
final class ClinicSchedule
{
    public const WEEKDAY_KEYS = [
        'saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday',
    ];

    private const DAY_NUMBER_TO_KEY = [
        0 => 'sunday',
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
    ];

    public static function defaults(): array
    {
        return [
            'active_days' => ['saturday', 'monday', 'wednesday'],
            'interval_minutes' => 15,
            'day_times' => collect(self::WEEKDAY_KEYS)
                ->mapWithKeys(fn (string $day) => [$day => ['start' => '09:00', 'end' => '17:00']])
                ->all(),
        ];
    }

    public static function settings(): array
    {
        $raw = AppSetting::getByKey('clinic_schedule_settings', '{}');
        $stored = is_string($raw) ? json_decode($raw, true) : $raw;
        $defaults = self::defaults();
        $isConfigured = is_array($stored)
            && ! empty($stored['active_days'])
            && is_array($stored['day_times'] ?? null);

        if (! is_array($stored)) {
            return [...$defaults, 'is_configured' => false];
        }

        return [
            'active_days' => array_values(array_filter($stored['active_days'] ?? $defaults['active_days'])),
            'interval_minutes' => max(1, (int) ($stored['interval_minutes'] ?? $defaults['interval_minutes'])),
            'day_times' => array_replace_recursive(
                $defaults['day_times'],
                is_array($stored['day_times'] ?? null) ? $stored['day_times'] : []
            ),
            'is_configured' => $isConfigured,
        ];
    }

    public static function activeDays(): array
    {
        return array_values(array_intersect(self::settings()['active_days'] ?? [], self::WEEKDAY_KEYS));
    }

    public static function weekdayKeyFromJalali(?string $month, mixed $dayNum): ?string
    {
        $month = trim((string) $month);
        $dayNum = (int) $dayNum;
        if (! preg_match('/^\d{4}-\d{2}$/', $month) || $dayNum < 1 || $dayNum > 31) {
            return null;
        }

        $jalali = sprintf('%s-%02d', $month, $dayNum);
        try {
            $gregorian = (new JalaliMonthWindow())->toGregorian($jalali);
            $carbon = Carbon::parse($gregorian, 'Asia/Tehran');
        } catch (\Throwable) {
            return null;
        }

        return self::DAY_NUMBER_TO_KEY[(int) $carbon->dayOfWeek] ?? null;
    }

    public static function isActiveWeekday(?string $month, mixed $dayNum): bool
    {
        $key = self::weekdayKeyFromJalali($month, $dayNum);
        if ($key === null) {
            return true;
        }

        $active = self::activeDays();
        if ($active === [] || ! (self::settings()['is_configured'] ?? false)) {
            return true;
        }

        return in_array($key, $active, true);
    }

    /**
     * @param  list<string>  $weekdays
     * @return array<string, int>
     */
    public static function futureAppointmentCountsByWeekday(array $weekdays): array
    {
        $wanted = array_values(array_intersect($weekdays, self::WEEKDAY_KEYS));
        $counts = array_fill_keys($wanted, 0);
        if ($wanted === []) {
            return $counts;
        }

        $today = AppointmentPastAccess::today();
        [$todayYear, $todayMonth] = explode('-', $today);
        $currentMonth = "{$todayYear}-{$todayMonth}";

        $appointments = Appointment::query()
            ->where(fn ($query) => Appointment::constrainToMeaningful($query))
            ->where(function ($query) use ($currentMonth, $today) {
                $day = (int) substr($today, -2);
                $query->where('month', '>', $currentMonth)
                    ->orWhere(fn ($same) => $same->where('month', $currentMonth)->where('day_num', '>=', $day));
            })
            ->where(function ($query) {
                $query->whereNull('file_number')
                    ->orWhere('file_number', 'not like', 'LOADTEST-%');
            })
            ->get(['month', 'day_num']);

        foreach ($appointments as $appointment) {
            $key = self::weekdayKeyFromJalali($appointment->month, $appointment->day_num);
            if ($key !== null && array_key_exists($key, $counts)) {
                $counts[$key]++;
            }
        }

        return $counts;
    }
}
