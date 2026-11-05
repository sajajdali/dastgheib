<?php

namespace App\Reporting\DynamicReports\Filters;

use App\Reporting\DynamicReports\AppointmentReportMatch;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class AppointmentDiscountCompareFilter extends AppointmentExistsFilter
{
    protected function key(): string { return 'discount'; }

    protected function active(array $filters): bool { return is_numeric(data_get($filters, 'values.discount')); }

    protected function constrain(QueryBuilder $appointments, string $alias, array $filters): void
    {
        AppointmentReportMatch::compare($appointments, AppointmentReportMatch::money($alias.'.discount'), data_get($filters, 'comparators.discount'), data_get($filters, 'values.discount'));
    }
}
