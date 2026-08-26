<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OfficialCalendarService
{
    public function eventsForMonth(int $year, int $month): array
    {
        return tenancy()->central(function () use ($year, $month) {
            return DB::table('calendar_events')
                ->whereBetween('jalali_date', [sprintf('%04d-%02d-01', $year, $month), sprintf('%04d-%02d-31', $year, $month)])
                ->orderBy('jalali_date')
                ->get(['jalali_date', 'title', 'is_official_holiday'])
                ->map(fn ($event) => [
                    'date' => $event->jalali_date,
                    'title' => $event->title ?? '',
                    'holiday' => (bool) $event->is_official_holiday,
                ])
                ->all();
        });
    }

}
