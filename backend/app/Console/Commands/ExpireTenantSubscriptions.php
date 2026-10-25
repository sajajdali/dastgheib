<?php

namespace App\Console\Commands;

use App\Services\TenantSubscriptionService;
use Illuminate\Console\Command;

class ExpireTenantSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';
    protected $description = 'Expire tenant subscriptions whose end time has passed.';

    public function handle(TenantSubscriptionService $subscriptions): int
    {
        $count = $subscriptions->expireDue();
        $this->info("Expired {$count} tenant subscription(s).");

        return self::SUCCESS;
    }
}
