<?php

namespace App\Http\Webhooks\Jobs;

use App\Services\Messaging\Providers\BulkVSWebhookParser;
use App\Services\Messaging\Providers\MessagingWebhookParser;

class ProcessBulkVSWebhookJob extends ProcessMessagingWebhookJob
{
    protected function parser(): MessagingWebhookParser
    {
        messaging_webhook_debug('ProcessBulkVSWebhookJob parser() resolved', [
            'parser' => BulkVSWebhookParser::class,
        ]);

        return app(BulkVSWebhookParser::class);
    }
}