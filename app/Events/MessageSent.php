<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // Use Now for lower latency
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $payload;
    public $roomId;

    /**
     * @param array $payload  Data for DeepChat: ['text' => '...', 'role' => 'ai']
     * @param string $roomId  The normalized phone number (e.g., 16467052267)
     */
    public function __construct($payload, $roomId)
    {
        $this->payload = $payload;
        $this->roomId = $roomId;
    }

    // 1. Channel Name: 'room.{id}'
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('room.' . $this->roomId),
        ];
    }

    // 2. Event Name: client-side listens for '.message.new'
    public function broadcastAs(): string
    {
        return 'message.new';
    }
    
    // 3. Data to send: We only send the payload DeepChat needs
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}