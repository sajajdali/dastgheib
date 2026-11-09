<?php

namespace App\Reporting\DynamicReports\Filters;

final class DoctorCommissionCompareFilter extends AppointmentCommissionCompareFilter
{
    protected function key(): string { return 'doctorCommission'; }
}
