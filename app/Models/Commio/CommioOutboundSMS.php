<?php

namespace App\Models\Commio;

use App\Models\Messages;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string|null $domain_setting_value
 * @property string|null $to_did
 * @property string|null $from_did
 * @property string|null $message
 */
class CommioOutboundSMS extends Model
{

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
            Log::alert("Could not find sms entity from ".$this->from_did." to ".$this->to_did);
        }

        // Logic to send the SMS message using a third-party Commio API,
        // This method should return a boolean indicating whether the message was sent successfully.

        $data = array(
            'from_did' => $this->formatNumber($message->source),
            'to_did' => $this->formatNumber($message->destination),
            "message" => $message->message,
            "message_uuid" => $message->message_uuid
        );
        
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode(config('commio.username') . ":" . config('commio.token')),
            'Content-Type' => 'application/json'
        ])
            ->withBody(json_encode($data), 'application/json')
            ->post('https://api.thinq.com/account/' . config('commio.account_id') . '/product/origination/sms/send');

        // Get result
        if (isset($response)) {
            $result = json_decode($response->body());
            // logger([$result]);
            if (isset($result->code) && ($result->code >= 400)) {
                $message->status = $result->message;
            }
            if (isset($result->guid)) {
                $message->status = "success";
            }
            $message->save();
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

    private function formatNumber($phoneNumber){
        return str_replace("+1", "", $phoneNumber);
    }
}
