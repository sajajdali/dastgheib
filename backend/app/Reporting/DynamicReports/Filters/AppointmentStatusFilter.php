<?php

namespace App\Reporting\DynamicReports\Filters;

use Illuminate\Database\Eloquent\Builder;

final class AppointmentStatusFilter implements DynamicReportQueryFilter
{
    public function apply(Builder $query, array $filters): void
    {
        $statuses = collect(data_get($filters, 'multi.status', []))->filter()->unique()->values();
        if ($statuses->isEmpty()) return;

        $query->whereExists(function ($appointments) use ($statuses) {
            $appointments->selectRaw('1')
                ->from('appointments as status_appointments')
                ->whereIn('status_appointments.status', $statuses)
                ->where(function ($link) {
                    $link->where(function ($byFile) {
                        $byFile->whereNotNull('patients.file_number')
                            ->where('patients.file_number', '<>', '')
                            ->whereColumn('status_appointments.file_number', 'patients.file_number');
                    })->orWhere(function ($byPhone) {
                        $byPhone->where(function ($missingFile) {
                            $missingFile->whereNull('patients.file_number')->orWhere('patients.file_number', '');
                        })->whereNotNull('patients.phone')
                            ->where('patients.phone', '<>', '')
                            ->whereColumn('status_appointments.phone', 'patients.phone');
                    });
                });
        });
    }
}
