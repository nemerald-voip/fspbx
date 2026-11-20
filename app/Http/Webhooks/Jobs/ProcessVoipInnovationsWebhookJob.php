<?php

namespace App\Http\Webhooks\Jobs;

use App\Services\Messaging\Providers\MessagingWebhookParser;
use App\Services\Messaging\Providers\VoipInnovationsWebhookParser;

class ProcessVoipInnovationsWebhookJob extends ProcessMessagingWebhookJob
{
    protected function parser(): MessagingWebhookParser
    {
        return app(VoipInnovationsWebhookParser::class);
    }
}
