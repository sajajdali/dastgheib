<?php

namespace App\Reporting\DynamicReports;

use App\Models\Patient;
use App\Reporting\DynamicReports\Filters\DynamicReportQueryFilter;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;

class DynamicReportFilterPipeline
{
    public function __construct(private readonly Container $container) {}

    public function query(array $filters): Builder
    {
        $query = Patient::query()->select([
            'id', 'first_name', 'last_name', 'phone', 'file_number', 'gender',
            'birth_date', 'city', 'financial_status', 'customer_level',
        ]);
        foreach (config('dynamic_reports.query_filters', []) as $filterClass) {
            $filter = $this->container->make($filterClass);
            if (! $filter instanceof DynamicReportQueryFilter) {
                throw new \LogicException("Dynamic report filter [{$filterClass}] must implement DynamicReportQueryFilter.");
            }
            $filter->apply($query, $filters);
        }
        return $query->orderBy('id');
    }
}
