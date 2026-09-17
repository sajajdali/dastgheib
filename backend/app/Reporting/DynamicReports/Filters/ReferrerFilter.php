<?php

namespace App\Reporting\DynamicReports\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReferrerFilter extends AbstractPrefixValueFilter
{
    protected function key(): string { return 'referrer'; }
    protected function column(): string { return 'referrer_phone'; }

    public function apply(Builder $query, array $filters): void
    {
        $term = trim((string) data_get($filters, 'values.referrer', ''));
        if ($term === '') return;
        $pattern = $this->pattern($term);

        $query->whereExists(function ($subquery) use ($pattern, $filters) {
            $subquery->selectRaw('1')
                ->from('appointments as referral_appointments')
                ->leftJoin('patients as referrers', 'referrers.phone', '=', 'referral_appointments.referrer_phone')
                ->where(function ($link) {
                    $link->whereColumn('referral_appointments.file_number', 'patients.file_number')
                        ->orWhereColumn('referral_appointments.phone', 'patients.phone');
                })
                ->where(function ($search) use ($pattern) {
                    $search->where('referrers.first_name', 'like', $pattern)
                        ->orWhere('referrers.last_name', 'like', $pattern)
                        ->orWhere(DB::raw("CONCAT(COALESCE(referrers.first_name, ''), ' ', COALESCE(referrers.last_name, ''))"), 'like', $pattern)
                        ->orWhere('referrers.phone', 'like', $pattern)
                        ->orWhere('referral_appointments.referrer_phone', 'like', $pattern);
                });
            AppointmentReportDate::constrain($subquery, 'referral_appointments', $filters);
        });
    }
}
