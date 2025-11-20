<?php

namespace App\Models\ClickSend;

use App\Models\Messages;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Model;
use App\Jobs\SendSmsNotificationToSlack;

/**
 * @property string|null $message_uuid
 */
class ClickSendOutboundSMS extends Model
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

        // Build the ClickSend endpoint URL
        $url = rtrim(config('clicksend.base_url'), '/')
            . '/v3/sms/send';

        // Prepare payload
        $data = [
            'messages' => [[
                'to'     => $message->destination,   // E.164
                'body'   => $message->message,
                'from'   => $message->source,
                'source' => config('app.name'),
                'custom_string' => (string) $message->uuid,
            ]],
        ];

        // Send the request with Basic Auth (username: api_key)
        $response = Http::withBasicAuth(
            config('clicksend.username'),
            config('clicksend.api_key')
        )
            ->acceptJson()
            ->post($url, $data);

        $result = $response->json() ?? [];

        // logger($result);

        if (
            $response->successful()
            && ($result['response_code'] ?? null) === 'SUCCESS'
            && !empty($result['data']['messages'][0])
        ) {
            $msgData = $result['data']['messages'][0];

            // ClickSend per-message status is usually "SUCCESS" on queue
            $message->status       = 'success';
            $message->reference_id = $msgData['message_id'] ?? null;
        } else {
            $message->status = 'failed';

            // Try to capture something meaningful as reference_id
            $message->reference_id = $result['data']['messages'][0]['message_id']
                ?? $result['response_code']
                ?? null;

            logger()->error('ClickSend API error', [
                'status'  => $response->status(),
                'body'    => $result,
            ]);

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
