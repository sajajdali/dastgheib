<?php

namespace App\Reporting\DynamicReports\Filters;

use App\Reporting\DynamicReports\AppointmentReportMatch;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class AppointmentSectionFilter extends AppointmentExistsFilter
{
    protected function key(): string { return 'section2'; }

    protected function active(array $filters): bool { return $this->selected($filters, 'section2') !== []; }

    protected function constrain(QueryBuilder $appointments, string $alias, array $filters): void
    {
        AppointmentReportMatch::sections($appointments, $alias, $this->selected($filters, 'section2'), 'subsection');
    }
}
