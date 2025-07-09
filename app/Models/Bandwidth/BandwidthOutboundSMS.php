<?php

namespace App\Models\Bandwidth;

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
            logger("Could not find SMS entity. SMS From " . $this->from_did . " to " . $this->to_did);
            return false;
        }
    
        // Build the endpoint URL
        $url = rtrim(config('bandwidth.message_base_url'), '/')
             . '/users/' . config('bandwidth.account_id') . '/messages';
    
        // Prepare payload (numbers already E.164)
        $data = [
            'from'          => $message->source,
            'to'            => [$message->destination],
            'applicationId' => config('bandwidth.application_id'),
            'text'          => $message->message,
        ];
    
        // Prepare HTTP Basic Auth header
        $credentials = config('bandwidth.api_token') . ':' . config('bandwidth.api_secret');
        $authHeader  = 'Basic ' . base64_encode($credentials);
    
        // Send the request
        $response = Http::withHeaders([
                'Authorization' => $authHeader,
                'Content-Type'  => 'application/json',
            ])
            ->post($url, $data);
    
        logger($response->body());
    
        if ($response->successful() && $response->status() === 202) {
            $result = $response->json();
            $message->status = 'pending';
            $message->reference_id = $result['id'];
        } else {
            $result = $response->json();
            $message->status = 'failed';
            $message->reference_id = $result['id'];
            if (isset($result['error'])) {
                logger()->error("Bandwidth API error: " . json_encode($result['error']));
            }
            $this->handleError($message);
        }
    
        $message->save();
    
        return $message->status === 'success';
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
