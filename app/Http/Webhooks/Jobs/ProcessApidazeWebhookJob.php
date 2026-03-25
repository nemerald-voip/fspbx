<?php

namespace App\Http\Webhooks\Jobs;

use App\Services\Messaging\Providers\ApidazeWebhookParser;
use App\Services\Messaging\Providers\MessagingWebhookParser;

class ProcessApidazeWebhookJob extends ProcessMessagingWebhookJob
{
    protected function parser(): MessagingWebhookParser
    {
        messaging_webhook_debug('ProcessApidazeWebhookJob parser() resolved', [
            'parser' => ApidazeWebhookParser::class,
        ]);

        return app(ApidazeWebhookParser::class);
    }
}