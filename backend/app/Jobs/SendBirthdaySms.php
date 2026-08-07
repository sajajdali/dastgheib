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
        if (AppSetting::getByKey('birthday_sms_enabled', '0') !== '1') return;
        $template = trim((string) AppSetting::getByKey('birthday_sms_content', ''));
        if ($template === '') return;

        $today = Carbon::now('Asia/Tehran');
        Patient::query()->whereNotNull('birth_date')->whereNotNull('phone')
            ->whereMonth('birth_date', $today->month)->whereDay('birth_date', $today->day)
            ->chunkById(100, function ($patients) use ($sms, $template, $today) {
                foreach ($patients as $patient) {
                    $exists = DB::table('birthday_sms_logs')->where('patient_id', $patient->id)->where('birthday_year', $today->year)->exists();
                    if ($exists) continue;
                    try {
                        $sms->sendTemplate($patient->phone, $template, [
                            trim($patient->first_name.' '.$patient->last_name),
                            (string) AppSetting::getByKey('clinic_name', ''),
                        ]);
                        DB::table('birthday_sms_logs')->insert(['patient_id'=>$patient->id,'birthday_year'=>$today->year,'recipient'=>$patient->phone,'sent_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
                    } catch (Throwable $e) { report($e); }
                }
            });
    }
}
