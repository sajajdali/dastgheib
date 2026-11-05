<?php

namespace App\Reporting\DynamicReports\Filters;

use App\Reporting\DynamicReports\AppointmentReportMatch;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class AppointmentSubsectionFilter extends AppointmentExistsFilter
{
    protected function key(): string { return 'subsection'; }

    protected function active(array $filters): bool { return $this->selected($filters, 'subsection') !== []; }

    protected function constrain(QueryBuilder $appointments, string $alias, array $filters): void
    {
        AppointmentReportMatch::sections($appointments, $alias, $this->selected($filters, 'subsection'), 'section');
    }
}
