<?php

namespace App\Reporting\DynamicReports\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/** پرونده‌هایی که در بازهٔ گزارش نوبتی با این شرط دارند. */
abstract class AppointmentExistsFilter implements DynamicReportQueryFilter
{
    abstract protected function key(): string;
    abstract protected function active(array $filters): bool;
    abstract protected function constrain(QueryBuilder $appointments, string $alias, array $filters): void;

    public function apply(Builder $query, array $filters): void
    {
        if (! $this->active($filters)) return;
        $alias = $this->key().'_appointments';
        $query->whereExists(function (QueryBuilder $appointments) use ($alias, $filters) {
            $appointments->selectRaw('1')->from('appointments as '.$alias)
                ->where(function ($link) use ($alias) {
                    $link->where(fn ($q) => $q->whereNotNull('patients.file_number')->where('patients.file_number', '<>', '')
                        ->whereColumn($alias.'.file_number', 'patients.file_number'))
                        ->orWhere(fn ($q) => $q->where(fn ($m) => $m->whereNull('patients.file_number')->orWhere('patients.file_number', ''))
                            ->whereNotNull('patients.phone')->where('patients.phone', '<>', '')
                            ->whereColumn($alias.'.phone', 'patients.phone'));
                });
            $this->dateRange($appointments, $alias, $filters);
            $this->constrain($appointments, $alias, $filters);
        });
    }

    /** بازهٔ گزارش با مقایسهٔ ماه و روز (بدون LPAD تا در SQLite هم کار کند). */
    private function dateRange(QueryBuilder $appointments, string $alias, array $filters): void
    {
        $from = (string) data_get($filters, 'reportDate.from', '');
        $to = (string) data_get($filters, 'reportDate.to', '');
        if ($from !== '') {
            [$month, $day] = [substr($from, 0, 7), (int) substr($from, 8, 2)];
            $appointments->where(fn ($q) => $q->where($alias.'.month', '>', $month)
                ->orWhere(fn ($same) => $same->where($alias.'.month', $month)->where($alias.'.day_num', '>=', $day)));
        }
        if ($to !== '') {
            [$month, $day] = [substr($to, 0, 7), (int) substr($to, 8, 2)];
            $appointments->where(fn ($q) => $q->where($alias.'.month', '<', $month)
                ->orWhere(fn ($same) => $same->where($alias.'.month', $month)->where($alias.'.day_num', '<=', $day)));
        }
    }

    protected function selected(array $filters, string $key): array
    {
        return collect(data_get($filters, 'multi.'.$key, []))->map(fn ($value) => trim((string) $value))->filter()->unique()->values()->all();
    }
}
