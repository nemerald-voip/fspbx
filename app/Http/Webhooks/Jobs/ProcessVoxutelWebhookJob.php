<?php

namespace App\Http\Webhooks\Jobs;

use App\Services\Messaging\Providers\MessagingWebhookParser;
use App\Services\Messaging\Providers\VoxutelWebhookParser;

class ProcessVoxutelWebhookJob extends ProcessMessagingWebhookJob
{
    protected function parser(): MessagingWebhookParser
    {
        return app(VoxutelWebhookParser::class);
    }
}
