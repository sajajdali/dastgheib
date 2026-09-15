<?php
namespace App\Reporting\DynamicReports\Filters;
final class AppointmentSectionFilter extends AppointmentServiceJsonFilter
{
    protected function key(): string { return 'section2'; }
    protected function jsonPath(): string { return '$[*].section'; }
}
