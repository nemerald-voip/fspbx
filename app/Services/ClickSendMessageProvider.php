<?php

namespace App\Services;

use App\Jobs\SendClickSendSMS;
use App\Services\Interfaces\MessageProviderInterface;

class ClickSendMessageProvider implements MessageProviderInterface
{
    public function send($message_uuid)
    {
        // Implementation for sending SMS via Sinch
        SendClickSendSMS::dispatch($message_uuid)->onQueue('messages');
    }


}
