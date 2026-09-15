<?php

namespace App\Reporting\DynamicReports\Filters;

use App\Reporting\DynamicReports\JalaliMonthWindow;
use Illuminate\Database\Eloquent\Builder;

final class NoReturnFilter implements DynamicReportQueryFilter
{
    public function __construct(private readonly JalaliMonthWindow $dates) {}

    public function apply(Builder $query, array $filters): void
    {
        $months = (int) data_get($filters, 'values.noreturnMonths', 0);
        if ($months < 1) return;

        $window = $this->dates->endingToday($months);
        $appointmentDate = "CONCAT(return_appointments.month, '-', LPAD(return_appointments.day_num, 2, '0'))";

        $query->whereRaw("(
            SELECT COUNT(*)
            FROM appointments AS return_appointments
            WHERE TRIM(return_appointments.status) = ?
              AND {$appointmentDate} BETWEEN ? AND ?
              AND (
                (patients.file_number IS NOT NULL AND patients.file_number <> ''
                    AND return_appointments.file_number = patients.file_number)
                OR
                ((patients.file_number IS NULL OR patients.file_number = '')
                    AND patients.phone IS NOT NULL AND patients.phone <> ''
                    AND return_appointments.phone = patients.phone)
              )
        ) = 1", ['آمد', $window['from'], $window['to']]);
    }
}
