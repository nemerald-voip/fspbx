<?php

namespace App\Events;

use App\Models\Extensions;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class ExtensionDeleted
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
