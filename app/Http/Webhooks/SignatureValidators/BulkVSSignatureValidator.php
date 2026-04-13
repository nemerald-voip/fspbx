<?php

namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class BulkVSSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        messaging_webhook_debug('BulkVSSignatureValidator validation bypassed', [
            'reason' => 'no_signature_scheme_was_provided',
            'content_type' => $request->header('Content-Type'),
        ]);

        return true;
    }
}