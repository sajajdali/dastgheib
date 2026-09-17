<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class OnlinePresenceChannelTest extends TestCase
{
    public function test_presence_channel_is_scoped_to_the_current_tenant(): void
    {
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');

        $user = new User(['name' => 'Test User', 'avatar_url' => null]);
        $user->setAttribute('id', 10);
        $user->setRelation('roles', collect());

        $tenant = new Tenant();
        $tenant->setAttribute('id', 'clinic-a');
        tenancy()->tenant = $tenant;
        tenancy()->initialized = true;

        try {
            $authorizer = Broadcast::getChannels()->get('clinic.{tenantId}.online');

            $this->assertIsCallable($authorizer);
            $this->assertIsArray($authorizer($user, 'clinic-a'));
            $this->assertFalse($authorizer($user, 'clinic-b'));
        } finally {
            tenancy()->tenant = null;
            tenancy()->initialized = false;
        }
    }
}
