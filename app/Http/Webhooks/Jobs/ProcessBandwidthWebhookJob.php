<?php

namespace App\Http\Webhooks\Jobs;

use App\Services\Messaging\Providers\BandwidthWebhookParser;
use App\Services\Messaging\Providers\MessagingWebhookParser;

class ProcessBandwidthWebhookJob extends ProcessMessagingWebhookJob
{
    protected function parser(): MessagingWebhookParser
    {
        return app(BandwidthWebhookParser::class);
    }
}