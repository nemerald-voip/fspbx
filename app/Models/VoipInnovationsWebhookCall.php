<?php

namespace App\Models;

use Illuminate\Http\Request;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\WebhookConfig;

class VoipInnovationsWebhookCall extends WhCall
{
    public static function storeWebhook(WebhookConfig $config, Request $request): WebhookCall
    {
        // The callback path contains a bearer secret.
        return static::create([
            'name' => $config->name,
            'url' => '/webhook/voipinnovations/sms/[redacted]',
            'headers' => [],
            'payload' => $request->json()->all(),
        ]);
    }
}
