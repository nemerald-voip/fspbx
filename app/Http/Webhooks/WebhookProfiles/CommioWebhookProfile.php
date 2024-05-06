<?php

namespace App\Http\Webhooks\WebhookProfiles;

use App\Models\Domain;
use App\Models\Messages;
use App\Models\Extensions;
use Illuminate\Http\Request;
use App\Models\SmsDestinations;
use Illuminate\Support\Facades\Log;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use App\Jobs\SendSmsNotificationToSlack;
use libphonenumber\NumberParseException;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class CommioWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {

        logger($request);
        return true;

        try {

        

            return true;
        } catch (\Throwable $e) {
            Log::alert($e->getMessage());
            SendSmsNotificationToSlack::dispatch($slack_message . $e->getMessage())->onQueue('messages');
        }

        return false;
    }

}
