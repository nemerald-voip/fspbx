<?php

namespace App\Models\Commio;

use App\Models\Messages;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

/**
 * @property string|null $domain_setting_value
 * @property string|null $to_did
 * @property string|null $from_did
 * @property string|null $message
 * @property string|null $message_uuid
 */
class CommioInboundSMS extends Model
{
    public $domain_setting_value;
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

        if (!$message) {
            Log::alert("Could not find sms entity from ".$this->from_did." to ".$this->to_did);
        }

        // Logic to send the SMS message using a third-party Commio API,
        // This method should return a boolean indicating whether the message was sent successfully.
        $response = Http::ringotel_api()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode([
                'method' => 'message',
                'params' => [
                    'orgid' => $this->domain_setting_value,
                    'from' => $this->from_did,
                    'to' => $this->to_did,
                    'content' => $this->message
                ]
            ]), 'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                Notification::route('mail', 'dexter@stellarvoip.com')
                    ->notify(new StatusUpdate("error"));
                return false;
            })
            ->json();

        if ($message) {
            if (isset($response['result'])) {
                $message->status = 'success';
            } elseif (isset($response['error'])) {
                $message->status = json_encode($response['error']);
            } else {
                $message->status = 'unknown';
            }
            $message->save();
        }
        //Log::alert($response);

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
