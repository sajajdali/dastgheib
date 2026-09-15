<?php

namespace App\Reporting\DynamicReports\Filters;

use Illuminate\Database\Eloquent\Builder;

class GenderFilter implements DynamicReportQueryFilter
{
    public function apply(Builder $query, array $filters): void
    {
        $value = trim((string) data_get($filters, 'values.gender', ''));
        if ($value === '' || $value === 'همه') return;
        $normalized = match ($value) { 'مرد' => 'male', 'زن' => 'female', default => $value };
        $persian = $normalized === 'male' ? 'مرد' : ($normalized === 'female' ? 'زن' : $normalized);
        $query->whereIn('gender', array_unique([$normalized, $persian]));
    }
}
