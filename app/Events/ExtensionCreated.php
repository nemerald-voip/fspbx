<?php

namespace App\Events;

use App\Models\Extensions;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class ExtensionCreated
{
    public $extension;

    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Extensions $extension)
    {
        $this->extension = $extension;
    }


}
