<?php

namespace App\Reporting\DynamicReports\Filters;

use Illuminate\Database\Eloquent\Builder;

abstract class AbstractPrefixValueFilter implements DynamicReportQueryFilter
{
    abstract protected function key(): string;
    abstract protected function column(): string;

    public function apply(Builder $query, array $filters): void
    {
        $value = trim((string) data_get($filters, 'values.'.$this->key(), ''));
        if ($value === '') return;
        $query->where($this->column(), 'like', $this->pattern($value));
    }

    protected function pattern(string $value): string
    {
        return addcslashes(trim($value), '%_\\').'%';
    }
}
