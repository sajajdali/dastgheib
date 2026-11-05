<?php

namespace App\Reporting\DynamicReports\Filters;

use App\Reporting\DynamicReports\AppointmentReportMatch;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class AppointmentPaymentMethodFilter extends AppointmentExistsFilter
{
    protected function key(): string { return 'payment'; }

    protected function active(array $filters): bool { return $this->selected($filters, 'payment') !== []; }

    protected function constrain(QueryBuilder $appointments, string $alias, array $filters): void
    {
        AppointmentReportMatch::payment($appointments, $alias, 'payment_method', $this->selected($filters, 'payment'));
    }
}
