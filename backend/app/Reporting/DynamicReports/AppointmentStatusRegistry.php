<?php

namespace App\Reporting\DynamicReports;

final class AppointmentStatusRegistry
{
    public function all(): array
    {
        return array_values(config('dynamic_reports.appointment_statuses', []));
    }

    public function contains(string $status): bool
    {
        return in_array($status, $this->all(), true);
    }
}
