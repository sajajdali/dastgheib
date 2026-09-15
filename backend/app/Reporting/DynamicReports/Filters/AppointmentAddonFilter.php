<?php
namespace App\Reporting\DynamicReports\Filters;
final class AppointmentAddonFilter extends AppointmentServiceJsonFilter
{
    protected function key(): string { return 'extra'; }
    protected function jsonPath(): string { return '$[*].addons[*].name'; }
}
