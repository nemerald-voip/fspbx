<?php

namespace App\Services;

use App\Jobs\SendApidazeSMS;
use App\Services\Interfaces\MessageProviderInterface;

class ApidazeMessageProvider implements MessageProviderInterface
{
    public function send($message_uuid)
    {
        // Implementation for sending SMS via Sinch
        SendApidazeSMS::dispatch($message_uuid)->onQueue('messages');
    }


}
