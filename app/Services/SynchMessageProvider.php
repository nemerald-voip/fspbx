<?php

namespace App\Services;

use App\Jobs\SendSynchSMS;
use App\Services\Interfaces\MessageProviderInterface;

class SynchMessageProvider implements MessageProviderInterface
{
    public function send($message)
    {
        $data = array(
            'from' => preg_replace('/[^0-9]/', '', $message->source),
            'to' => [
                preg_replace('/[^0-9]/', '', $message->destination),
            ],
            "text" => $message->message,
            "message_uuid" => $message->message_uuid
        );

        // Implementation for sending SMS via Synch
        SendSynchSMS::dispatch($data)->onQueue('messages');
    }


}
