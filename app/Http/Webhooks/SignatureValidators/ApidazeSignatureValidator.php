<?php

namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;

class ApidazeSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $data = $request->all();
        // logger($data);

        if (($request->header('content-type') ?? '') !== 'application/json') {
            return false;
        }

        if (($data['type'] ?? null) !== 'incomingWebhookSMS' && ($data['type'] ?? null) !== 'incomingWebhookMMS') {
            return false;
        }

        if (empty($data['destination_number']) || empty($data['caller_id_number'])) {
            return false;
        }

        if (!array_key_exists('text', $data)) {
            return false;
        }

        return true;
    }
}
