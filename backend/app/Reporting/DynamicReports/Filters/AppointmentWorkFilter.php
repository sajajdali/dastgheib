<?php

namespace App\Reporting\DynamicReports\Filters;

final class AppointmentWorkFilter extends AbstractAppointmentMultiValueFilter
{
    protected function key(): string { return 'work'; }
    protected function column(): string { return 'done'; }
}
