<?php

namespace App\Http\Webhooks\Jobs;

use App\Services\Messaging\Providers\MessagingWebhookParser;
use App\Services\Messaging\Providers\SinchWebhookParser;

class ProcessSinchWebhookJob extends ProcessMessagingWebhookJob
{
    protected function parser(): MessagingWebhookParser
    {
        return app(SinchWebhookParser::class);
    }
}