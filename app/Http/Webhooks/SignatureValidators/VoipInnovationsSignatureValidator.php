<?php

namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class VoipInnovationsSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $expected = config('voipinnovations.inbound_webhook_key');
        $provided = $request->route('key');

        $validKey = is_string($expected)
            && strlen($expected) >= 32
            && is_string($provided)
            && hash_equals($expected, $provided);

        abort_unless($validKey, 403);
        abort_unless($request->isJson(), 415);
        abort_if(strlen($request->getContent()) > 8 * 1024 * 1024, 413);

        return true;
    }
}
