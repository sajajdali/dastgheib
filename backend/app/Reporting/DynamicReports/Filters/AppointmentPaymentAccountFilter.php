<?php

namespace App\Reporting\DynamicReports\Filters;

use App\Reporting\DynamicReports\AppointmentReportMatch;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class AppointmentPaymentAccountFilter extends AppointmentExistsFilter
{
    protected function key(): string { return 'account'; }

    protected function active(array $filters): bool { return $this->selected($filters, 'account') !== []; }

    protected function constrain(QueryBuilder $appointments, string $alias, array $filters): void
    {
        AppointmentReportMatch::payment($appointments, $alias, 'payment_account', $this->selected($filters, 'account'));
    }
}
