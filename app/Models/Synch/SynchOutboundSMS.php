<?php

namespace App\Models\Synch;

use App\Models\Messages;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string|null $domain_setting_value
 * @property string|null $to_did
 * @property string|null $from_did
 * @property string|null $message
 */
class SynchOutboundSMS extends Model
{

    public $from;
    public $to;
    public $text;
    public $message_uuid;

    /**
     * Send the outbound SMS message.
     *
     * @return bool
     */
    public function send()
    {
        $message = Messages::find($this->message_uuid);

        if(!$message) {
            logger("Could not find sms entity from ".$this->from_did." to ".$this->to_did);
        }

        // Logic to send the SMS message using a third-party Synch API,
        // This method should return a boolean indicating whether the message was sent successfully.

        $data = array(
            'from' => $this->from,
            'to' => $this->to,
            "text" => $this->text,
        );
        logger($data);
        // $response = Http::withHeaders([
        //     'Authorization' => 'Authorization: Bearer ' . config('synch.api_key'),
        //     'Content-Type' => 'application/json'
        // ])
        //     ->withBody(json_encode($data), 'application/json')
        //     ->post(config('synch.message_broker_url') . "/publishMessages");

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('synch.api_key'),
            'Content-Type' => 'application/json'
        ])
        ->asJson()
        ->post(config('synch.message_broker_url') . "/publishMessages", $data);

        logger(config('synch.api_key'));

        // Get result
        if (isset($response)) {
            $result = json_decode($response->body());
            logger([$result]);
            if (isset($result->code) && ($result->code >= 400)) {
                // $message->status = $result->message;
            }
            if (isset($result->guid)) {
                $message->status = "success";
            }
            // $message->save();
        }

        return true; // Change this to reflect the result of the API call.
    }

    /**
     * Determine if the outbound SMS message was sent successfully.
     *
     * @return bool
     */
    public function wasSent()
    {
        // Logic to determine if the message was sent successfully using a third-party API.

        return true; // Change this to reflect the result of the API call.
    }
}
