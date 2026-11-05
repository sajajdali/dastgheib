<?php

namespace App\Reporting\DynamicReports\Filters;

use Illuminate\Database\Eloquent\Builder;

/** «مشکل»: جست‌وجو در سابقهٔ پزشکی و سابقهٔ بیمار پرونده. */
final class MedicalProblemFilter implements DynamicReportQueryFilter
{
    public function apply(Builder $query, array $filters): void
    {
        $term = trim((string) data_get($filters, 'values.problem', ''));
        if ($term === '') return;
        $pattern = '%'.addcslashes($term, '%_\\').'%';
        $query->where(fn ($match) => $match->where('patients.medical_history', 'like', $pattern)->orWhere('patients.patient_history', 'like', $pattern));
    }
}
