<?php

namespace App\Models\Sinch;

use App\Models\Messages;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Model;
use App\Jobs\SendSmsNotificationToSlack;

/**
 * @property string|null $message_uuid
 */
class BandwidthOutboundSMS extends Model
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

        if (!$message) {
            logger("Could not find sms entity. SMS From " . $this->from_did . " to " . $this->to_did);
            return;
        }

        // Logic to send the SMS message using a third-party Sinch API,
        // This method should return a boolean indicating whether the message was sent successfully.

        $data = array(
            'from' => preg_replace('/[^0-9]/', '', $message->source),
            'to' => [
                preg_replace('/[^0-9]/', '', $message->destination),
            ],
            "text" => $message->message,
            "message_uuid" => $message->message_uuid
        );

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('sinch.api_key'),
            'Content-Type' => 'application/json'
        ])
            ->asJson()
            ->post(config('sinch.message_broker_url') . "/publishMessages", $data);

        // For debugging puporses only
        // Log::info('Request URL:', [config('sinch.message_broker_url') . "/publishMessages"]);
        // Log::info('Request Headers:', [
        //     'Authorization' => 'Bearer ' . config('sinch.api_key'),
        //     'Content-Type' => 'application/json'
        // ]);
        // Log::info('Request Data:', $data);

        // Get result
        if (isset($response)) {
            $result = json_decode($response->body());
            logger($response->body());

            // Determine if the operation was successful
            if ($response->successful() && isset($result->success) && $result->success) {
                $message->status = 'success';
                if (isset($result->result->referenceId)) {
                    $message->reference_id = $result->result->referenceId;
                }
            } else {
                if (isset($result->reason, $result->detail)) {
                    $message->status = $result->detail;
                } elseif (isset($result->response) && !$result->response->success) {
                    $message->status = $result->response->detail;
                } else {
                    $message->status = 'unknown error';
                }
    
                if (isset($result->errors)) {
                    logger()->error("Error details:", $result->errors);
                }
                $this->handleError($message);
            }
    
            $message->save();
        } else {
            logger()->error('SMS error. No response received from Sinch API.');
            $message->status = 'failed';
            $message->save();
            $this->handleError($message);
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

    private function handleError($message)
    {

        // Log the error or send it to Slack
        $error = "*Outbound SMS Failed*: From: " . $message->source . " To: " . $message->destination . "\n" . $message->status;

        SendSmsNotificationToSlack::dispatch($error)->onQueue('messages');

    }
}
