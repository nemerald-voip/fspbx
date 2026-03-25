<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class BulkVSWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        $payload = $request->all();

        $shouldProcess = filled($payload['From'] ?? null)
            && is_array($payload['To'] ?? null)
            && ! empty($payload['To']);

        messaging_webhook_debug('BulkVSWebhookProfile shouldProcess()', [
            'should_process' => $shouldProcess,
            'payload_keys' => array_keys($payload),
        ]);

        return $shouldProcess;
    }
}