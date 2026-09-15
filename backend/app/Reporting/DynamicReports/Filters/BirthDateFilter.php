<?php

namespace App\Reporting\DynamicReports\Filters;

use Illuminate\Database\Eloquent\Builder;

class BirthDateFilter implements DynamicReportQueryFilter
{
    public function apply(Builder $query, array $filters): void
    {
        $from = data_get($filters, 'birthDate.from');
        $to = data_get($filters, 'birthDate.to');
        if ($from) $query->where('birth_date', '>=', $from);
        if ($to) $query->where('birth_date', '<=', $to);
    }
}
