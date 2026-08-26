<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicCalendarOverride;
use App\Services\OfficialCalendarService;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request, OfficialCalendarService $calendar)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:1300,1600'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $prefix = sprintf('%04d-%02d-', $data['year'], $data['month']);
        $official = collect($calendar->eventsForMonth($data['year'], $data['month']))->keyBy('date');
        $overrides = ClinicCalendarOverride::query()
            ->where('jalali_date', 'like', $prefix.'%')
            ->get()
            ->keyBy('jalali_date');

        $events = collect(range(1, 31))->map(function (int $day) use ($prefix, $official, $overrides) {
            $date = $prefix.str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            $event = $official->get($date);
            $override = $overrides->get($date);

            if (! $event && ! $override) {
                return null;
            }

            return [
                'day' => $day,
                'title' => $override?->title ?: ($event['title'] ?? ''),
                'holiday' => $override ? $override->is_closed : ($event['holiday'] ?? false),
                'is_override' => (bool) $override,
            ];
        })->filter()->values();

        return response()->json(['events' => $events]);
    }

    public function saveOverride(Request $request, string $date)
    {
        if (! preg_match('/^1[34]\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $date)) {
            abort(422, 'تاریخ شمسی معتبر نیست.');
        }

        $data = $request->validate([
            'is_closed' => ['required', 'boolean'],
            'title' => ['nullable', 'string', 'max:500'],
        ]);

        $override = ClinicCalendarOverride::updateOrCreate(
            ['jalali_date' => $date],
            [
                'is_closed' => $data['is_closed'],
                'title' => trim((string) ($data['title'] ?? '')) ?: null,
                'updated_by' => $request->user()->id,
            ]
        );

        return response()->json(['override' => $override]);
    }
}
