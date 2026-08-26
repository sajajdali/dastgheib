<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class OfficialCalendarSeeder extends Seeder
{
    /** Imports a complete Jalali year once; the application never calls this provider at runtime. */
    public function run(): void
    {
        foreach (range(1404, 1410) as $year) {
            for ($month = 1; $month <= 12; $month++) {
                $complete = DB::table('calendar_syncs')
                    ->where('jalali_year', $year)
                    ->where('jalali_month', $month)
                    ->where('status', 'completed')
                    ->exists();

                if ($complete) continue;

                try {
                    $result = Http::acceptJson()->timeout(15)
                        ->get('https://pnldev.com/api/calender', compact('year', 'month'))
                        ->throw()
                        ->json('result', []);
                } catch (Throwable $exception) {
                    DB::table('calendar_syncs')->updateOrInsert(
                        ['jalali_year' => $year, 'jalali_month' => $month],
                        ['synced_at' => null, 'status' => 'failed', 'error' => $exception->getMessage(), 'updated_at' => now(), 'created_at' => now()]
                    );
                    continue;
                }

                $now = now();
                foreach ($result as $day => $item) {
                    if (! is_array($item)) continue;

                    DB::table('calendar_events')->updateOrInsert(
                        ['jalali_date' => sprintf('%04d-%02d-%02d', $year, $month, (int) $day)],
                        [
                            'title' => is_array($item['event'] ?? null) ? implode('، ', $item['event']) : '',
                            'is_official_holiday' => ($item['holiday'] ?? false) === true,
                            'source' => 'pnldev-import-'.$year,
                            'source_synced_at' => $now,
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]
                    );
                }

                DB::table('calendar_syncs')->updateOrInsert(
                    ['jalali_year' => $year, 'jalali_month' => $month],
                    ['synced_at' => $now, 'status' => 'completed', 'error' => null, 'updated_at' => $now, 'created_at' => $now]
                );
            }
        }
    }
}
