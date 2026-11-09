<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\AutomaticSmsProject;
use App\Models\AutomaticSmsScenario;
use App\Models\FollowupAutomaticSms;
use App\Reporting\DynamicReports\JalaliMonthWindow;
use App\Support\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * پیامک اتوماتیک پیگیری: کاربر در پیگیری برای یک مخاطب یک یا چند سناریو از پروژهٔ فعال انتخاب می‌کند،
 * مرحله‌ها از روز انتخاب زمان‌بندی می‌شوند و کسی که بعداً نوبت ثبت می‌کند می‌تواند هر سناریو را خاموش کند.
 */
class FollowupAutomaticSmsService
{
    private const PENDING = ['scheduled', 'failed', 'disabled'];

    /** سناریوهای فعالِ پروژه‌ای که مدیر فعال کرده است. */
    public function activeScenarios(): Collection
    {
        if (! Schema::hasTable('automatic_sms_projects')) return collect();
        $project = AutomaticSmsProject::query()->where('is_active', true)
            ->with(['scenarios' => fn ($query) => $query->where('is_active', true)->with('steps')])
            ->first();

        return collect($project?->scenarios ?? [])->map(fn (AutomaticSmsScenario $scenario) => [
            'id' => $scenario->id,
            'name' => $scenario->name,
            'inventory_tag' => $scenario->inventory_tag,
            'project_id' => $project->id,
            'steps' => $scenario->steps->sortBy('sort_order')->values()->map(fn ($step) => [
                'days_after' => (int) $step->days_after,
                'send_at' => substr((string) $step->send_at, 0, 5),
            ])->all(),
        ])->values();
    }

