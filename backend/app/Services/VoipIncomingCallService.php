<?php

namespace App\Services;

use App\Models\Patient;
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
