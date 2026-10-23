<?php

namespace App\Reporting\DynamicReports\Filters;

class NationalIdFilter extends AbstractPrefixValueFilter
{
    protected function key(): string { return 'nationalId'; }
    protected function column(): string { return 'national_id'; }
}
