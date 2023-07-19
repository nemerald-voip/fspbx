<?php

namespace App\Events;

use App\Models\Extensions;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExtensionUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $extension;
    public $voicemail;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Extensions $extension)
    {
        $this->extension = $extension;
        $this->voicemail = $extension->voicemail;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    // public function broadcastOn()
    // {
    //     return new PrivateChannel('channel-name');
    // }
}
