<?php

namespace App\Reporting\DynamicReports\Sources;

use App\Models\Appointment;
use App\Models\Patient;
use App\Reporting\DynamicReports\DynamicReportChunkContextLoader;
use App\Reporting\DynamicReports\JalaliMonthWindow;
use App\Reporting\DynamicReports\ReportPatientContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

final class AppointmentReportSource implements ReportSource
{
    public function __construct(
        private readonly DynamicReportChunkContextLoader $contexts,
        private readonly JalaliMonthWindow $dates,
    ) {}

    public function query(array $filters): Builder
    {
        $query = Appointment::query();
        $date = "CONCAT(appointments.month, '-', LPAD(appointments.day_num, 2, '0'))";
        if ($from = data_get($filters, 'reportDate.from')) $query->whereRaw("{$date} >= ?", [$from]);
        if ($to = data_get($filters, 'reportDate.to')) $query->whereRaw("{$date} <= ?", [$to]);

        foreach (['status' => 'status', 'source' => 'source', 'work' => 'done', 'payment' => 'payment_method', 'account' => 'payment_account'] as $key => $column) {
            $values = $this->selected($filters, $key);
            if ($values) $query->whereIn('appointments.'.$column, $values);
        }
        if ($doctors = $this->selected($filters, 'doctor')) $query->whereIn('appointments.doctor', $doctors);
        foreach (['section2' => '$[*].section', 'subsection' => '$[*].subsection', 'areas' => '$[*].tags[*]', 'extra' => '$[*].addons[*].name'] as $key => $path) {
            $values = $this->selected($filters, $key);
            if ($values) $query->where(function (Builder $match) use ($values, $path) {
                foreach ($values as $value) $match->orWhereRaw("JSON_SEARCH(appointments.services, 'one', ?, NULL, '{$path}') IS NOT NULL", [$value]);
            });
        }

        $this->prefix($query, 'appointments.phone', data_get($filters, 'values.phone'));
        $this->prefix($query, 'appointments.file_number', data_get($filters, 'values.fileNo'));
        $gender = data_get($filters, 'values.gender');
        if ($gender && $gender !== 'همه') {
            $normalized = ['مرد' => 'male', 'زن' => 'female'][$gender] ?? $gender;
            $query->whereIn('appointments.gender', array_unique([$normalized, $gender]));
        }

        $amount = $this->moneyExpression('appointments.amount');
        $this->compare($query, $amount, '>=', data_get($filters, 'range.amount.from'));
        $this->compare($query, $amount, '<=', data_get($filters, 'range.amount.to'));
        foreach (['debt' => $this->moneyExpression('appointments.debt'), 'deposit' => $this->moneyExpression("JSON_UNQUOTE(JSON_EXTRACT(appointments.payment_details, '$.deposit'))")] as $key => $expression) {
            $operator = ['lt' => '<', 'gt' => '>', 'eq' => '='][data_get($filters, 'comparators.'.$key, 'eq')] ?? '=';
            $this->compare($query, $expression, $operator, data_get($filters, 'values.'.$key));
        }

        $patientFields = ['name' => 'first_name', 'family' => 'last_name', 'nationalId' => 'national_id', 'city' => 'city'];
        $hasPatientFilters = collect($patientFields)->keys()->contains(fn ($key) => filled(data_get($filters, 'values.'.$key)))
            || data_get($filters, 'birthDate.from') || data_get($filters, 'birthDate.to')
            || $this->selected($filters, 'finstatus');
        if ($hasPatientFilters) $query->whereExists(function (QueryBuilder $patients) use ($filters, $patientFields) {
            $patients->selectRaw('1')->from('patients')->whereNull('patients.deleted_at');
            self::linkPatient($patients);
            foreach ($patientFields as $key => $column) $this->prefix($patients, 'patients.'.$column, data_get($filters, 'values.'.$key));
            if ($from = data_get($filters, 'birthDate.from')) $patients->where('patients.birth_date', '>=', $from);
            if ($to = data_get($filters, 'birthDate.to')) $patients->where('patients.birth_date', '<=', $to);
            if ($values = $this->selected($filters, 'finstatus')) $patients->whereIn('patients.financial_status', $values);
        });

        if ($referrer = trim((string) data_get($filters, 'values.referrer', ''))) {
            $query->where(function (Builder $match) use ($referrer) {
                $pattern = addcslashes($referrer, '%_\\').'%';
                $match->where('appointments.referrer_phone', 'like', $pattern)
                    ->orWhereExists(fn (QueryBuilder $people) => $people->selectRaw('1')->from('patients as referrers')
                        ->whereColumn('referrers.phone', 'appointments.referrer_phone')
                        ->where(fn (QueryBuilder $name) => $name->where('referrers.first_name', 'like', $pattern)
                            ->orWhere('referrers.last_name', 'like', $pattern)));
            });
        }

        $months = (int) data_get($filters, 'values.noreturnMonths', 0);
        if ($months > 0) {
            $window = $this->dates->endingToday($months);
            $query->whereRaw("(
                SELECT COUNT(*) FROM appointments AS return_appointments
                WHERE TRIM(return_appointments.status) = ?
                  AND CONCAT(return_appointments.month, '-', LPAD(return_appointments.day_num, 2, '0')) BETWEEN ? AND ?
                  AND ((appointments.file_number IS NOT NULL AND appointments.file_number <> ''
                      AND return_appointments.file_number = appointments.file_number)
                    OR ((appointments.file_number IS NULL OR appointments.file_number = '')
                      AND appointments.phone IS NOT NULL AND appointments.phone <> ''
                      AND return_appointments.phone = appointments.phone))
            ) = 1", ['آمد', $window['from'], $window['to']]);
        }

        return $query->orderBy('appointments.id');
    }

