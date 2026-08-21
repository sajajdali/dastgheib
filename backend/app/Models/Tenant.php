<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    public const BASE_MODULE_IDS = [
        'patients',
        'followups',
        'beauty',
        'tickets',
        'services',
        'resources',
        'payroll',
        'settings',
    ];

    protected static function booted(): void
    {
        static::saving(function (Tenant $tenant): void {
            $moduleIds = is_array($tenant->module_ids ?? null)
                ? $tenant->module_ids
                : [];

            $tenant->module_ids = static::withBaseModules($moduleIds);
        });
    }

    public static function withBaseModules(array $moduleIds): array
    {
        return array_values(array_unique([...$moduleIds, ...static::BASE_MODULE_IDS]));
    }
}
