<?php

namespace App\Reporting\DynamicReports;

use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Support\Collection;

final class ReportPatientContext
{
    public function __construct(
        public readonly Patient $patient,
        public readonly ?Appointment $latestAppointment,
        public readonly Collection $appointmentHistory,
        public readonly Collection $referrers,
        public readonly string $customerLevel,
    ) {}
}
