<?php
namespace App\Reporting\DynamicReports\Filters;
class LastNameFilter extends AbstractPrefixValueFilter
{
    protected function key(): string { return 'family'; }
    protected function column(): string { return 'last_name'; }
}
