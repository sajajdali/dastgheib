<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\AppointmentAutomaticSmsService;
use App\Services\ShsmsService;
use Illuminate\Console\Command;
use Throwable;

class SendDueAutomaticSms extends Command
{
    protected $signature = 'automatic-sms:send-due {--tenant=}';
    protected $description = 'Send due automatic appointment SMS messages for tenant clinics';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($query, $tenant) => $query->whereKey($tenant))
            ->get();

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);
                $stats = app(AppointmentAutomaticSmsService::class)->sendDueForCurrentTenant(app(ShsmsService::class));
                $this->line(sprintf('%s: sent=%d failed=%d skipped=%d', $tenant->getTenantKey(), $stats['sent'], $stats['failed'], $stats['skipped']));
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