    /**
     * سناریوهای انتخاب‌شدهٔ یک ردیف پیگیری را با سرور یکی می‌کند: سناریوی تازه زمان‌بندی می‌شود و
     * سناریویی که برداشته شده، پیامک‌های ارسال‌نشده‌اش لغو می‌شود.
     */
    public function syncRow(?int $campaignId, string $rowKey, string $phone, ?string $patientName, array $scenarioIds, $user): array
    {
        $phone = self::normalizePhone($phone);
        abort_if($phone === '', 422, 'شماره موبایل این مخاطب معتبر نیست.');
        $scenarioIds = collect($scenarioIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        DB::transaction(function () use ($campaignId, $rowKey, $phone, $patientName, $scenarioIds, $user) {
            $rowItems = FollowupAutomaticSms::query()->where('campaign_id', $campaignId)->where('followup_row_key', $rowKey)
                ->lockForUpdate()->get();

            // سناریوهایی که از این ردیف برداشته شده‌اند.
            $rowItems->whereIn('status', self::PENDING)->reject(fn ($item) => $scenarioIds->contains((int) $item->scenario_id))
                ->each(fn (FollowupAutomaticSms $item) => $item->update(['status' => 'cancelled', 'is_enabled' => false, 'disabled_at' => now(), 'disabled_by_name' => $user?->name]));

            $active = $this->activeScenarios()->keyBy('id');
            foreach ($scenarioIds as $scenarioId) {
                if (! $active->has($scenarioId)) continue;
                // سناریویی که برای همین ردیف هنوز در جریان است (حتی اگر هنگام نوبت خاموش شده باشد) دوباره ساخته نمی‌شود؛
                // و اگر برای همین شماره از ردیف دیگری فعال باشد هم تکراری نمی‌شود.
                $inRow = $rowItems->where('scenario_id', $scenarioId)->whereIn('status', self::PENDING)->isNotEmpty();
                $forPhone = FollowupAutomaticSms::query()->where('phone', $phone)->where('scenario_id', $scenarioId)
                    ->where('status', 'scheduled')->where('is_enabled', true)->exists();
                if ($inRow || $forPhone) continue;
                $this->enroll($campaignId, $rowKey, $phone, $patientName, AutomaticSmsScenario::query()->with('steps')->findOrFail($scenarioId), $user);
            }
        });

        return $this->enrollmentsFor(FollowupAutomaticSms::query()->where('campaign_id', $campaignId)->where('followup_row_key', $rowKey)->get());
    }

    private function enroll(?int $campaignId, string $rowKey, string $phone, ?string $patientName, AutomaticSmsScenario $scenario, $user): void
    {
        $key = (string) Str::uuid();
        $today = now('Asia/Tehran')->startOfDay();
        foreach ($scenario->steps->sortBy('sort_order') as $step) {
            [$hour, $minute] = array_pad(array_map('intval', explode(':', (string) $step->send_at)), 2, 0);
            $scheduled = $today->copy()->addDays(max(0, (int) $step->days_after))->setTime($hour, $minute);
            if ($scheduled->isPast()) $scheduled = now('Asia/Tehran')->addMinute();
            FollowupAutomaticSms::query()->create([
                'enrollment_key' => $key, 'campaign_id' => $campaignId, 'followup_row_key' => $rowKey,
                'phone' => $phone, 'patient_name' => trim((string) $patientName) ?: null,
                'project_id' => $scenario->project_id, 'scenario_id' => $scenario->id, 'scenario_step_id' => $step->id,
                'scenario_name' => $scenario->name, 'inventory_tag' => $scenario->inventory_tag,
                'sms_template' => trim((string) $step->sms_template), 'days_after' => (int) $step->days_after,
                'send_at' => substr((string) $step->send_at, 0, 8),
                'scheduled_at' => $scheduled->timezone(config('app.timezone')),
                'status' => 'scheduled', 'is_enabled' => true,
                'created_by' => $user?->id, 'created_by_name' => $user?->name,
            ]);
        }
        ActivityLogger::manual('automatic_sms_enrolled', 'پیامک اتوماتیک', null, [], [
            'phone' => $phone, 'scenario' => $scenario->name, 'campaign_id' => $campaignId,
        ]);
    }

    /** سناریوهای هر ردیف یک کمپین پیگیری، با کلید ردیف. */
    public function forCampaign(?int $campaignId): array
    {
        return FollowupAutomaticSms::query()->where('campaign_id', $campaignId)->get()
            ->groupBy('followup_row_key')
            ->map(fn (Collection $items) => $this->enrollmentsFor($items))
            ->all();
    }

    /** سناریوهای در جریانِ هر شماره (فعال یا خاموش‌شده با پیامک ارسال‌نشده)، با کلید شمارهٔ نرمال‌شده. */
    public function pendingForPhones(Collection $phones): Collection
    {
        $phones = $phones->map(fn ($phone) => self::normalizePhone($phone))->filter()->unique()->values();
        if ($phones->isEmpty() || ! Schema::hasTable('followup_automatic_sms')) return collect();

        $keys = FollowupAutomaticSms::query()->whereIn('phone', $phones)->whereIn('status', self::PENDING)
            ->distinct()->pluck('enrollment_key');
        if ($keys->isEmpty()) return collect();

        return FollowupAutomaticSms::query()->whereIn('enrollment_key', $keys)->get()
            ->groupBy('phone')
            ->map(fn (Collection $items) => $this->enrollmentsFor($items));
    }

    /** خاموش یا روشن کردن یک سناریو برای یک مخاطب؛ پیامک‌های ارسال‌شده دست نمی‌خورند. */
    public function toggle(string $enrollmentKey, bool $enabled, $user): array
    {
        $items = FollowupAutomaticSms::query()->where('enrollment_key', $enrollmentKey)->get();
        abort_if($items->isEmpty(), 404, 'این سناریو پیدا نشد.');
        $items->whereIn('status', self::PENDING)->each(function (FollowupAutomaticSms $item) use ($enabled, $user) {
            $item->update($enabled
                ? ['is_enabled' => true, 'status' => 'scheduled', 'disabled_at' => null, 'disabled_by_name' => null, 'last_error' => null]
                : ['is_enabled' => false, 'status' => 'disabled', 'disabled_at' => now(), 'disabled_by_name' => $user?->name]);
        });
        ActivityLogger::manual($enabled ? 'automatic_sms_enabled' : 'automatic_sms_disabled', 'پیامک اتوماتیک', null, [], [
            'phone' => $items->first()->phone, 'scenario' => $items->first()->scenario_name,
        ]);

        return $this->enrollmentsFor(FollowupAutomaticSms::query()->where('enrollment_key', $enrollmentKey)->get())[0];
    }

    /** خلاصهٔ هر سناریوی فعال‌شده: چند پیامک رفته، پیامک بعدی کی است و آیا هنوز در جریان است. */
    public function enrollmentsFor(Collection $items): array
    {
        return $items->groupBy('enrollment_key')->map(function (Collection $steps, string $key) {
            $first = $steps->sortBy('days_after')->first();
            $pending = $steps->whereIn('status', self::PENDING);
            $next = $steps->where('status', 'scheduled')->where('is_enabled', true)->sortBy('scheduled_at')->first();
            $status = $pending->isEmpty()
                ? ($steps->where('status', 'sent')->isNotEmpty() ? 'completed' : 'cancelled')
                : ($pending->where('is_enabled', true)->isNotEmpty() ? 'active' : 'disabled');

            return [
                'enrollment_key' => $key,
                'scenario_id' => $first->scenario_id,
                'scenario_name' => $first->scenario_name,
                'inventory_tag' => $first->inventory_tag,
                'phone' => $first->phone,
                'status' => $status,
                'steps_total' => $steps->count(),
                'steps_sent' => $steps->where('status', 'sent')->count(),
                'steps_failed' => $steps->where('status', 'failed')->count(),
                'next_at' => $next?->scheduled_at?->toISOString(),
                'enrolled_at' => $first->created_at?->toISOString(),
                'enrolled_by' => $first->created_by_name,
                'disabled_by' => $steps->whereNotNull('disabled_by_name')->first()?->disabled_by_name,
                'steps' => $steps->sortBy('days_after')->values()->map(fn ($step) => [
                    'days_after' => $step->days_after, 'send_at' => substr((string) $step->send_at, 0, 5),
                    'status' => $step->status, 'scheduled_at' => $step->scheduled_at?->toISOString(), 'sent_at' => $step->sent_at?->toISOString(),
                ])->all(),
            ];
        })->sortByDesc('enrolled_at')->values()->all();
    }

    public function sendDueForCurrentTenant(ShsmsService $sms): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        if (! Schema::hasTable('followup_automatic_sms')) return $stats;

        FollowupAutomaticSms::query()->where('status', 'sending')->where('updated_at', '<=', now()->subMinutes(10))
            ->update(['status' => 'failed', 'last_error' => 'ارسال قبلی کامل نشد و برای تلاش مجدد آزاد شد.']);

        FollowupAutomaticSms::query()->where('is_enabled', true)->whereIn('status', ['scheduled', 'failed'])
            ->where('attempts', '<', 3)->whereNotNull('scheduled_at')->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')->limit(200)->pluck('id')
            ->each(function (int $id) use ($sms, &$stats): void {
                $item = DB::transaction(function () use ($id) {
                    $locked = FollowupAutomaticSms::query()->lockForUpdate()->with(['project', 'scenario'])->find($id);
                    if (! $locked || ! $locked->is_enabled || ! in_array($locked->status, ['scheduled', 'failed'], true)) return null;
                    if (! $locked->project?->is_active || ! $locked->scenario?->is_active) {
                        $locked->update(['status' => 'cancelled', 'last_error' => 'پروژه یا سناریو غیرفعال است.']);
                        return null;
                    }
                    $locked->update(['status' => 'sending', 'attempts' => $locked->attempts + 1]);
                    return $locked->fresh();
                });
                if (! $item) { $stats['skipped']++; return; }

                try {
                    abort_unless(preg_match('/^09\d{9}$/', $item->phone), 422, 'شماره موبایل مخاطب معتبر نیست.');
                    abort_unless(filled($item->sms_template), 422, 'نام الگوی پیامک ثبت نشده است.');
                    // پارامترها: نام مخاطب، تاریخ فعال‌سازی در پیگیری، ناحیه / مشکل، نام مجموعه.
                    $sms->sendTemplate($item->phone, $item->sms_template, [
                        (string) $item->patient_name,
                        str_replace('-', '/', (new JalaliMonthWindow())->date(Carbon::parse($item->created_at)->timezone('Asia/Tehran'))),
                        (string) ($item->inventory_tag ?: $item->scenario_name),
                        AppSetting::clinicName(),
                    ]);
                    $item->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
                    ActivityLogger::manual('automatic_sms_sent', 'پیامک اتوماتیک', null, [], [
                        'phone' => $item->phone, 'scenario' => $item->scenario_name, 'template' => $item->sms_template,
                    ]);
                    $stats['sent']++;
                } catch (\Throwable $error) {
                    $item->update(['status' => 'failed', 'last_error' => mb_substr($error->getMessage(), 0, 2000)]);
                    $stats['failed']++;
                }
            });

        return $stats;
    }

    public static function normalizePhone(mixed $phone): string
    {
        $digits = preg_replace('/\D+/', '', strtr((string) $phone, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ])) ?: '';
        if (str_starts_with($digits, '0098')) $digits = '0'.substr($digits, 4);
        elseif (str_starts_with($digits, '98') && strlen($digits) === 12) $digits = '0'.substr($digits, 2);
        elseif (strlen($digits) === 10 && str_starts_with($digits, '9')) $digits = '0'.$digits;

        return $digits;
    }
}
