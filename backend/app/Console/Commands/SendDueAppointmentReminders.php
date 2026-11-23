<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminderSms;
use App\Models\Tenant;
use App\Services\ShsmsService;
use Illuminate\Console\Command;
use Throwable;

class SendDueAppointmentReminders extends Command
{
    protected $signature = 'appointment-reminders:send-due {--tenant=}';

    protected $description = 'Send day-before appointment reminder SMS for scheduled («وقت داده شد») bookings';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($query, $tenant) => $query->whereKey($tenant))
            ->get();

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);
                (new SendAppointmentReminderSms)->handle(app(ShsmsService::class));
                $this->line($tenant->getTenantKey().': reminder pass complete');
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
