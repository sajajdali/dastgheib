<?php

namespace App\Reporting\DynamicReports\Filters;

use App\Reporting\DynamicReports\ReportTextMatch;
use Illuminate\Database\Eloquent\Builder;

abstract class AbstractPrefixValueFilter implements DynamicReportQueryFilter
{
    abstract protected function key(): string;
    abstract protected function column(): string;

    public function apply(Builder $query, array $filters): void
    {
        ReportTextMatch::apply($query, $this->column(), data_get($filters, 'values.'.$this->key(), ''));
    }

    protected function pattern(string $value): string
    {
        return addcslashes(trim($value), '%_\\').'%';
    }
}
