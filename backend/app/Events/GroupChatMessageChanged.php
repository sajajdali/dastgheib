<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class GroupChatMessageChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  string  $action  created|updated|deleted
     * @param  array  $message  Already-masked payload from GroupChatMessage::toChatArray().
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $action,
        public readonly array $message,
    ) {}

    public static function channelName(string $tenantId): string
    {
        return "clinic.{$tenantId}.group-chat";
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::channelName($this->tenantId))];
    }

    public function broadcastAs(): string
    {
        return 'group-chat.message';
    }

    public function broadcastWith(): array
    {
        return ['action' => $this->action, 'message' => $this->message];
    }
}
