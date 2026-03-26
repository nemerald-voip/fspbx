<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class VoipMsWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        $eventType = (string) data_get($request->all(), 'data.event_type', '');
        $type = strtoupper((string) data_get($request->all(), 'data.payload.type', ''));

        $shouldProcess = $eventType === 'message.received'
            && filled(data_get($request->all(), 'data.payload.from.phone_number'))
            && ! empty(data_get($request->all(), 'data.payload.to', []))
            && in_array($type, ['SMS', 'MMS'], true);

        messaging_webhook_debug('VoipMsWebhookProfile shouldProcess()', [
            'should_process' => $shouldProcess,
            'event_type' => $eventType,
            'type' => $type,
        ]);

        return $shouldProcess;
    }
}