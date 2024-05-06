<?php

namespace App\Services;

use App\Jobs\SendSynchSMS;
use App\Services\Interfaces\MessageProviderInterface;

class SynchMessageProvider implements MessageProviderInterface
{
    public function send($message_uuid)
    {
        // Implementation for sending SMS via Synch
        SendSynchSMS::dispatch($message_uuid)->onQueue('messages');
    }


}
