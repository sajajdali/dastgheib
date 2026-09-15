<?php
namespace App\Reporting\DynamicReports\Filters;
class FileNumberFilter extends AbstractPrefixValueFilter
{
    protected function key(): string { return 'fileNo'; }
    protected function column(): string { return 'file_number'; }
}
