<?php

namespace App\Http\Webhooks\Jobs;

use App\Services\Messaging\Providers\ClickSendWebhookParser;
use App\Services\Messaging\Providers\MessagingWebhookParser;

class ProcessClickSendWebhookJob extends ProcessMessagingWebhookJob
{
    protected function parser(): MessagingWebhookParser
    {
        messaging_webhook_debug('ProcessClickSendWebhookJob parser() resolved', [
            'parser' => ClickSendWebhookParser::class,
        ]);

        return app(ClickSendWebhookParser::class);
    }
}