<?php

namespace App\Reporting\DynamicReports\Resolvers;

use App\Reporting\DynamicReports\ReportPatientContext;
use App\Models\ResourceEarningLine;

class AppointmentFieldResolver implements ReportFieldResolver
{
    private const FIELDS = ['noreturn', 'status', 'source', 'work', 'section2', 'subsection', 'areas', 'problem', 'extra', 'count', 'doctor', 'consultant', 'salary', 'overtime'];

    public function supports(string $field): bool { return in_array($field, self::FIELDS, true); }

    public function resolve(string $field, ReportPatientContext $context): mixed
    {
        $appointment = $context->latestAppointment;
        return match ($field) {
            'noreturn' => $this->lastAttendance($context),
            'status' => $appointment?->status,
            'source' => $appointment?->source,
            'work' => ! $appointment ? null : ($appointment->done ? 'انجام شده' : ($appointment->status ?: 'انجام نشده')),
            'section2' => $this->join($appointment?->service_types),
            'subsection' => $this->serviceNames($appointment?->services),
            'count' => $this->serviceCc($appointment?->services),
            'doctor' => $appointment?->doctor,
            'consultant' => $appointment?->consultant,
            'salary' => $this->salary($appointment),
            'overtime' => $this->overtime($appointment),
            'areas' => $this->serviceTags($appointment?->services),
            'problem', 'extra' => null,
        };
    }

    private function join(mixed $values): ?string
    {
        $items = collect(is_array($values) ? $values : [])->flatten()->filter(fn ($value) => is_scalar($value));
        return $items->isEmpty() ? null : $items->implode('، ');
    }

    private function serviceNames(mixed $services): ?string
    {
        $names = collect(is_array($services) ? $services : [])->map(fn ($service) =>
            is_array($service) ? ($service['name'] ?? $service['title'] ?? null) : $service
        )->filter(fn ($value) => is_scalar($value));
        return $names->isEmpty() ? null : $names->implode('، ');
    }

    private function serviceTags(mixed $services): ?string
    {
        $tags = collect(is_array($services) ? $services : [])
            ->flatMap(fn ($service) => is_array($service) && is_array($service['tags'] ?? null) ? $service['tags'] : [])
            ->map(fn ($tag) => trim((string) $tag))->filter()->unique()->values();
        return $tags->isEmpty() ? null : $tags->implode('، ');
    }

    private function serviceCc(mixed $services): ?string
    {
        $total = collect(is_array($services) ? $services : [])->sum(fn ($service) => is_array($service) ? (float) str_replace([',', '٬', ' '], '', (string) ($service['cc'] ?? 0)) : 0);
        return $total > 0 ? rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.') : null;
    }
    private function salary(mixed $appointment): ?string
    {
        if (!$appointment) return null;
        $names = array_filter([$appointment->doctor, $appointment->consultant]);
        $value = ResourceEarningLine::query()->where('appointment_id', $appointment->id)->whereIn('resource_name', $names)->sum('amount');
        return $value ? number_format((float)$value, 0, '.', '٬') : '۰';
    }
    private function overtime(mixed $appointment): ?string
    {
        if (!$appointment) return null;
        $value = ResourceEarningLine::query()->where('appointment_id', $appointment->id)->where('earning_type', 'attendance_overtime')->sum('quantity');
        return $value ? number_format((float)$value, 2, '.', '٬') : '۰';
    }

    private function lastAttendance(ReportPatientContext $context): ?string
    {
        $appointment = $context->appointmentHistory
            ->first(fn ($item) => trim((string) $item->status) === 'آمد');
        if (! $appointment?->month || ! $appointment?->day_num) return null;
        return sprintf('%s/%02d', str_replace('-', '/', (string) $appointment->month), (int) $appointment->day_num);
    }
}
