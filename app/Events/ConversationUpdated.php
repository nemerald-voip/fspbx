<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $payload;
    public $extensionUuid;

    public function __construct($payload, $extensionUuid)
    {
        $this->payload = $payload;
        $this->extensionUuid = $extensionUuid;
    }

    public function broadcastOn(): array
    {
        // Channel: private-extension.{UUID}
        return [
            new PrivateChannel('extension.' . $this->extensionUuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}