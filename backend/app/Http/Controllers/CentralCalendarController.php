<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CentralCalendarController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:1300,1600'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        return response()->json(['events' => DB::table('calendar_events')
            ->where('jalali_date', 'like', sprintf('%04d-%02d-%%', $data['year'], $data['month']))
            ->orderBy('jalali_date')
            ->get()]);
    }

    public function update(Request $request, string $date)
    {
        if (! preg_match('/^1[34]\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $date)) {
            abort(422, 'تاریخ شمسی معتبر نیست.');
        }

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:4000'],
            'is_official_holiday' => ['required', 'boolean'],
        ]);

        DB::table('calendar_events')->updateOrInsert(
            ['jalali_date' => $date],
            [
                'title' => trim((string) ($data['title'] ?? '')) ?: null,
                'is_official_holiday' => $data['is_official_holiday'],
                'source' => 'manual',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return response()->json(['message' => 'تقویم رسمی برای همه کلینیک‌ها بروزرسانی شد.']);
    }
}
