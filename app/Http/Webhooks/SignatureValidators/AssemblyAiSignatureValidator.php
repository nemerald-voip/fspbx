<?php
namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;

class AssemblyAiSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $headerName = $config->signatureHeaderName;

        // Secret value from services config (or wherever you keep it)
        $expected = (string) config('services.assemblyai.webhook_header_value');

        if (empty($headerName) || $expected === '') {
            return false;
        }

        $incoming = $request->header($headerName);

        return is_string($incoming) && hash_equals($expected, $incoming);
    }
}
