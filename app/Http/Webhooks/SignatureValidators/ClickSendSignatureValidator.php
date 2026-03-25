<?php

namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;

class ClickSendSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        messaging_webhook_debug('ClickSend signature validation bypassed', [
            'reason' => 'no_official_clicksend_signature_scheme_configured',
            'content_type' => $request->header('Content-Type'),
        ]);

        return true;
    }
}