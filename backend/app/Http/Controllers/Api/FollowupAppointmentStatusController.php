<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\FollowupAutomaticSmsService;
use Illuminate\Http\Request;

/**
 * وضعیت «انجام کار» نوبت‌هایی که از ستون «ثبت نوبت» پیگیری‌ها گرفته شده‌اند.
 * ردیف جدید شناسهٔ نوبت را دارد؛ ردیف‌های قدیمی با شماره تلفن و روز نوبت پیدا می‌شوند.
 */
class FollowupAppointmentStatusController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'max:2000'],
            'items.*.key' => ['required', 'string', 'max:120'],
            'items.*.appointment_id' => ['nullable', 'integer', 'min:1'],
            'items.*.phone' => ['nullable', 'string', 'max:50'],
            'items.*.month' => ['nullable', 'string', 'regex:/^1[34]\d{2}-(0[1-9]|1[0-2])$/'],
            'items.*.day_num' => ['nullable', 'integer', 'between:1,31'],
            'items.*.time' => ['nullable', 'string', 'max:10'],
        ]);

        $items = collect($data['items']);
        $columns = ['id', 'month', 'day_num', 'time', 'phone', 'done', 'completed_at'];

        $byId = Appointment::query()
            ->whereIn('id', $items->pluck('appointment_id')->filter()->unique()->values())
            ->get($columns)
            ->keyBy('id');

        // ردیف‌های قدیمی شناسهٔ نوبت ندارند: نوبت همان روز با همان شماره تلفن.
        $legacy = $items->filter(fn ($item) => empty($item['appointment_id'])
            && ! empty($item['month']) && ! empty($item['day_num'])
            && FollowupAutomaticSmsService::normalizePhone($item['phone'] ?? '') !== '');
        $byDay = collect();
        if ($legacy->isNotEmpty()) {
            $byDay = Appointment::query()
                ->where(function ($query) use ($legacy) {
                    $legacy->map(fn ($item) => [$item['month'], (int) $item['day_num']])->unique()
                        ->each(fn ($day) => $query->orWhere(fn ($q) => $q->where('month', $day[0])->where('day_num', $day[1])));
                })
                ->whereNotNull('phone')
                ->get($columns)
                ->groupBy(fn (Appointment $appointment) => $appointment->month.'|'.(int) $appointment->day_num);
        }

        $statuses = [];
        foreach ($items as $item) {
            $appointment = ! empty($item['appointment_id'])
                ? $byId->get((int) $item['appointment_id'])
                : $this->matchLegacy($byDay, $item);
            if (! $appointment) continue;

            $done = trim((string) $appointment->done) === 'انجام شد';
            $statuses[$item['key']] = [
                'appointment_id' => $appointment->id,
                'done' => $done,
                'completed_at' => $done ? $appointment->completed_at : null,
            ];
        }

        return response()->json(['statuses' => (object) $statuses]);
    }

    private function matchLegacy($byDay, array $item): ?Appointment
    {
        if (empty($item['month']) || empty($item['day_num'])) return null;
        $phone = FollowupAutomaticSmsService::normalizePhone($item['phone'] ?? '');
        if ($phone === '') return null;

        $candidates = collect($byDay->get($item['month'].'|'.(int) $item['day_num'], []))
            ->filter(fn (Appointment $appointment) => FollowupAutomaticSmsService::normalizePhone($appointment->phone) === $phone);
        $time = substr(trim((string) ($item['time'] ?? '')), 0, 5);

        return ($time !== '' ? $candidates->first(fn (Appointment $appointment) => substr((string) $appointment->time, 0, 5) === $time) : null)
            ?? $candidates->first();
    }
}
