<?php

namespace App\Reporting\DynamicReports\Filters;

use App\Reporting\DynamicReports\AppointmentReportMatch;
use App\Reporting\DynamicReports\ReportCommissionTotals;
use Illuminate\Database\Query\Builder as QueryBuilder;

/** پرونده‌هایی که در بازهٔ گزارش نوبتی با پورسانت پزشک یا مشاورِ کمتر/بیشتر/برابر مقدار دارند. */
abstract class AppointmentCommissionCompareFilter extends AppointmentExistsFilter
{
    protected function active(array $filters): bool { return is_numeric(data_get($filters, 'values.'.$this->key())); }

    protected function constrain(QueryBuilder $appointments, string $alias, array $filters): void
    {
        AppointmentReportMatch::compare(
            $appointments,
            ReportCommissionTotals::expression($alias.'.id', ReportCommissionTotals::RESOURCE_TYPES[$this->key()]),
            data_get($filters, 'comparators.'.$this->key()),
            data_get($filters, 'values.'.$this->key()),
        );
    }
}
