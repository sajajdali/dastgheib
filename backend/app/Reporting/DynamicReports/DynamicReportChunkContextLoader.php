<?php

namespace App\Reporting\DynamicReports;

use App\Models\Appointment;
use App\Models\Patient;
use App\Services\CustomerLevelService;
use Illuminate\Support\Collection;

final class DynamicReportChunkContextLoader
{
    private ?array $levelSettings = null;

    public function __construct(private readonly CustomerLevelService $levels) {}

    /** @return Collection<int, ReportPatientContext> */
    public function load(Collection $patients, array $filters = []): Collection
    {
        $index = $this->appointmentIndex($patients);
        $selectedStatuses = collect(data_get($filters, 'multi.status', []))->filter();

        return $patients->map(function (Patient $patient) use ($index, $selectedStatuses) {
            $history = $this->historyFor($patient, $index);
            $latest = $selectedStatuses->isEmpty()
                ? $history->first()
                : $history->first(fn (Appointment $appointment) => $selectedStatuses->contains(trim((string) $appointment->status)));
            $level = $patient->customer_level === 'problematic'
                ? 'problematic'
                : $this->levels->calculate($history, $this->levelSettings ??= CustomerLevelService::settings());

            return new ReportPatientContext(
                patient: $patient,
                latestAppointment: $latest,
                appointmentHistory: $history,
                referrers: $index['referrers'],
                customerLevel: $level,
            );
        });
    }

    private function appointmentIndex(Collection $patients): array
    {
        $fileNumbers = $patients->pluck('file_number')->filter()->unique()->values();
        $phones = $patients->pluck('phone')->filter()->unique()->values();
        if ($fileNumbers->isEmpty() && $phones->isEmpty()) {
            return ['file_history' => collect(), 'phone_history' => collect(), 'referrers' => collect()];
        }

        $appointments = Appointment::query()
            ->where(function ($query) use ($fileNumbers, $phones) {
                if ($fileNumbers->isNotEmpty()) $query->whereIn('file_number', $fileNumbers);
                if ($phones->isNotEmpty()) $query->{$fileNumbers->isNotEmpty() ? 'orWhereIn' : 'whereIn'}('phone', $phones);
            })
            ->orderByDesc('id')
            ->get();

        $referrers = Patient::query()
            ->whereIn('phone', $appointments->pluck('referrer_phone')->filter()->unique())
            ->get(['id', 'first_name', 'last_name', 'phone'])
            ->keyBy('phone');

        return [
            'file_history' => $appointments->filter->file_number->groupBy('file_number'),
            'phone_history' => $appointments->filter->phone->groupBy('phone'),
            'referrers' => $referrers,
        ];
    }

    private function historyFor(Patient $patient, array $index): Collection
    {
        if ($patient->file_number && $index['file_history']->has($patient->file_number)) {
            return $index['file_history']->get($patient->file_number)->values();
        }
        if ($patient->phone && $index['phone_history']->has($patient->phone)) {
            return $index['phone_history']->get($patient->phone)->values();
        }
        return collect();
    }
}
