<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class BandwidthWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        return true;

    }
}
