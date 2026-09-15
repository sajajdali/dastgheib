<?php
namespace App\Reporting\DynamicReports\Filters;
use Illuminate\Database\Eloquent\Builder;
final class FinancialStatusFilter implements DynamicReportQueryFilter
{
    public function apply(Builder $query, array $filters): void
    {
        $values = collect(data_get($filters, 'multi.finstatus', []))->filter()->unique()->values();
        if ($values->isNotEmpty()) $query->whereIn('patients.financial_status', $values);
    }
}
