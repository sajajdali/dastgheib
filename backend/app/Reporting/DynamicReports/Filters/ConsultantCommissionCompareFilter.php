<?php

namespace App\Reporting\DynamicReports\Filters;

final class ConsultantCommissionCompareFilter extends AppointmentCommissionCompareFilter
{
    protected function key(): string { return 'consultantCommission'; }
}