    public function rows(Collection $records, array $filters): Collection
    {
        $fileNumbers = $records->pluck('file_number')->filter()->unique();
        $phones = $records->pluck('phone')->filter()->unique();
        $patients = ($fileNumbers->isEmpty() && $phones->isEmpty()) ? collect() : Patient::query()
            ->where(function (Builder $query) use ($fileNumbers, $phones) {
                if ($fileNumbers->isNotEmpty()) $query->whereIn('file_number', $fileNumbers);
                if ($phones->isNotEmpty()) $query->{$fileNumbers->isNotEmpty() ? 'orWhereIn' : 'whereIn'}('phone', $phones);
            })->get();
        $byFile = $patients->filter->file_number->keyBy('file_number');
        $byPhone = $patients->filter->phone->keyBy('phone');
        $patientContexts = $this->contexts->load($patients, $filters)->keyBy(fn ($context) => $context->patient->id);

        return $records->map(function (Appointment $appointment) use ($byFile, $byPhone, $patientContexts) {
            $patient = ($appointment->file_number ? $byFile->get($appointment->file_number) : null)
                ?? ($appointment->phone ? $byPhone->get($appointment->phone) : null);
            $base = $patient ? $patientContexts->get($patient->id) : null;
            $patient ??= new Patient([
                'first_name' => '', 'last_name' => $appointment->lastname,
                'phone' => $appointment->phone, 'file_number' => $appointment->file_number,
                'gender' => $appointment->gender,
            ]);
            $context = new ReportPatientContext(
                patient: $patient,
                latestAppointment: $appointment,
                appointmentHistory: $base?->appointmentHistory ?? collect([$appointment]),
                referrers: $base?->referrers ?? collect(),
                customerLevel: $base?->customerLevel ?? 'silver',
            );
            return ['context' => $context, 'patient_id' => $patient->id, 'source_id' => $appointment->id];
        });
    }

    private static function linkPatient(QueryBuilder $query): void
    {
        $query->where(fn (QueryBuilder $link) => $link
            ->where(fn (QueryBuilder $byFile) => $byFile->whereNotNull('appointments.file_number')
                ->where('appointments.file_number', '<>', '')->whereColumn('patients.file_number', 'appointments.file_number'))
            ->orWhere(fn (QueryBuilder $byPhone) => $byPhone->whereNotNull('appointments.phone')
                ->where('appointments.phone', '<>', '')->whereColumn('patients.phone', 'appointments.phone')));
    }

    private function selected(array $filters, string $key): array
    {
        return collect(data_get($filters, 'multi.'.$key, []))->map(fn ($value) => trim((string) $value))->filter()->unique()->all();
    }

    private function prefix(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $value = trim((string) $value);
        if ($value !== '') $query->where($column, 'like', addcslashes($value, '%_\\').'%');
    }

    private function moneyExpression(string $column): string
    {
        return "CAST(REPLACE(REPLACE(REPLACE(COALESCE({$column}, '0'), ',', ''), '٬', ''), ' ', '') AS DECIMAL(18,2))";
    }

    private function compare(Builder $query, string $expression, string $operator, mixed $value): void
    {
        if ($value !== null && $value !== '' && is_numeric($value)) $query->whereRaw("{$expression} {$operator} ?", [(float) $value]);
    }
}
