<?php

namespace App\Reporting\DynamicReports\Resolvers;

use App\Reporting\DynamicReports\ReportPatientContext;
use App\Models\ResourceEarningLine;

class AppointmentFieldResolver implements ReportFieldResolver
{
    private const FIELDS = ['noreturn', 'status', 'appointmentCreatedDate', 'appointmentDate', 'source', 'work', 'section2', 'subsection', 'areas', 'problem', 'extra', 'count', 'doctor', 'consultant', 'salary', 'overtime'];

    public function supports(string $field): bool { return in_array($field, self::FIELDS, true); }

    public function resolve(string $field, ReportPatientContext $context): mixed
    {
        $appointment = $context->latestAppointment;
        return match ($field) {
            'noreturn' => $this->lastAttendance($context),
            'status' => $appointment?->status,
            'appointmentCreatedDate' => $this->createdDate($appointment),
            'appointmentDate' => $this->appointmentDate($appointment),
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

    private function appointmentDate(mixed $appointment): ?string
    {
        if (! $appointment?->month || ! $appointment?->day_num) return null;

        return sprintf(
            '%s/%02d',
            str_replace('-', '/', trim((string) $appointment->month)),
            (int) $appointment->day_num,
        );
    }

    private function createdDate(mixed $appointment): ?string
    {
        if (! $appointment?->created_at) return null;

        $date = $appointment->created_at;
        $gy = (int) $date->format('Y') - 1600;
        $gm = (int) $date->format('n') - 1;
        $gd = (int) $date->format('j') - 1;
        $gregorianMonthDays = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $jalaliMonthDays = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

        $days = 365 * $gy + intdiv($gy + 3, 4) - intdiv($gy + 99, 100) + intdiv($gy + 399, 400);
        for ($index = 0; $index < $gm; $index++) $days += $gregorianMonthDays[$index];

        $gregorianYear = $gy + 1600;
        if ($gm > 1 && ($gregorianYear % 4 === 0 && ($gregorianYear % 100 !== 0 || $gregorianYear % 400 === 0))) $days++;
        $days += $gd - 79;

        $jalaliCycle = intdiv($days, 12053);
        $days %= 12053;
        $jy = 979 + 33 * $jalaliCycle + 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days >= 366) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        for ($jm = 0; $jm < 11 && $days >= $jalaliMonthDays[$jm]; $jm++) $days -= $jalaliMonthDays[$jm];

        return sprintf('%04d/%02d/%02d', $jy, $jm + 1, $days + 1);
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
