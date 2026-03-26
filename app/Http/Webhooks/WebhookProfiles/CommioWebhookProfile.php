<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class CommioWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        $payload = $request->all();
        $type = strtoupper((string) ($payload['type'] ?? ''));
        $userAgent = strtolower((string) $request->userAgent());

        $shouldProcess = str_contains($userAgent, 'thinq-sms')
            && filled($payload['from'] ?? null)
            && filled($payload['to'] ?? null)
            && in_array($type, ['SMS', 'MMS'], true);

        messaging_webhook_debug('Commio webhook profile evaluated request', [
            'should_process' => $shouldProcess,
            'type' => $payload['type'] ?? null,
            'user_agent' => $request->userAgent(),
            'payload_keys' => array_keys($payload),
        ]);

        return $shouldProcess;
    }
}