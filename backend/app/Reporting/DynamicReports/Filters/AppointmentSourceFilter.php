<?php

namespace App\Reporting\DynamicReports\Filters;

final class AppointmentSourceFilter extends AbstractAppointmentMultiValueFilter
{
    protected function key(): string { return 'source'; }
    protected function column(): string { return 'source'; }
}
