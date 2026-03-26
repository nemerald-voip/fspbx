<?php

namespace App\Http\Webhooks\Jobs;

use App\Services\Messaging\Providers\MessagingWebhookParser;
use App\Services\Messaging\Providers\VoipMsWebhookParser;

class ProcessVoipMsWebhookJob extends ProcessMessagingWebhookJob
{
    protected function parser(): MessagingWebhookParser
    {
        messaging_webhook_debug('ProcessVoipMsWebhookJob parser() resolved', [
            'parser' => VoipMsWebhookParser::class,
        ]);

        return app(VoipMsWebhookParser::class);
    }
}