<?php

namespace App\Reporting\DynamicReports\Filters;

use Illuminate\Database\Eloquent\Builder;

abstract class AbstractAppointmentMultiValueFilter implements DynamicReportQueryFilter
{
    abstract protected function key(): string;
    abstract protected function column(): string;

    public function apply(Builder $query, array $filters): void
    {
        $values = collect(data_get($filters, 'multi.'.$this->key(), []))
            ->map(fn ($value) => trim((string) $value))->filter()->unique()->values();
        if ($values->isEmpty()) return;

        $column = $this->column();
        $alias = $this->key().'_appointments';
        $query->whereExists(function ($appointments) use ($values, $column, $alias, $filters) {
            $appointments->selectRaw('1')->from('appointments as '.$alias)
                ->whereIn($alias.'.'.$column, $values)
                ->where(function ($link) use ($alias) {
                    $link->where(function ($byFile) use ($alias) {
                        $byFile->whereNotNull('patients.file_number')->where('patients.file_number', '<>', '')
                            ->whereColumn($alias.'.file_number', 'patients.file_number');
                    })->orWhere(function ($byPhone) use ($alias) {
                        $byPhone->where(function ($missingFile) {
                            $missingFile->whereNull('patients.file_number')->orWhere('patients.file_number', '');
                        })->whereNotNull('patients.phone')->where('patients.phone', '<>', '')
                            ->whereColumn($alias.'.phone', 'patients.phone');
                    });
                });
            AppointmentReportDate::constrain($appointments, $alias, $filters);
        });
    }
}
