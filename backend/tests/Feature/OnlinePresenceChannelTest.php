<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlinePresenceChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_join_online_presence_channel(): void
    {
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user, 'web')
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'presence-clinic.online',
            ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['auth', 'channel_data']);
    }
}
