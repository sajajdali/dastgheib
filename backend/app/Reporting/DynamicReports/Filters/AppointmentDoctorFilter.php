<?php

namespace App\Reporting\DynamicReports\Filters;

use Illuminate\Database\Eloquent\Builder;

final class AppointmentDoctorFilter implements DynamicReportQueryFilter
{
    public function apply(Builder $query, array $filters): void
    {
        $doctors = collect(data_get($filters, 'multi.doctor', []))->map(fn ($name) => trim((string) $name))->filter()->unique()->values();
        if ($doctors->isEmpty()) return;

        $query->whereExists(function ($appointments) use ($doctors, $filters) {
            $appointments->selectRaw('1')->from('appointments as doctor_appointments')
                ->whereIn('doctor_appointments.doctor', $doctors)
                ->where(function ($link) {
                    $link->where(function ($byFile) {
                        $byFile->whereNotNull('patients.file_number')->where('patients.file_number', '<>', '')
                            ->whereColumn('doctor_appointments.file_number', 'patients.file_number');
                    })->orWhere(function ($byPhone) {
                        $byPhone->where(function ($missingFile) {
                            $missingFile->whereNull('patients.file_number')->orWhere('patients.file_number', '');
                        })->whereNotNull('patients.phone')->where('patients.phone', '<>', '')
                            ->whereColumn('doctor_appointments.phone', 'patients.phone');
                    });
                });
            $from = (string) data_get($filters, 'reportDate.from', '');
            $to = (string) data_get($filters, 'reportDate.to', '');
            if ($from !== '') {
                $month = substr($from, 0, 7); $day = (int) substr($from, 8, 2);
                $appointments->where(fn ($q) => $q->where('doctor_appointments.month', '>', $month)
                    ->orWhere(fn ($same) => $same->where('doctor_appointments.month', $month)->where('doctor_appointments.day_num', '>=', $day)));
            }
            if ($to !== '') {
                $month = substr($to, 0, 7); $day = (int) substr($to, 8, 2);
                $appointments->where(fn ($q) => $q->where('doctor_appointments.month', '<', $month)
                    ->orWhere(fn ($same) => $same->where('doctor_appointments.month', $month)->where('doctor_appointments.day_num', '<=', $day)));
            }
        });
    }
}
