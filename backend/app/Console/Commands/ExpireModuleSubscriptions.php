<?php

namespace App\Console\Commands;

use App\Services\ModuleSubscriptionService;
use Illuminate\Console\Command;

class ExpireModuleSubscriptions extends Command
{
    protected $signature = 'modules:expire-subscriptions';

    protected $description = 'Expire due module subscriptions and rebuild tenant active module ids.';

    public function handle(ModuleSubscriptionService $subscriptions): int
    {
        $count = $subscriptions->expireDue();
        $this->info("Expired {$count} module subscription(s).");

        return self::SUCCESS;
    }
}
