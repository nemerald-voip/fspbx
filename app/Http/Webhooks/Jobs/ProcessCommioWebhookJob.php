<?php

namespace App\Http\Webhooks\Jobs;

use App\Services\Messaging\Providers\CommioWebhookParser;
use App\Services\Messaging\Providers\MessagingWebhookParser;

class ProcessCommioWebhookJob extends ProcessMessagingWebhookJob
{
    protected function parser(): MessagingWebhookParser
    {
        messaging_webhook_debug('ProcessCommioWebhookJob parser() resolved', [
            'parser' => CommioWebhookParser::class,
        ]);

        return app(CommioWebhookParser::class);
    }
}