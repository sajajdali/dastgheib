<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Appointment;
use App\Models\Patient;
use App\Reporting\DynamicReports\JalaliMonthWindow;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CustomerLevelService
{
    public const DEFAULTS = [
        'blue_min_period_amount' => 10000000,
        'blue_max_period_amount' => 30000000,
        'blue_visit_count' => 2,
        'blue_visit_period_months' => 3,
        'silver_min_period_amount' => 0,
        'silver_max_period_amount' => 0,
        'silver_visit_count' => 0,
        'silver_visit_period_months' => 3,
        'gold_min_period_amount' => 100000000,
        'gold_max_period_amount' => 200000000,
        'gold_visit_count' => 3,
        'gold_visit_period_months' => 3,
    ];

    public static function settings(): array
    {
        $stored = json_decode((string) AppSetting::getByKey('customer_level_settings', '{}'), true);

        return array_merge(self::DEFAULTS, is_array($stored) ? $stored : []);
    }

    public function decorate(Collection $patients): Collection
    {
        if ($patients->isEmpty()) {
            return $patients;
        }

        $fileNumbers = $patients->pluck('file_number')->filter()->unique()->values();
        $phones = $patients->pluck('phone')->filter()->unique()->values();

        // Customer level only depends on completed/used appointments. Loading
        // empty and future schedule rows used a large amount of memory, and
        // filtering the entire history once for every patient was O(P x A).
        $appointments = Appointment::query()
            ->where(function ($query) use ($fileNumbers, $phones) {
                if ($fileNumbers->isNotEmpty()) {
                    $query->whereIn('file_number', $fileNumbers);
                }
                if ($phones->isNotEmpty()) {
                    $method = $fileNumbers->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('phone', $phones);
                }
            })
            ->where(function ($query) {
                $query->where('done', 'انجام شد')
                    ->orWhereNotNull('completed_at');
            })
            ->orderByDesc('id')
            ->get(['id', 'file_number', 'phone', 'month', 'day_num', 'amount', 'done', 'completed_at', 'created_at']);
        $settings = self::settings();
        $byFileNumber = $appointments->filter->file_number->groupBy('file_number');
        $byPhone = $appointments->filter->phone->groupBy('phone');

        return $patients->each(function (Patient $patient) use ($byFileNumber, $byPhone, $settings) {
            if ($patient->customer_level === 'problematic') {
                return;
            }

            $history = collect()
                ->concat($patient->file_number ? $byFileNumber->get($patient->file_number, collect()) : collect())
                ->concat($patient->phone ? $byPhone->get($patient->phone, collect()) : collect())
                ->unique('id')
                ->values();
            $patient->setAttribute('customer_level', $this->calculate($history, $settings));
        });
    }

    public function calculate(Collection $appointments, ?array $settings = null, ?CarbonInterface $today = null): string
    {
        $settings ??= self::settings();
        $usedAppointments = $appointments->filter(fn (Appointment $item) => $this->hasUsedService($item))->values();

        if ($usedAppointments->isEmpty()) {
            // پرونده تازه، تا پیش از استفاده از خدمت، مشتری عادی/نقره‌ای است.
            return 'silver';
        }

        $currentMonth = substr((new JalaliMonthWindow)->date($today ?? now('Asia/Tehran')), 0, 7);

        // سطح‌ها از بالا به پایین بررسی می‌شوند؛ نقره‌ای سطح پیش‌فرض است و
        // معیار جداگانه ندارد. چون سطح بالاتر زودتر بررسی می‌شود، «سقف پرداخت»
        // لازم نیست: مشتری پرخرجی که به طلایی نرسد، دست‌کم آبی می‌ماند.
        foreach (['gold', 'blue'] as $level) {
            if ($this->matchesLevel($usedAppointments, $settings, $level, $currentMonth)) {
                return $level;
            }
        }

        return 'silver';
    }

    private function matchesLevel(Collection $appointments, array $settings, string $level, string $currentMonth): bool
    {
        $minPeriodAmount = (float) ($settings["{$level}_min_period_amount"] ?? $settings["{$level}_min_card_amount"] ?? 0);
        $visitCount = (int) ($settings["{$level}_visit_count"] ?? 0);
        $periodMonths = max(1, (int) ($settings["{$level}_visit_period_months"] ?? 3));

        $periodAppointments = $this->recentAppointments($appointments, $periodMonths, $currentMonth);
        $periodTotal = $periodAppointments->sum(fn (Appointment $item) => $this->amount($item->amount));
        $hasPeriodPayment = $minPeriodAmount > 0 && $periodTotal >= $minPeriodAmount;

        // چند خدمت در یک روز یک مراجعه حساب می‌شود.
        $recentVisitCount = $periodAppointments
            ->map(fn (Appointment $item) => $this->monthKey($item).'-'.((int) $item->day_num ?: 'id'.$item->id))
            ->unique()
            ->count();
        $hasVisits = $visitCount > 0 && $recentVisitCount >= $visitCount;

        return $hasPeriodPayment || $hasVisits;
    }

    /**
     * نوبت‌های N ماه شمسی اخیر تا ماه جاری (مثلاً بازهٔ ۲ ماهه در آبان = مهر و آبان).
     */
    private function recentAppointments(Collection $appointments, int $months, string $currentMonth): Collection
    {
        [$year, $month] = array_map('intval', explode('-', $currentMonth));
        $firstIndex = ($year * 12 + $month - 1) - ($months - 1);
        $fromMonth = sprintf('%04d-%02d', intdiv($firstIndex, 12), ($firstIndex % 12) + 1);

        return $appointments->filter(function (Appointment $item) use ($fromMonth, $currentMonth) {
            $key = $this->monthKey($item);

            return $key !== null && $key >= $fromMonth && $key <= $currentMonth;
        });
    }

    private function monthKey(Appointment $appointment): ?string
    {
        $month = trim((string) $appointment->month);
        if (preg_match('/^(\d{4})-(\d{1,2})/', strtr($month, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']), $match)) {
            return sprintf('%04d-%02d', (int) $match[1], (int) $match[2]);
        }

        // ستون month قدیمی خالی باشد، تاریخ ثبت به ماه شمسی تبدیل می‌شود.
        $date = $appointment->completed_at ?: $appointment->created_at;
        if (! $date) {
            return null;
        }

        try {
            $date = $date instanceof CarbonInterface ? $date : Carbon::parse((string) $date);
        } catch (\Throwable) {
            return null;
        }

        return substr((new JalaliMonthWindow)->date($date), 0, 7);
    }

    private function hasUsedService(Appointment $appointment): bool
    {
        return trim((string) $appointment->done) === 'انجام شد'
            || filled($appointment->completed_at);
    }

    private function amount(mixed $value): float
    {
        $digits = preg_replace('/[^0-9.]/', '', strtr((string) $value, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٬'=>'']));
        return (float) ($digits ?: 0);
    }

}
