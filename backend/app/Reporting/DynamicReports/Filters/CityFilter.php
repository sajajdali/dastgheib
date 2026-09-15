<?php
namespace App\Reporting\DynamicReports\Filters;
class CityFilter extends AbstractPrefixValueFilter
{
    protected function key(): string { return 'city'; }
    protected function column(): string { return 'city'; }
}
