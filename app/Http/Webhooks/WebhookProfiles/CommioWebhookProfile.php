<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Throwable;
use App\Models\User;
use App\Models\Voicemails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;
use App\Jobs\SendFaxInvalidEmailNotification;
use App\Jobs\SendFaxInvalidDestinationNotification;

class CommioWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {

        return true;
    }
}
