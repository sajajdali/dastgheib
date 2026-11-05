<?php

namespace App\Reporting\DynamicReports\Filters;

use App\Reporting\DynamicReports\AppointmentReportMatch;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class AppointmentWorkFilter extends AppointmentExistsFilter
{
    protected function key(): string { return 'work'; }

    protected function active(array $filters): bool { return $this->selected($filters, 'work') !== []; }

    protected function constrain(QueryBuilder $appointments, string $alias, array $filters): void
    {
        AppointmentReportMatch::work($appointments, $alias, $this->selected($filters, 'work'));
    }
}
