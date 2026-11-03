<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\AttendanceMonthController;
use App\Http\Controllers\AppointmentController;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

class ResyncResourceEarnings extends Command
{
    protected $signature = 'payroll:resync-earnings {--tenant=}';
    protected $description = 'Rebuild doctor and staff commission and attendance lines, keeping manual payroll edits';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($query, $tenant) => $query->whereKey($tenant))
            ->get();

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);
                $appointments = app(AppointmentController::class)->resyncAllResourceEarnings();
                $attendance = app(AttendanceMonthController::class)->resyncAttendanceEarnings();
                $this->line(sprintf('%s: appointments=%d attendance_months=%d', $tenant->getTenantKey(), $appointments, $attendance));
            } catch (Throwable $error) {
                report($error);
                $this->error($tenant->getTenantKey().': '.$error->getMessage());
            } finally {
                tenancy()->end();
            }
        }

        return self::SUCCESS;
    }
}
