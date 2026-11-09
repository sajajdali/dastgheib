<?php

namespace App\Reporting\DynamicReports\Filters;

use App\Reporting\DynamicReports\ReportWalletTotals;
use Illuminate\Database\Eloquent\Builder;

/** جمع واریز و برداشت کیف پول پرونده در بازهٔ گزارش، کمتر/بیشتر/برابر مقدار وارد شده. */
final class WalletCompareFilter implements DynamicReportQueryFilter
{
    public function apply(Builder $query, array $filters): void
    {
        foreach (ReportWalletTotals::TYPES as $key => $type) {
            $value = data_get($filters, 'values.'.$key);
            if ($value === null || $value === '' || ! is_numeric($value)) continue;
            [$sql, $bindings] = ReportWalletTotals::expression('patients.id', $type, $filters);
            $operator = ['lt' => '<', 'gt' => '>', 'eq' => '='][data_get($filters, 'comparators.'.$key, 'eq')] ?? '=';
            $query->whereRaw("{$sql} {$operator} CAST(? AS DECIMAL(18,2))", [...$bindings, (float) $value]);
        }
    }
}
