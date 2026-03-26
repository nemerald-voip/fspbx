<?php

namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class VoipMsSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        messaging_webhook_debug('VoipMsSignatureValidator validation bypassed', [
            'reason' => 'voipms_signature_scheme_not_provided',
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
        ]);

        return true;
    }
}