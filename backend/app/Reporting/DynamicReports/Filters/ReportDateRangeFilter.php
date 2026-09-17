<?php

namespace App\Reporting\DynamicReports\Filters;

use Illuminate\Database\Eloquent\Builder;

final class ReportDateRangeFilter implements DynamicReportQueryFilter
{
    public function apply(Builder $query, array $filters): void
    {
        $query->whereExists(function ($appointments) use ($filters) {
            $alias = 'report_range_appointments';
            $appointments->selectRaw('1')->from('appointments as '.$alias);
            AppointmentReportDate::constrain($appointments, $alias, $filters);
            $appointments->where(function ($link) use ($alias) {
                $link->where(fn ($q) => $q->whereNotNull('patients.file_number')->where('patients.file_number', '<>', '')->whereColumn($alias.'.file_number', 'patients.file_number'))
                    ->orWhere(fn ($q) => $q->where(fn ($missing) => $missing->whereNull('patients.file_number')->orWhere('patients.file_number', ''))->whereNotNull('patients.phone')->where('patients.phone', '<>', '')->whereColumn($alias.'.phone', 'patients.phone'));
            });
        });
    }
}
