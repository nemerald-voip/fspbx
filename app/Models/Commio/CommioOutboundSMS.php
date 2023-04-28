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

    public $to_did;
    public $from_did;
    public $message;
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
            'from_did' => $this->from_did,
            'to_did' => $this->to_did,
            "message" => $this->message,
        );

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode(env('THINQ_USERNAME') . ":" . env('THINQ_TOKEN')),
            'Content-Type' => 'application/json'
        ])
            ->withBody(json_encode($data), 'application/json')
            ->post('https://api.thinq.com/account/' . env('THINQ_ACCOUNT_ID') . '/product/origination/sms/send');

        // Get result
        if (isset($response)) {
            $result = json_decode($response->body());
            // dd($result);
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
}
