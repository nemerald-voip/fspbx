<?php

namespace App\Models\Telnyx;

use App\Models\Messages;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Model;
use App\Jobs\SendSmsNotificationToSlack;

/**
 * @property string|null $message_uuid
 */
class TelnyxOutboundSMS extends Model
{
    public $message_uuid;

    /**
     * Send the outbound SMS message via Telnyx V2.
     *
     * @return bool
     */
    public function send()
    {
        $message = Messages::find($this->message_uuid);
    
        if (!$message) {
            logger("Could not find SMS entity. SMS From " . ($this->from_did ?? 'Unknown') . " to " . ($this->to_did ?? 'Unknown'));
            return false;
        }
    
        // 1. Endpoint URL
        $url = rtrim(config('telnyx.message_base_url')) . '/messages';
    
        // 2. Prepare Payload
        $data = [
            'from' => $message->source,
            'to'   => $message->destination,
            'text' => $message->message,
            // 'messaging_profile_id' => '40019981-fb97-4a66-a7f3-4396dd1721db',
        ];

        // 3. Handle Media (MMS)
        // Assuming $message->media is stored as JSON. 
        // Telnyx expects 'media_urls' as an array of strings.
        if (!empty($message->media)) {
            $mediaItems = json_decode($message->media, true);
            
            // Extract URLs if the stored media is an array of objects (common in inbound storage)
            // or just use the array if it's already a list of URLs.
            $mediaUrls = [];
            if (is_array($mediaItems)) {
                foreach ($mediaItems as $item) {
                    if (is_array($item) && isset($item['url'])) {
                        $mediaUrls[] = $item['url'];
                    } elseif (is_string($item)) {
                        $mediaUrls[] = $item;
                    }
                }
            }

            if (!empty($mediaUrls)) {
                $data['media_urls'] = $mediaUrls;
                $data['type'] = 'MMS';
            } else {
                $data['type'] = 'SMS';
            }
        } else {
            $data['type'] = 'SMS';
        }

        // 4. Send Request with Bearer Token
        // Ensure you have 'telnyx.api_key' in your config/services.php or similar
        $response = Http::withToken(config('telnyx.api_key'))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])
            ->post($url, $data);
    
        // 5. Handle Response
        $result = $response->json();

        if ($response->successful()) {
            // Telnyx returns a 'data' object wrapper
            $responseData = $result['data'];

            // Telnyx ID
            $message->reference_id = $responseData['id'];
            
            // Status is inside the 'to' array (usually index 0 for single message)
            // Typical statuses: 'queued', 'sending'
            $status = $responseData['to'][0]['status'] ?? 'queued';
            
            $message->status =  $status;
            
        } else {            
            // Extract error details from Telnyx 'errors' array
            $errorMsg = "Unknown Error";
            if (isset($result['errors']) && is_array($result['errors']) && count($result['errors']) > 0) {
                $firstError = $result['errors'][0];
                $errorMsg = $firstError['detail'] ?? $firstError['title'] ?? "Code: " . ($firstError['code'] ?? 'N/A');
            }

            logger($errorMsg);
            
            logger()->error("Telnyx API error: " . json_encode($result));
            
            // Store reference ID if available even on failure (sometimes provided)
            if (isset($result['data']['id'])) {
                $message->reference_id = $result['data']['id'];
            }

            $message->status = $errorMsg ?? 'failed';

            // Pass specific error message to handler
            $this->handleError($message, $errorMsg);
        }
    
        $message->save();
    
        return;
    }

    private function handleError($message, $specificError = null)
    {
        $errorMessage = $specificError ?? $message->status;

        // Log the error or send it to Slack
        $error = "*Outbound Telnyx SMS Failed*: From: " . $message->source . " To: " . $message->destination . "\nError: " . $errorMessage;

        SendSmsNotificationToSlack::dispatch($error)->onQueue('messages');
    }
}