<?php

namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class OpenAiSignatureValidator implements SignatureValidator
{
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $secret = (string) config('services.openai.webhook_secret');
        $webhookId = (string) $request->header('webhook-id');
        $timestamp = (string) $request->header('webhook-timestamp');
        $signatureHeader = (string) $request->header($config->signatureHeaderName);

        if ($secret === '' || $webhookId === '' || $timestamp === '' || $signatureHeader === '') {
            return false;
        }

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
            return false;
        }

        $signedPayload = "{$webhookId}.{$timestamp}.{$request->getContent()}";
        $expected = base64_encode(hash_hmac('sha256', $signedPayload, $this->webhookSigningKey($secret), true));

        foreach (preg_split('/\s+/', trim($signatureHeader)) ?: [] as $signaturePart) {
            if (! Str::startsWith($signaturePart, 'v1,')) {
                continue;
            }

            if (hash_equals($expected, substr($signaturePart, 3))) {
                return true;
            }
        }

        return false;
    }

    private function webhookSigningKey(string $secret): string
    {
        if (! Str::startsWith($secret, 'whsec_')) {
            return $secret;
        }

        $decoded = base64_decode(substr($secret, 6), true);

        return $decoded === false ? $secret : $decoded;
    }
}
