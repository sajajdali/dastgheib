<?php
namespace App\Reporting\DynamicReports\Filters;
final class AppointmentPaymentAccountFilter extends AbstractAppointmentMultiValueFilter
{
    protected function key(): string { return 'account'; }
    protected function column(): string { return 'payment_account'; }
}
