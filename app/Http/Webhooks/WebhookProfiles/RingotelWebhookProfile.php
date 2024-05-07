<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class RingotelWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        logger("here");
        logger($request);
    
        try {
            switch ($request['method']) {
                case 'typing':
                    return false;
                case 'read':
                    return false;
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
