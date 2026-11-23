<?php

namespace App\Jobs;

use App\Models\AppSetting;
use App\Models\Appointment;
use App\Reporting\DynamicReports\JalaliMonthWindow;
use App\Services\ShsmsService;
use App\Support\ActivityLogger;
use App\Support\AppointmentStatuses;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Day-before appointment reminder SMS.
 * Runs globally for every clinic at a fixed time; no per-tenant toggle.
 * Sends only when status is exactly «وقت داده شد» at send time (once per appointment).
 */
class SendAppointmentReminderSms implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public const SEND_AT = '10:00';

    public function handle(ShsmsService $sms): void
    {
        if (! Schema::hasTable('appointment_reminder_sms_logs') || ! Schema::hasTable('appointments')) {
            return;
        }

        $template = $this->activeTemplate();
        if ($template === '') {
            return;
        }

        $tomorrow = now('Asia/Tehran')->addDay();
        $jalali = (new JalaliMonthWindow())->date($tomorrow);
        $month = substr($jalali, 0, 7);
        $dayNum = (int) substr($jalali, 8, 2);
        $dateLabel = sprintf('%s/%02d', str_replace('-', '/', $month), $dayNum);

        Appointment::query()
            ->where('month', $month)
            ->where('day_num', $dayNum)
            ->where('status', AppointmentStatuses::SCHEDULED)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($appointments) use ($sms, $template, $dateLabel): void {
                foreach ($appointments as $appointment) {
                    if (! AppointmentStatuses::isConfirmedScheduled($appointment->status)) {
                        continue;
                    }

                    $phone = trim((string) $appointment->phone);
                    if (! preg_match('/^09\d{9}$/', $phone)) {
                        continue;
                    }

                    $reserved = DB::table('appointment_reminder_sms_logs')->insertOrIgnore([
                        'appointment_id' => $appointment->id,
                        'recipient' => $phone,
                        'sent_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    if ($reserved !== 1) {
                        continue;
                    }

                    try {
                        $fresh = Appointment::query()->find($appointment->id);
                        if (! $fresh || ! AppointmentStatuses::isConfirmedScheduled($fresh->status)) {
                            DB::table('appointment_reminder_sms_logs')
                                ->where('appointment_id', $appointment->id)
                                ->delete();
                            continue;
                        }

                        $sms->sendTemplate($phone, $template, [
                            trim((string) ($fresh->lastname ?? '')),
                            $dateLabel,
                            substr((string) ($fresh->time ?? ''), 0, 5),
                        ]);
                        ActivityLogger::manual('sms_sent', 'پیامک', $fresh, [], [
                            'type' => 'appointment_reminder',
                            'recipient' => $phone,
                            'patient_name' => $fresh->lastname,
                            'date' => $dateLabel,
                            'time' => substr((string) ($fresh->time ?? ''), 0, 5),
                        ]);
                    } catch (Throwable $error) {
                        DB::table('appointment_reminder_sms_logs')
                            ->where('appointment_id', $appointment->id)
                            ->delete();
                        report($error);
                    }
                }
            });
    }

    private function activeTemplate(): string
    {
        // یادآوری برای همه کلینیک‌ها فعال است؛ فقط نام الگوی SHSMS لازم است (بدون کلید on/off).
        $templates = collect(json_decode((string) AppSetting::getByKey('sms_templates', '[]'), true));
        $configured = $templates->first(fn ($item) => ($item['category'] ?? '') === 'appointment_reminder');

        return trim((string) ($configured['content'] ?? AppSetting::getByKey('sms_appointment_reminder', '')));
    }
}
