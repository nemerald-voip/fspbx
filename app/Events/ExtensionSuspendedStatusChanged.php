<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExtensionSuspendedStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $model;
    public $user; // User who dispatched the event

    /**
     * Create a new event instance.
     */
    public function __construct($model)
    {
        $this->model = $model;
        $this->user = auth()->user();
    }

}
