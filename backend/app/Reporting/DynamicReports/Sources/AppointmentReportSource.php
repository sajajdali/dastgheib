<?php

namespace App\Reporting\DynamicReports\Sources;

use App\Reporting\DynamicReports\AppointmentReportMatch;
use App\Reporting\DynamicReports\ReportTextMatch;
use App\Models\Appointment;
use App\Models\Patient;
use App\Reporting\DynamicReports\DynamicReportChunkContextLoader;
use App\Reporting\DynamicReports\JalaliMonthWindow;
use App\Reporting\DynamicReports\ReportPatientContext;
use App\Reporting\DynamicReports\ReportCommissionTotals;
use App\Reporting\DynamicReports\ReportWalletTotals;
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
        // ردیف‌های خالی تقویم (اسلات‌های ذخیره‌شدهٔ نسخه‌های قدیم) نوبت واقعی نیستند و گزارش را پر از صفر می‌کنند.
        $query = Appointment::query()->meaningful();
        $date = "CONCAT(appointments.month, '-', LPAD(appointments.day_num, 2, '0'))";
        if ($from = data_get($filters, 'reportDate.from')) $query->whereRaw("{$date} >= ?", [$from]);
        if ($to = data_get($filters, 'reportDate.to')) $query->whereRaw("{$date} <= ?", [$to]);

        foreach (['status' => 'status', 'source' => 'source'] as $key => $column) {
            $values = $this->selected($filters, $key);
            if ($values) $query->whereIn('appointments.'.$column, $values);
        }
        AppointmentReportMatch::work($query, 'appointments', $this->selected($filters, 'work'));
        AppointmentReportMatch::payment($query, 'appointments', 'payment_method', $this->selected($filters, 'payment'));
        AppointmentReportMatch::payment($query, 'appointments', 'payment_account', $this->selected($filters, 'account'));
        AppointmentReportMatch::person($query, 'appointments', 'doctor', $this->selected($filters, 'doctor'));
        AppointmentReportMatch::person($query, 'appointments', 'consultant', $this->selected($filters, 'consultant'));
        AppointmentReportMatch::sections($query, 'appointments', $this->selected($filters, 'section2'), 'subsection');
        AppointmentReportMatch::sections($query, 'appointments', $this->selected($filters, 'subsection'), 'section');
        foreach (['areas' => '$[*].tags[*]', 'extra' => '$[*].addons[*].name'] as $key => $path) {
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
        // بیعانه در دفتر مالی ثبت می‌شود؛ تخفیف ستون خود نوبت است.
        foreach (['debt' => $this->moneyExpression('appointments.debt'), 'discount' => $this->moneyExpression('appointments.discount'), 'deposit' => AppointmentReportMatch::deposit('appointments')] as $key => $expression) {
            AppointmentReportMatch::compare($query, $expression, data_get($filters, 'comparators.'.$key), data_get($filters, 'values.'.$key));
        }
        // پورسانت همین نوبت از دفتر حقوق؛ کیف پول از پروندهٔ همین نوبت در بازهٔ گزارش.
        foreach (ReportCommissionTotals::RESOURCE_TYPES as $key => $resourceType) {
            AppointmentReportMatch::compare($query, ReportCommissionTotals::expression('appointments.id', $resourceType), data_get($filters, 'comparators.'.$key), data_get($filters, 'values.'.$key));
        }
        foreach (ReportWalletTotals::TYPES as $key => $type) {
            $value = data_get($filters, 'values.'.$key);
            if ($value === null || $value === '' || ! is_numeric($value)) continue;
            [$sql, $bindings] = ReportWalletTotals::expression(self::patientIdExpression(), $type, $filters);
            $operator = ['lt' => '<', 'gt' => '>', 'eq' => '='][data_get($filters, 'comparators.'.$key, 'eq')] ?? '=';
            $query->whereRaw("{$sql} {$operator} CAST(? AS DECIMAL(18,2))", [...$bindings, (float) $value]);
        }

        $patientFields = ['name' => 'first_name', 'family' => 'last_name', 'nationalId' => 'national_id', 'city' => 'city'];
        $problem = trim((string) data_get($filters, 'values.problem', ''));
        $hasPatientFilters = collect($patientFields)->keys()->contains(fn ($key) => filled(data_get($filters, 'values.'.$key)))
            || data_get($filters, 'birthDate.from') || data_get($filters, 'birthDate.to')
            || $this->selected($filters, 'finstatus') || $problem !== '';
        if ($hasPatientFilters) $query->whereExists(function (QueryBuilder $patients) use ($filters, $patientFields) {
            $patients->selectRaw('1')->from('patients')->whereNull('patients.deleted_at');
            self::linkPatient($patients);
            foreach ($patientFields as $key => $column) $this->prefix($patients, 'patients.'.$column, data_get($filters, 'values.'.$key));
            if ($from = data_get($filters, 'birthDate.from')) $patients->where('patients.birth_date', '>=', $from);
            if ($to = data_get($filters, 'birthDate.to')) $patients->where('patients.birth_date', '<=', $to);
            if ($values = $this->selected($filters, 'finstatus')) $patients->whereIn('patients.financial_status', $values);
            if ($problem !== '') {
                $pattern = '%'.addcslashes($problem, '%_\\').'%';
                $patients->where(fn ($match) => $match->where('patients.medical_history', 'like', $pattern)->orWhere('patients.patient_history', 'like', $pattern));
            }
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
                WHERE (TRIM(return_appointments.status) = ? OR TRIM(return_appointments.done) = 'انجام شد')
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

        $reportDate = array_filter(['from' => data_get($filters, 'reportDate.from'), 'to' => data_get($filters, 'reportDate.to')]);

        return $records->map(function (Appointment $appointment) use ($byFile, $byPhone, $patientContexts, $reportDate) {
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
                reportDate: $reportDate,
            );
            return ['context' => $context, 'patient_id' => $patient->id, 'source_id' => $appointment->id];
        });
    }

    /** پروندهٔ نوبت با همان اولویت rows(): اول شمارهٔ پرونده، بعد شماره تماس. */
    private static function patientIdExpression(): string
    {
        $match = fn (string $column) => "(SELECT wallet_patients.id FROM patients AS wallet_patients"
            ." WHERE wallet_patients.deleted_at IS NULL AND appointments.{$column} IS NOT NULL AND appointments.{$column} <> ''"
            ." AND wallet_patients.{$column} = appointments.{$column} ORDER BY wallet_patients.id DESC LIMIT 1)";
        return 'COALESCE('.$match('file_number').', '.$match('phone').')';
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
        ReportTextMatch::apply($query, $column, $value);
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
