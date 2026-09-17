<?php

namespace App\Reporting\DynamicReports\Filters;

use Illuminate\Database\Query\Builder;

final class AppointmentReportDate
{
    public static function constrain(Builder $query, string $alias, array $filters): void
    {
        $from = data_get($filters, 'reportDate.from');
        $to = data_get($filters, 'reportDate.to');
        $date = "CONCAT({$alias}.month, '-', LPAD({$alias}.day_num, 2, '0'))";

        if ($from) $query->whereRaw($date.' >= ?', [$from]);
        if ($to) $query->whereRaw($date.' <= ?', [$to]);
    }
}
