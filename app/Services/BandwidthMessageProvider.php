<?php

namespace App\Services;

use App\Jobs\SendBandwidthSMS;
use App\Services\Interfaces\MessageProviderInterface;

class BandwidthMessageProvider implements MessageProviderInterface
{
    public function send($message_uuid)
    {
        // Implementation for sending SMS via Sinch
        SendBandwidthSMS::dispatch($message_uuid)->onQueue('messages');
    }


}
