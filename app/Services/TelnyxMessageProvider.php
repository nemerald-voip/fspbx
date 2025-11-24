<?php

namespace App\Services;

use App\Jobs\SendTelnyxSMS;
use App\Services\Interfaces\MessageProviderInterface;

class TelnyxMessageProvider implements MessageProviderInterface
{
    public function send($message_uuid)
    {
        // Implementation for sending SMS via Sinch
        SendTelnyxSMS::dispatch($message_uuid)->onQueue('messages');
    }


}
