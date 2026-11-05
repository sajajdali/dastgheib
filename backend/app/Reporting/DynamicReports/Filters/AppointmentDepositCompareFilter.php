<?php

namespace App\Reporting\DynamicReports\Filters;

use App\Reporting\DynamicReports\AppointmentReportMatch;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class AppointmentDepositCompareFilter extends AppointmentExistsFilter
{
    protected function key(): string { return 'deposit'; }

    protected function active(array $filters): bool { return is_numeric(data_get($filters, 'values.deposit')); }

    protected function constrain(QueryBuilder $appointments, string $alias, array $filters): void
    {
        AppointmentReportMatch::compare($appointments, AppointmentReportMatch::deposit($alias), data_get($filters, 'comparators.deposit'), data_get($filters, 'values.deposit'));
    }
}
