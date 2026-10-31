<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentAutomaticSms;
use App\Models\AutomaticSmsProject;
use App\Support\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppointmentAutomaticSmsService
{
    private const TERMINAL = ['sent', 'skipped_returned'];

    public function syncForAppointment(Appointment $appointment): void
    {
        if (! Schema::hasTable('appointment_automatic_sms') || ! $appointment->exists) return;

        $project = AutomaticSmsProject::query()
            ->where('is_active', true)
            ->with(['scenarios' => fn ($query) => $query->where('is_active', true)->with('steps')])
            ->first();

        $existing = AppointmentAutomaticSms::query()
            ->where('appointment_id', $appointment->id)
            ->get()
            ->keyBy('scenario_step_id');
        $matchedStepIds = [];

        foreach ($project?->scenarios ?? [] as $scenario) {
            $serviceName = $this->matchingServiceName($appointment, (string) $scenario->inventory_tag);
            if ($serviceName === null) continue;

            foreach ($scenario->steps as $step) {
                $matchedStepIds[] = (int) $step->id;
                $item = $existing->get($step->id);
                $attributes = [
                    'project_id' => $project->id,
                    'scenario_id' => $scenario->id,
                    'service_name' => $serviceName,
                    'inventory_tag' => $scenario->inventory_tag,
                    'sms_template' => trim((string) $step->sms_template),
                    'days_after' => (int) $step->days_after,
                    'send_at' => substr((string) $step->send_at, 0, 8),
                ];

                if (! $item) {
                    $item = new AppointmentAutomaticSms([
                        'appointment_id' => $appointment->id,
                        'scenario_step_id' => $step->id,
                        'is_enabled' => true,
                    ]);
                }

                if (! in_array($item->status, self::TERMINAL, true)) {
                    $attributes['scheduled_at'] = $this->scheduledAt($appointment, (int) $step->days_after, (string) $step->send_at);
                    $expiredCancellation = $item->exists
                        && $item->status === 'cancelled'
                        && $attributes['scheduled_at']
                        && $attributes['scheduled_at']->isPast();
                    $attributes['status'] = $expiredCancellation
                        ? 'cancelled'
                        : (! $item->is_enabled && $item->exists
                            ? 'disabled'
                            : ($attributes['scheduled_at'] ? 'scheduled' : 'waiting_completion'));
                    if ($expiredCancellation) $attributes['scheduled_at'] = null;
                    $attributes['last_error'] = null;
                }

                $item->fill($attributes)->save();
            }
        }

        AppointmentAutomaticSms::query()
            ->where('appointment_id', $appointment->id)
            ->when($matchedStepIds !== [], fn ($query) => $query->whereNotIn('scenario_step_id', $matchedStepIds))
            ->whereNotIn('status', self::TERMINAL)
            ->update(['status' => 'cancelled', 'scheduled_at' => null]);
    }

    public function resyncAllAppointments(): void
    {
        if (! Schema::hasTable('appointment_automatic_sms')) return;
        Appointment::query()
            ->where(function ($query): void {
                $query->whereHas('automaticSmsItems')
                    ->orWhereNull('completed_at')
                    ->orWhereNull('done')
                    ->orWhere('done', '!=', 'انجام شد');
            })
            ->orderBy('id')
            ->chunkById(100, function ($appointments): void {
            $appointments->each(fn (Appointment $appointment) => $this->syncForAppointment($appointment));
        });
    }

    public function decorateAppointments(Collection $appointments): Collection
    {
        if ($appointments->isEmpty() || ! Schema::hasTable('appointment_automatic_sms')) return $appointments;

        $items = AppointmentAutomaticSms::query()
            ->whereIn('appointment_id', $appointments->pluck('id')->filter())
            ->with(['project:id,is_active', 'scenario:id,is_active'])
            ->orderBy('inventory_tag')
            ->orderBy('days_after')
            ->orderBy('send_at')
            ->get()
            ->groupBy('appointment_id');

        return $appointments->each(function (Appointment $appointment) use ($items): void {
            $payload = ($items->get($appointment->id) ?? collect())
                ->map(fn (AppointmentAutomaticSms $item) => $this->payload($item))
                ->values();
            $appointment->setAttribute('automatic_sms', [
                'items' => $payload,
                'total_count' => $payload->count(),
                'active_count' => $payload->where('effective_enabled', true)->whereNotIn('status', self::TERMINAL)->count(),
                'sent_count' => $payload->where('status', 'sent')->count(),
                'failed_count' => $payload->where('status', 'failed')->count(),
                'has_active' => $payload->contains(fn ($item) => $item['effective_enabled'] && ! in_array($item['status'], self::TERMINAL, true)),
            ]);
        });
    }

    public function toggle(Appointment $appointment, AppointmentAutomaticSms $item, bool $enabled): array
    {
        abort_unless((int) $item->appointment_id === (int) $appointment->id, 404);
        abort_if($item->status === 'sent', 422, 'پیامک ارسال‌شده قابل تغییر نیست.');

        $item->is_enabled = $enabled;
        $item->disabled_at = $enabled ? null : now();
        $item->last_error = null;
        if ($enabled) {
            $item->scheduled_at = $this->scheduledAt($appointment, $item->days_after, (string) $item->send_at);
            $item->status = $item->scheduled_at ? 'scheduled' : 'waiting_completion';
        } else {
            $item->status = 'disabled';
        }
        $item->save();
        $item->load(['project:id,is_active', 'scenario:id,is_active']);

        return $this->payload($item);
    }

    public function sendDueForCurrentTenant(ShsmsService $sms): array
    {
        if (! Schema::hasTable('appointment_automatic_sms')) return ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        // A worker may stop after claiming an item but before persisting the provider result.
        // Return old claims to the retry queue instead of leaving them blocked forever.
        AppointmentAutomaticSms::query()
            ->where('status', 'sending')
            ->where('updated_at', '<=', now()->subMinutes(10))
            ->update([
                'status' => 'failed',
                'last_error' => 'ارسال قبلی کامل نشد و برای تلاش مجدد آزاد شد.',
            ]);

        AppointmentAutomaticSms::query()
            ->where('is_enabled', true)
            ->whereIn('status', ['scheduled', 'failed'])
            ->where('attempts', '<', 3)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(200)
            ->pluck('id')
            ->each(function (int $id) use ($sms, &$stats): void {
                $item = DB::transaction(function () use ($id) {
                    $locked = AppointmentAutomaticSms::query()->lockForUpdate()->with(['appointment', 'project', 'scenario'])->find($id);
                    if (! $locked || ! $locked->is_enabled || ! in_array($locked->status, ['scheduled', 'failed'], true)) return null;
                    if (! $locked->project?->is_active || ! $locked->scenario?->is_active) {
                        $locked->update(['status' => 'cancelled', 'last_error' => 'پروژه یا سناریو غیرفعال است.']);
                        return null;
                    }
                    $locked->update(['status' => 'sending', 'attempts' => $locked->attempts + 1]);
                    return $locked->fresh(['appointment', 'project', 'scenario']);
                });

                if (! $item) { $stats['skipped']++; return; }
                if ($this->patientReturnedForTag($item)) {
                    $item->update(['status' => 'skipped_returned', 'last_error' => null]);
                    $stats['skipped']++;
                    return;
                }

                try {
                    $appointment = $item->appointment;
                    abort_unless($appointment && filled($appointment->phone), 422, 'شماره موبایل مراجعه‌کننده ثبت نشده است.');
                    abort_unless(filled($item->sms_template), 422, 'نام الگوی پیامک ثبت نشده است.');
                    $sms->sendTemplate($appointment->phone, $item->sms_template, [
                        trim((string) $appointment->lastname),
                        $this->appointmentDate($appointment),
                        $item->service_name ?: $item->inventory_tag,
                        tenant()?->name ?? tenant('id') ?? 'کلینیک',
                    ]);
                    $item->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
                    ActivityLogger::manual('automatic_sms_sent', 'پیامک اتوماتیک', $item, [], [
                        'appointment_id' => $appointment->id,
                        'inventory_tag' => $item->inventory_tag,
                        'template' => $item->sms_template,
                    ]);
                    $stats['sent']++;
                } catch (\Throwable $error) {
                    $item->update(['status' => 'failed', 'last_error' => mb_substr($error->getMessage(), 0, 2000)]);
                    $stats['failed']++;
                }
            });

        return $stats;
    }

    private function scheduledAt(Appointment $appointment, int $daysAfter, string $sendAt): ?Carbon
    {
        if (! $appointment->completed_at || trim((string) $appointment->done) !== 'انجام شد') return null;
        [$hour, $minute, $second] = array_pad(array_map('intval', explode(':', $sendAt)), 3, 0);
        return Carbon::parse($appointment->completed_at, config('app.timezone'))->timezone('Asia/Tehran')
            ->startOfDay()->addDays($daysAfter)->setTime($hour, $minute, $second);
    }

    private function matchingServiceName(Appointment $appointment, string $tag): ?string
    {
        foreach ($appointment->services ?? [] as $service) {
            if (! is_array($service)) continue;
            $name = trim((string) ($service['name'] ?? ''));
            $tags = collect($service['tags'] ?? [])->map(fn ($value) => trim((string) $value));
            if ($name === $tag || $tags->contains($tag)) return $name ?: $tag;
        }
        return null;
    }

    private function patientReturnedForTag(AppointmentAutomaticSms $item): bool
    {
        $source = $item->appointment;
        if (! $source) return true;
        $identity = trim((string) $source->file_number);
        $phone = trim((string) $source->phone);
        if ($identity === '' && $phone === '') return false;
        $sourceVisitAt = Carbon::parse(
            $source->completed_at ?: $source->arrived_at ?: $source->created_at,
            config('app.timezone')
        );

        return Appointment::query()
            ->whereKeyNot($source->id)
            ->where(function ($query) use ($identity, $phone): void {
                if ($identity !== '') $query->where('file_number', $identity);
                if ($phone !== '') $query->{$identity !== '' ? 'orWhere' : 'where'}('phone', $phone);
            })
            ->where(function ($query) use ($sourceVisitAt): void {
                $query->where('arrived_at', '>', $sourceVisitAt)
                    ->orWhere('completed_at', '>', $sourceVisitAt)
                    ->orWhere(function ($statusQuery) use ($sourceVisitAt): void {
                        $statusQuery->where('status', 'آمد')->where('created_at', '>', $sourceVisitAt);
                    });
            })
            ->get(['services'])
            ->contains(fn (Appointment $appointment) => $this->matchingServiceName($appointment, $item->inventory_tag) !== null);
    }

    private function payload(AppointmentAutomaticSms $item): array
    {
        $globalActive = (bool) ($item->project?->is_active && $item->scenario?->is_active);
        return [
            'id' => $item->id,
            'project_id' => $item->project_id,
            'scenario_id' => $item->scenario_id,
            'scenario_step_id' => $item->scenario_step_id,
            'service_name' => $item->service_name,
            'inventory_tag' => $item->inventory_tag,
            'sms_template' => $item->sms_template,
            'days_after' => $item->days_after,
            'send_at' => substr((string) $item->send_at, 0, 5),
            'scheduled_at' => $item->scheduled_at?->toISOString(),
            'is_enabled' => $item->is_enabled,
            'effective_enabled' => $item->is_enabled && $globalActive && ! in_array($item->status, ['disabled', 'cancelled'], true),
            'status' => $item->status,
            'sent_at' => $item->sent_at?->toISOString(),
            'attempts' => $item->attempts,
            'last_error' => $item->last_error,
        ];
    }

    private function appointmentDate(Appointment $appointment): string
    {
        return trim(sprintf('%s/%02d', str_replace('-', '/', (string) $appointment->month), (int) $appointment->day_num), '/');
    }
}
