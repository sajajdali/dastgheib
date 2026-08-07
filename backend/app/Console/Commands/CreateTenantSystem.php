<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use App\Models\Tenant;

class CreateTenantSystem extends Command
{
    protected $signature = 'tenant:create-system
        {id : Tenant id, e.g. clinic1}
        {domain : Domain used to access this tenant, e.g. clinic1.localhost}
        {--seed : Seed the tenant database after migrations}';

    protected $description = 'Create a tenant, attach its domain, create its database, and optionally seed it.';

    public function handle(): int
    {
        $id = Str::slug((string) $this->argument('id'), '_');
        $domain = Str::lower(trim((string) $this->argument('domain')));

        if ($id === '' || $domain === '') {
            $this->error('Tenant id and domain are required.');

            return self::FAILURE;
        }

        if (Tenant::query()->whereKey($id)->exists()) {
            $this->error("Tenant [$id] already exists.");

            return self::FAILURE;
        }

        $tenant = Tenant::create(['id' => $id]);
        $tenant->domains()->create(['domain' => $domain]);

        if ($this->option('seed')) {
            Artisan::call('tenants:seed', [
                '--tenants' => [$id],
                '--force' => true,
            ]);

            $this->line(Artisan::output());
        }

        $this->info("Tenant [$id] is ready on [$domain].");

        return self::SUCCESS;
    }
}
