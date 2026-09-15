<?php

namespace App\Reporting\DynamicReports\Filters;

use Illuminate\Database\Eloquent\Builder;

interface DynamicReportQueryFilter
{
    public function apply(Builder $query, array $filters): void;
}
