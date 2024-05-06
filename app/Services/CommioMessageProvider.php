<?php

namespace App\Services;

use App\Jobs\SendCommioSMS;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;
use App\Services\Interfaces\MessageProviderInterface;

class CommioMessageProvider implements MessageProviderInterface
{
    public function send($message_uuid)
    {
        // $data = array(
        //     'from_did' => $this->formatNumber($message->source),
        //     'to_did' => $this->formatNumber($message->destination),
        //     "message" => $message->message,
        //     "message_uuid" => $message->message_uuid
        // );

        // logger($data);

        // Implementation for sending SMS via Thinq
        SendCommioSMS::dispatch($message_uuid)->onQueue('messages');
    }

    private function formatNumber($phoneNumber){
        return str_replace("+1", "", $phoneNumber);
    }

    private function getNationalFormat($phoneNumber){
        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($phoneNumber, 'US');

            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                $formattedNumber = $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
            } 
        } catch (NumberParseException $e) {
            logger($e->getMessage());
        }

        return $formattedNumber;
    }
}
