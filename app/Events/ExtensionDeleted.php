<?php

namespace App\Events;

use App\Models\Extensions;
use App\Models\CallCenterAgents;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class ExtensionDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $extension;
    public $voicemail;
    public $agent;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Extensions $extension, array $originalAttributes)
    {
        $this->extension = $extension;
        $this->voicemail = $extension->voicemail;

        // Find agent relation if any
        if ($originalAttributes['extension'] == $extension->extension) {
            $this->agent = $extension->agent;
        } else {
            $this->agent = CallCenterAgents::where('agent_id', $originalAttributes['extension'])
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->first();
        }

        logger('Extension Deleted Event');
    }

}
