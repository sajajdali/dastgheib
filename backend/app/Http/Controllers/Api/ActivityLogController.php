<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\PatientPhoneVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::query()->latest('id');

        if ($request->filled('event')) {
            $query->where('event', $request->query('event'));
        }

        if ($request->filled('section')) {
            $query->where('section', $request->query('section'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->query('subject_type'));
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->integer('subject_id'));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(function ($inner) use ($term) {
                $inner->where('subject_label', 'like', "%{$term}%")
                    ->orWhere('user_name', 'like', "%{$term}%")
                    ->orWhere('section', 'like', "%{$term}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        $perPage = min(100, max(10, (int) $request->integer('per_page', 30)));
        $logs = $query->paginate($perPage);

        $logs->getCollection()->transform(fn (ActivityLog $log) => $this->formatLog($log, $request));

        return response()->json($logs);
    }

    private function formatLog(ActivityLog $log, Request $request): array
    {
        $old = $this->maskPhones($log->old_values ?? [], $request);
        $new = $this->maskPhones($log->new_values ?? [], $request);
        $meta = $this->maskPhones($log->metadata ?? [], $request);

        return [
            'id' => $log->id,
            'event' => $log->event,
            'event_label' => $this->eventLabel($log->event),
            'section' => $log->section,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'subject_label' => $log->subject_label,
            'user_id' => $log->user_id,
            'user_name' => $log->user_name ?: 'سیستم',
            'user_email' => $log->user_email,
            'old_values' => $old,
            'new_values' => $new,
            'metadata' => $meta,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at,
        ];
    }

    private function maskPhones(array $values, Request $request): array
    {
        if (PatientPhoneVisibility::canView($request)) {
            return $values;
        }

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->maskPhones($value, $request);
                continue;
            }

            if (str_contains((string) $key, 'phone') || str_contains((string) $key, 'mobile')) {
                $values[$key] = PatientPhoneVisibility::mask((string) $value);
            }
        }

        return $values;
    }

    private function eventLabel(string $event): string
    {
        return [
            'created' => 'ایجاد',
            'updated' => 'ویرایش',
            'deleted' => 'حذف',
            'login' => 'ورود',
            'logout' => 'خروج',
            'role_permissions_updated' => 'تغییر دسترسی نقش',
            'sms_sent' => 'ارسال پیامک',
            'sms_failed' => 'خطای ارسال پیامک',
        ][$event] ?? $event;
    }
}
