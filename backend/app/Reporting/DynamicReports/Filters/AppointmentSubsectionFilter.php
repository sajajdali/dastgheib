<?php
namespace App\Reporting\DynamicReports\Filters;
final class AppointmentSubsectionFilter extends AppointmentServiceJsonFilter
{
    protected function key(): string { return 'subsection'; }
    protected function jsonPath(): string { return '$[*].subsection'; }
}
