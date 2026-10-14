<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class RingotelWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {    
        try {
            switch ($request['method']) {
                case 'typing':
                    return false;
                case 'read':
                case 'unread':
                    // Capture authenticated reports for verification. The job
                    // deliberately does not mutate read state until semantics are known.
                    return true;
                case 'delivered':
                    return true;
                case 'message':
                    return true;
                default:
                    return false;
            }
        } catch (\Throwable $e) {
            Log::alert($e->getMessage());
        }

        return false;
    }
}
