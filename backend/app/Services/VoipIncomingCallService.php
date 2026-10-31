<?php

namespace App\Services;

use App\Models\Patient;
use App\Reporting\DynamicReports\JalaliMonthWindow;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class VoipIncomingCallService
{
    public const EVENTS = [
        'call.started', 'call.ivr', 'call.queued', 'call.ringing', 'call.answered',
        'call.transferred', 'survey.started', 'survey.completed', 'voicemail.left', 'call.ended',
    ];

    public function record(array $event, string $rawBody): void
    {
        if (! in_array($event['event'], self::EVENTS, true) || ($event['test'] ?? false)) {
            return;
        }

        try {
            DB::transaction(function () use ($event, $rawBody): void {
                $data = $event['data'];
                $phone = $this->normalizePhone($data['phone'] ?? $data['callerNumber'] ?? null);
                DB::table('voip_call_events')->insert([
                    'event_id' => $event['eventId'],
                    'call_id' => $event['callId'],
                    'seq' => $event['seq'],
                    'event' => $event['event'],
                    'occurred_at' => $this->utc($event['occurredAt']),
                    'phone' => $phone,
                    'payload' => $rawBody,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->updateCall($event, $phone);
            });
        } catch (UniqueConstraintViolationException $exception) {
            // The call center retries the same eventId when a response is lost.
            if (DB::table('voip_call_events')->where('event_id', $event['eventId'])->exists()) {
                return;
            }
            throw $exception;
        }
    }

    public function recent(int $limit = 40): array
    {
        return DB::table('voip_calls')->orderByDesc('last_event_at')->orderByDesc('id')
            ->limit($limit)->get()->map(fn ($call) => [
                'call_id' => $call->call_id,
                'phone' => $call->phone,
                'patient_id' => $call->patient_id,
                'patient_name' => $call->patient_name,
                'state' => $call->state,
                'status' => $call->status,
                'queue' => $call->queue,
                'operator' => $call->operator,
                'started_at' => $this->iso($call->started_at),
                'ended_at' => $this->iso($call->ended_at),
                'last_event_at' => $this->iso($call->last_event_at),
            ])->all();
    }

    public function live(): array
    {
        $calls = DB::table('voip_calls')
            ->whereNull('ended_at')
            ->where('last_event_at', '>=', now('UTC')->subHours(6)->format('Y-m-d H:i:s'))
            ->orderByDesc('last_event_at')->limit(40)->get();
        if ($calls->isEmpty()) return [];

        $phones = $calls->pluck('phone')->filter()->unique()->values()->all();
        $patientIds = $calls->pluck('patient_id')->filter()->unique()->values()->all();
        $patients = Patient::query()->whereIn('id', $patientIds)
            ->get(['id', 'file_number', 'profile_photo_path', 'profile_thumbnail_path'])->keyBy('id');
        $fileNumbers = $patients->pluck('file_number')->filter()->unique()->values()->all();
        $appointments = $fileNumbers === [] ? collect() : DB::table('appointments')
            ->whereIn('file_number', $fileNumbers)->get(['file_number', 'month', 'day_num', 'time', 'status'])
            ->groupBy('file_number');
        $today = (new JalaliMonthWindow)->endingToday(1, now('Asia/Tehran'))['to'];

        $totalCounts = $phones === [] ? collect() : DB::table('voip_calls')
            ->whereIn('phone', $phones)->select('phone', DB::raw('COUNT(*) as total'))
            ->groupBy('phone')->pluck('total', 'phone');
        $todayStart = now('Asia/Tehran')->startOfDay()->utc()->format('Y-m-d H:i:s');
        $todayCounts = $phones === [] ? collect() : DB::table('voip_calls')
            ->whereIn('phone', $phones)->where('started_at', '>=', $todayStart)
            ->select('phone', DB::raw('COUNT(*) as total'))->groupBy('phone')->pluck('total', 'phone');
        $milestones = DB::table('voip_call_events')->whereIn('call_id', $calls->pluck('call_id'))
            ->whereIn('event', ['call.answered', 'survey.started', 'survey.completed', 'call.transferred'])
            ->orderBy('seq')->get(['call_id', 'event', 'occurred_at', 'payload'])->groupBy('call_id');

        return $calls->map(function ($call) use ($patients, $appointments, $today, $totalCounts, $todayCounts, $milestones): array {
            $patient = $patients->get($call->patient_id);
            $visits = $patient ? ($appointments->get($patient->file_number) ?? collect()) : collect();
            $next = $visits->filter(function ($appointment) use ($today): bool {
                $date = sprintf('%s-%02d', (string) $appointment->month, (int) $appointment->day_num);
                return $date >= $today && ! preg_match('/کنسل|لغو|cancel/u', (string) $appointment->status);
            })->sortBy(fn ($appointment) => sprintf('%s-%02d %s', $appointment->month, $appointment->day_num, $appointment->time))->first();
            $events = $milestones->get($call->call_id) ?? collect();
            $answered = $events->firstWhere('event', 'call.answered');
            $survey = $events->first(fn ($event) => in_array($event->event, ['survey.started', 'survey.completed'], true));
            $lastAnswer = $events->where('event', 'call.answered')->last();
            $answerData = $lastAnswer ? json_decode($lastAnswer->payload, true)['data'] ?? [] : [];

            return [
                'id' => $call->call_id,
                'phone' => $call->phone,
                'stage' => match ($call->state) {
                    'در حال مکالمه' => 'talking',
                    'نظرسنجی', 'پیام صوتی' => 'survey',
                    'در صف انتظار', 'در حال زنگ خوردن', 'منتقل‌شده' => 'queue',
                    default => 'ivr',
                },
                'startedAt' => $this->iso($call->started_at ?: $call->last_event_at),
                'connectedAt' => $answered ? $this->iso($answered->occurred_at) : null,
                'talkEndedAt' => $survey ? $this->iso($survey->occurred_at) : null,
                'queueName' => $call->queue,
                'agentName' => $call->operator,
                'extension' => $answerData['extension'] ?? null,
                'patient' => $patient ? [
                    'id' => $patient->id,
                    'name' => $call->patient_name,
                    'avatarUrl' => $patient->avatar_url,
                    'photoUrl' => $patient->profile_photo_url,
                    'fileNo' => $patient->file_number,
                    'appointmentsCount' => $visits->count(),
                    'nextAppointment' => $next ? str_replace('-', '/', $next->month).'/'.sprintf('%02d', $next->day_num).($next->time ? ' · '.$next->time : '') : null,
                ] : null,
                'callsToday' => (int) ($todayCounts->get($call->phone) ?? 1),
                'callsTotal' => (int) ($totalCounts->get($call->phone) ?? 1),
            ];
        })->all();
    }

    private function updateCall(array $event, ?string $eventPhone): void
    {
        $data = $event['data'];
        $type = $event['event'];
        $call = DB::table('voip_calls')->where('call_id', $event['callId'])->lockForUpdate()->first();

        if (! $call && ! in_array($type, ['call.started', 'call.ended'], true)) return;
        if (in_array($type, ['call.started', 'call.ended'], true) && ($data['direction'] ?? '') !== 'inbound') return;
        if ($call && (int) $event['seq'] <= (int) $call->latest_seq) return;

        $phone = $eventPhone ?: $call?->phone;
        $patient = null;
        if ($phone && (! $call || $call->phone !== $phone)) {
            $patient = Patient::query()->where('phone', $phone)->orderBy('id')->first(['id', 'first_name', 'last_name']);
        }

        $operator = $data['operator'] ?? null;
        if (is_array($operator)) $operator = $operator['name'] ?? $operator['extension'] ?? null;
        $changes = [
            'phone' => $phone,
            'patient_id' => $patient?->id ?? $call?->patient_id,
            'patient_name' => $patient
                ? trim($patient->first_name.' '.$patient->last_name)
                : ($call?->patient_name ?: ($data['patientName'] ?? null)),
            'state' => $type === 'call.ended'
                ? ($data['statusLabel'] ?? $this->state($type))
                : $this->state($type),
            'status' => $type === 'call.ended' ? ($data['status'] ?? null) : $call?->status,
            'queue' => $data['queue'] ?? $call?->queue,
            'operator' => is_string($operator) && $operator !== '' ? $operator : $call?->operator,
            'latest_seq' => $event['seq'],
            'started_at' => $type === 'call.ended' && ! empty($data['startedAt'])
                ? $this->utc($data['startedAt'])
                : ($call?->started_at ?? ($type === 'call.started' ? $this->utc($event['occurredAt']) : null)),
            'ended_at' => $type === 'call.ended' ? $this->utc($data['endedAt'] ?? $event['occurredAt']) : $call?->ended_at,
            'last_event_at' => $this->utc($event['occurredAt']),
            'updated_at' => now(),
        ];

        if ($call) {
            DB::table('voip_calls')->where('id', $call->id)->update($changes);
        } else {
            DB::table('voip_calls')->insert(['call_id' => $event['callId'], ...$changes, 'created_at' => now()]);
        }
    }

    private function state(string $event): string
    {
        return match ($event) {
            'call.started' => 'شروع تماس',
            'call.ivr' => 'منوی تلفنی',
            'call.queued' => 'در صف انتظار',
            'call.ringing' => 'در حال زنگ خوردن',
            'call.answered' => 'در حال مکالمه',
            'call.transferred' => 'منتقل‌شده',
            'survey.started', 'survey.completed' => 'نظرسنجی',
            'voicemail.left' => 'پیام صوتی',
            default => 'پایان‌یافته',
        };
    }

    private function normalizePhone(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') return null;
        $digits = preg_replace('/\D/', '', strtr($value, [
            '۰'=>'0', '۱'=>'1', '۲'=>'2', '۳'=>'3', '۴'=>'4', '۵'=>'5', '۶'=>'6', '۷'=>'7', '۸'=>'8', '۹'=>'9',
            '٠'=>'0', '١'=>'1', '٢'=>'2', '٣'=>'3', '٤'=>'4', '٥'=>'5', '٦'=>'6', '٧'=>'7', '٨'=>'8', '٩'=>'9',
        ]));
        if (str_starts_with($digits, '98') && strlen($digits) === 12) $digits = '0'.substr($digits, 2);
        if (strlen($digits) === 10 && str_starts_with($digits, '9')) $digits = '0'.$digits;

        return $digits !== '' ? substr($digits, 0, 30) : null;
    }

    private function utc(string $value): string
    {
        return Carbon::parse($value)->utc()->format('Y-m-d H:i:s.v');
    }

    private function iso(?string $value): ?string
    {
        return $value ? Carbon::parse($value, 'UTC')->toIso8601String() : null;
    }
}
