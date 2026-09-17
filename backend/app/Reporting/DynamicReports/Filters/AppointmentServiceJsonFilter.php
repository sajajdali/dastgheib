<?php

namespace App\Reporting\DynamicReports\Filters;

use Illuminate\Database\Eloquent\Builder;

abstract class AppointmentServiceJsonFilter implements DynamicReportQueryFilter
{
    abstract protected function key(): string;
    abstract protected function jsonPath(): string;

    public function apply(Builder $query, array $filters): void
    {
        $values = collect(data_get($filters, 'multi.'.$this->key(), []))->map(fn ($v) => trim((string) $v))->filter()->unique()->values();
        if ($values->isEmpty()) return;
        $alias = $this->key().'_service_appointments';
        $query->whereExists(function ($appointments) use ($values, $alias, $filters) {
            $appointments->selectRaw('1')->from('appointments as '.$alias)
                ->where(function ($match) use ($values, $alias) {
                    foreach ($values as $value) {
                        $match->orWhereRaw("JSON_SEARCH({$alias}.services, 'one', ?, NULL, '{$this->jsonPath()}') IS NOT NULL", [$value]);
                    }
                })
                ->where(function ($link) use ($alias) {
                    $link->where(fn ($q) => $q->whereNotNull('patients.file_number')->where('patients.file_number', '<>', '')
                        ->whereColumn($alias.'.file_number', 'patients.file_number'))
                        ->orWhere(fn ($q) => $q->where(fn ($m) => $m->whereNull('patients.file_number')->orWhere('patients.file_number', ''))
                            ->whereNotNull('patients.phone')->where('patients.phone', '<>', '')
                            ->whereColumn($alias.'.phone', 'patients.phone'));
                });
            AppointmentReportDate::constrain($appointments, $alias, $filters);
        });
    }
}
