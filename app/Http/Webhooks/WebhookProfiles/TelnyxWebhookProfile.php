<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class TelnyxWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        $eventType = (string) data_get($request->all(), 'data.event_type', '');

        $shouldProcess = in_array($eventType, [
            'message.received',
            'message.finalized',
        ], true);

        messaging_webhook_debug('TelnyxWebhookProfile shouldProcess()', [
            'event_type' => $eventType,
            'should_process' => $shouldProcess,
        ]);

        return $shouldProcess;
    }
}