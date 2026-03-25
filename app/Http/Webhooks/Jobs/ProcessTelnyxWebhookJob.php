<?php

namespace App\Http\Webhooks\Jobs;

use App\Services\Messaging\Providers\MessagingWebhookParser;
use App\Services\Messaging\Providers\TelnyxWebhookParser;

class ProcessTelnyxWebhookJob extends ProcessMessagingWebhookJob
{
    protected function parser(): MessagingWebhookParser
    {
        messaging_webhook_debug('ProcessTelnyxWebhookJob parser() resolved', [
            'parser' => TelnyxWebhookParser::class,
        ]);

        return app(TelnyxWebhookParser::class);
    }
}