<?php

namespace App\Reporting\DynamicReports\Filters;

use App\Reporting\DynamicReports\AppointmentReportMatch;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class AppointmentConsultantFilter extends AppointmentExistsFilter
{
    protected function key(): string { return 'consultant'; }

    protected function active(array $filters): bool { return $this->selected($filters, 'consultant') !== []; }

    protected function constrain(QueryBuilder $appointments, string $alias, array $filters): void
    {
        AppointmentReportMatch::person($appointments, $alias, 'consultant', $this->selected($filters, 'consultant'));
    }
}
