<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class ApidazeWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        $payload = $request->all();
        $type = data_get($payload, 'type');

        $shouldProcess = in_array($type, ['incomingWebhookSMS', 'incomingWebhookMMS'], true)
            || (
                filled(data_get($payload, 'caller_id_number') ?? data_get($payload, 'from'))
                && filled(data_get($payload, 'destination_number') ?? data_get($payload, 'to'))
            );

        messaging_webhook_debug('Apidaze webhook profile evaluated request', [
            'type' => $type,
            'should_process' => $shouldProcess,
        ]);

        return $shouldProcess;

    }
}
