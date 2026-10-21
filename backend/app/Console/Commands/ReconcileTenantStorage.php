<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantStorageService;
use Illuminate\Console\Command;

class ReconcileTenantStorage extends Command
{
    protected $signature = 'storage:reconcile {tenant? : Tenant ID, or omit for all sites}';
    protected $description = 'Recount tenant uploaded files and repair storage usage counters.';

    public function handle(TenantStorageService $storage): int
    {
        $id = $this->argument('tenant');
        $tenants = Tenant::query()->when($id, fn ($query) => $query->whereKey($id))->get();
        if ($id && $tenants->isEmpty()) {
            $this->error('Tenant not found.');
            return self::FAILURE;
        }

        $failures = 0;
        foreach ($tenants as $tenant) {
            try {
                $usage = $storage->reconcileTenant($tenant);
                $this->line($tenant->getTenantKey().': '.$usage['used_bytes'].' bytes');
            } catch (\Throwable $exception) {
                $failures++;
                $this->error($tenant->getTenantKey().': '.$exception->getMessage());
            }
        }

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
