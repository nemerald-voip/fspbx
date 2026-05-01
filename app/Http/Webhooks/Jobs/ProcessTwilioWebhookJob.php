<?php

namespace App\Http\Webhooks\Jobs;

use App\Services\Messaging\Providers\MessagingWebhookParser;
use App\Services\Messaging\Providers\TwilioWebhookParser;

class ProcessTwilioWebhookJob extends ProcessMessagingWebhookJob
{
    protected function parser(): MessagingWebhookParser
    {
        messaging_webhook_debug('ProcessTwilioWebhookJob parser() resolved', [
            'parser' => TwilioWebhookParser::class,
        ]);

        return app(TwilioWebhookParser::class);
    }
}
