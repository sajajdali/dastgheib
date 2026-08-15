<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Support\Collection;

class CustomerLevelService
{
    public const DEFAULTS = [
        'blue_min_period_amount' => 0,
        'blue_max_period_amount' => 0,
        'blue_visit_count' => 1,
        'blue_visit_period_months' => 3,
        'silver_min_period_amount' => 10000000,
        'silver_max_period_amount' => 30000000,
        'silver_visit_count' => 2,
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

        $appointments = Appointment::query()
            ->whereIn('file_number', $patients->pluck('file_number')->filter())
            ->orWhereIn('phone', $patients->pluck('phone')->filter())
            ->orderByDesc('id')
            ->get();
        $settings = self::settings();

        return $patients->each(function (Patient $patient) use ($appointments, $settings) {
            if ($patient->customer_level === 'problematic') {
                return;
            }

            $history = $appointments->filter(fn (Appointment $item) =>
                ($patient->file_number && $item->file_number === $patient->file_number)
                || ($patient->phone && $item->phone === $patient->phone)
            );
            $patient->setAttribute('customer_level', $this->calculate($history, $settings));
        });
    }

    public function calculate(Collection $appointments, ?array $settings = null): string
    {
        $settings ??= self::settings();
        $usedAppointments = $appointments->filter(fn (Appointment $item) => $this->hasUsedService($item))->values();

        if ($usedAppointments->isEmpty()) {
            // پرونده تازه، تا پیش از استفاده از خدمت، مشتری عادی/نقره‌ای است.
            return 'silver';
        }

        foreach (['gold', 'silver', 'blue'] as $level) {
            if ($this->matchesLevel($usedAppointments, $settings, $level)) {
                return $level;
            }
        }

        return 'silver';
    }

    private function matchesLevel(Collection $appointments, array $settings, string $level): bool
    {
        $minPeriodAmount = (float) ($settings["{$level}_min_period_amount"] ?? $settings["{$level}_min_card_amount"] ?? 0);
        $maxPeriodAmount = (float) ($settings["{$level}_max_period_amount"] ?? $settings["{$level}_three_month_amount"] ?? 0);
        $visitCount = (int) ($settings["{$level}_visit_count"] ?? 0);
        $periodMonths = max(1, (int) ($settings["{$level}_visit_period_months"] ?? 3));

        $periodAppointments = $this->recentAppointments($appointments, $periodMonths);
        $periodTotal = $periodAppointments->sum(fn (Appointment $item) => $this->amount($item->amount));
        $hasPeriodPayment = $minPeriodAmount > 0
            && $periodTotal >= $minPeriodAmount
            && ($maxPeriodAmount <= 0 || $periodTotal <= $maxPeriodAmount);

        $recentVisitCount = $periodAppointments->count();
        $hasVisits = $visitCount > 0 && $recentVisitCount >= $visitCount;

        return $hasPeriodPayment || $hasVisits;
    }

    private function recentAppointments(Collection $appointments, int $months): Collection
    {
        $monthKeys = $appointments
            ->sortByDesc(fn (Appointment $item) => $item->id)
            ->map(fn (Appointment $item) => $item->month ?: $item->created_at?->format('Y-m'))
            ->filter()
            ->unique()
            ->take($months)
            ->values();

        return $appointments->filter(fn (Appointment $item) =>
            $monthKeys->contains($item->month ?: $item->created_at?->format('Y-m'))
        );
    }

    private function hasUsedService(Appointment $appointment): bool
    {
        return ! empty($appointment->services) || filled($appointment->done);
    }

    private function amount(mixed $value): float
    {
        $digits = preg_replace('/[^0-9.]/', '', strtr((string) $value, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٬'=>'']));
        return (float) ($digits ?: 0);
    }

}
