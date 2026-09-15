<?php
namespace App\Reporting\DynamicReports\Filters;
final class AppointmentPaymentMethodFilter extends AbstractAppointmentMultiValueFilter
{
    protected function key(): string { return 'payment'; }
    protected function column(): string { return 'payment_method'; }
}
