<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class TwilioWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        $payload = $request->all();

        // Both inbound messages and delivery status callbacks include MessageSid (or SmsSid).
        $shouldProcess = isset($payload['MessageSid']) || isset($payload['SmsSid']);

        messaging_webhook_debug('TwilioWebhookProfile shouldProcess()', [
            'has_message_sid' => isset($payload['MessageSid']),
            'has_sms_sid'     => isset($payload['SmsSid']),
            'should_process'  => $shouldProcess,
        ]);

        return $shouldProcess;
    }
}
