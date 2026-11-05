<?php

namespace App\Reporting\DynamicReports\Filters;

use App\Reporting\DynamicReports\AppointmentReportMatch;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class AppointmentDoctorFilter extends AppointmentExistsFilter
{
    protected function key(): string { return 'doctor'; }

    protected function active(array $filters): bool { return $this->selected($filters, 'doctor') !== []; }

    protected function constrain(QueryBuilder $appointments, string $alias, array $filters): void
    {
        AppointmentReportMatch::person($appointments, $alias, 'doctor', $this->selected($filters, 'doctor'));
    }
}
