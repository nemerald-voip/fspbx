<?php

namespace App\Http\Webhooks\Jobs;

use App\Services\Messaging\Providers\FiberneticsWebhookParser;
use App\Services\Messaging\Providers\MessagingWebhookParser;

class ProcessFiberneticsWebhookJob extends ProcessMessagingWebhookJob
{
    protected function parser(): MessagingWebhookParser
    {
        return app(FiberneticsWebhookParser::class);
    }
}
