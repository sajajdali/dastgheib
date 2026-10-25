<?php

namespace App\Jobs;

use App\Models\AppSetting;
use App\Models\Patient;
use App\Services\ShsmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendBirthdaySms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(ShsmsService $sms): void
    {
        $legacyEnabled = AppSetting::getByKey('birthday_sms_enabled', '0') === '1';
        $templateEnabled = AppSetting::getByKey(
            'birthday_sms_template_enabled',
            $legacyEnabled ? '1' : '0'
        ) === '1';
        if (! $templateEnabled) return;

        $rawAutomation = AppSetting::getByKey('birthday_sms_automation');
        $automation = is_string($rawAutomation) ? json_decode($rawAutomation, true) : $rawAutomation;
        $automation = array_merge([
            'enabled' => $legacyEnabled,
            'timing' => 'same_day',
            'days_before' => 0,
        ], is_array($automation) ? $automation : []);
        if (! (bool) $automation['enabled']) return;

        $template = trim((string) AppSetting::getByKey('birthday_sms_content', ''));
        if ($template === '') return;

        $today = Carbon::now('Asia/Tehran');
        $daysBefore = $automation['timing'] === 'days_before'
            ? max(1, min(30, (int) $automation['days_before']))
            : 0;
        $birthdayDate = $today->copy()->addDays($daysBefore);
        [, $jalaliMonth, $jalaliDay] = $this->gregorianToJalali($birthdayDate);
        $monthDays = collect([
            [(int) $birthdayDate->month, (int) $birthdayDate->day],
            [$jalaliMonth, $jalaliDay],
        ])->unique(fn (array $date) => $date[0].'-'.$date[1])->values();

        Patient::query()->whereNotNull('birth_date')->whereNotNull('phone')
            ->where(function ($query) use ($monthDays) {
                foreach ($monthDays as [$month, $day]) {
                    $query->orWhere(fn ($date) => $date->whereMonth('birth_date', $month)->whereDay('birth_date', $day));
                }
            })
            ->chunkById(100, function ($patients) use ($sms, $template, $today, $birthdayDate) {
                foreach ($patients as $patient) {
                    // ابتدا رکورد همان بیمار/سال را به‌صورت اتمیک رزرو می‌کنیم.
                    // کلید یکتا اجازه نمی‌دهد اجرای هم‌زمان Scheduler یا Queue پیام را دوبار بفرستد.
                    $reserved = DB::table('birthday_sms_logs')->insertOrIgnore([
                        'patient_id' => $patient->id,
                        'birthday_year' => $birthdayDate->year,
                        'recipient' => $patient->phone,
                        'sent_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if ($reserved !== 1) continue;
                    try {
                        $sms->sendTemplate($patient->phone, $template, [
                            trim($patient->first_name.' '.$patient->last_name),
                            trim((string) $patient->first_name),
                            (string) ($patient->file_number ?? ''),
                            (string) ($patient->birth_date ?? ''),
                            (string) AppSetting::getByKey('clinic_name', ''),
                            $today->format('Y/m/d'),
                        ]);
                    } catch (Throwable $e) {
                        // در خطای واقعی ارسال، رزرو آزاد می‌شود تا تلاش بعدی امکان‌پذیر باشد.
                        DB::table('birthday_sms_logs')
                            ->where('patient_id', $patient->id)
                            ->where('birthday_year', $birthdayDate->year)
                            ->delete();
                        report($e);
                    }
                }
            });
    }

    private function gregorianToJalali(Carbon $date): array
    {
        $gy = (int) $date->format('Y') - 1600;
        $gm = (int) $date->format('n') - 1;
        $gd = (int) $date->format('j') - 1;
        $gregorianMonthDays = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $jalaliMonthDays = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
        $days = 365 * $gy + intdiv($gy + 3, 4) - intdiv($gy + 99, 100) + intdiv($gy + 399, 400);
        for ($index = 0; $index < $gm; $index++) $days += $gregorianMonthDays[$index];
        if ($gm > 1 && (($gy + 1600) % 4 === 0 && (($gy + 1600) % 100 !== 0 || ($gy + 1600) % 400 === 0))) $days++;
        $days += $gd - 79;
        $cycle = intdiv($days, 12053);
        $days %= 12053;
        $jy = 979 + 33 * $cycle + 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days >= 366) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        for ($jm = 0; $jm < 11 && $days >= $jalaliMonthDays[$jm]; $jm++) $days -= $jalaliMonthDays[$jm];

        return [$jy, $jm + 1, $days + 1];
    }
}
