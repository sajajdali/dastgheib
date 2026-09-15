<?php
namespace App\Reporting\DynamicReports\Filters;
final class AppointmentServiceTagFilter extends AppointmentServiceJsonFilter
{
    protected function key(): string { return 'areas'; }
    protected function jsonPath(): string { return '$[*].tags[*]'; }
}
