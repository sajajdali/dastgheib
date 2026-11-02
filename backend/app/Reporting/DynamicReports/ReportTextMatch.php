<?php

namespace App\Reporting\DynamicReports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * معنای مقدار فیلترهای متنی گزارش‌ساز:
 *   «*»      → دارای مقدار (خالی نباشد)
 *   «-»      → خالی
 *   «*متن»   → شامل متن (هر جای مقدار)
 *   «متن»    → شروع با متن (رفتار قبلی)
 */
final class ReportTextMatch
{
    public static function apply(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $value = trim((string) $value);
        if ($value === '') return;
        if ($value === '*') {
            $query->whereNotNull($column)->where($column, '<>', '');
        } elseif ($value === '-') {
            $query->where(fn ($empty) => $empty->whereNull($column)->orWhere($column, ''));
        } elseif (str_starts_with($value, '*')) {
            $query->where($column, 'like', '%'.addcslashes(ltrim(mb_substr($value, 1)), '%_\\').'%');
        } else {
            $query->where($column, 'like', addcslashes($value, '%_\\').'%');
        }
    }
}
