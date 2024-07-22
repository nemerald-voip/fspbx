<?php

namespace App\Services;

use App\Jobs\SendSinchSMS;
use App\Services\Interfaces\MessageProviderInterface;

class SinchMessageProvider implements MessageProviderInterface
{
    public function send($message_uuid)
    {
        // Implementation for sending SMS via Sinch
        SendSinchSMS::dispatch($message_uuid)->onQueue('messages');
    }


}
