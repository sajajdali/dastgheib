<?php
namespace App\Reporting\DynamicReports\Filters;
class FirstNameFilter extends AbstractPrefixValueFilter
{
    protected function key(): string { return 'name'; }
    protected function column(): string { return 'first_name'; }
}
