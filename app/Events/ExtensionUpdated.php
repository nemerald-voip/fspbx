<?php

namespace App\Events;

use App\Models\Extensions;
use App\Models\CallCenterAgents;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class ExtensionUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $extension;
    public $originalAttributes;
    public $vmOriginalAttributes;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($extension, $originalAttributes, $vmOriginalAttributes)
    {
        $this->extension = $extension;
        $this->originalAttributes = $originalAttributes;
        $this->vmOriginalAttributes = $vmOriginalAttributes;
    }

}
