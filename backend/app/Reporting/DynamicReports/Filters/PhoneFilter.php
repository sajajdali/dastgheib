<?php
namespace App\Reporting\DynamicReports\Filters;
class PhoneFilter extends AbstractPrefixValueFilter
{
    protected function key(): string { return 'phone'; }
    protected function column(): string { return 'phone'; }
}
