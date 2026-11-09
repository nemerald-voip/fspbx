<?php

namespace App\Http\Webhooks\SignatureValidators;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class VoxutelSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $sharedKey = (string) config('voxutel.inbound_api_key');
        $timestamp = (string) $request->header('X-Voxutel-Timestamp');
        $providedSignature = (string) $request->header('X-Voxutel-Signature');

        if ($sharedKey === '') {
            return $this->reject('missing_signing_key');
        }

        if ($timestamp === '' || $providedSignature === '') {
            return $this->reject('missing_headers', [
                'timestamp_present' => $timestamp !== '',
                'signature_present' => $providedSignature !== '',
            ]);
        }

        try {
            $timestampValue = CarbonImmutable::parse($timestamp);
        } catch (\Throwable) {
            return $this->reject('invalid_timestamp', [
                'timestamp_is_numeric' => ctype_digit($timestamp),
                'timestamp_length' => strlen($timestamp),
            ]);
        }

        $age = abs(now()->getTimestamp() - $timestampValue->getTimestamp());
        if ($age > (int) config('voxutel.inbound_signature_tolerance_seconds', 300)) {
            return $this->reject('timestamp_outside_tolerance', ['clock_difference_seconds' => $age]);
        }

        $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $sharedKey);
        $providedSignature = preg_replace('/^sha256=/i', '', $providedSignature);

        if (! is_string($providedSignature) || ! hash_equals($expectedSignature, $providedSignature)) {
            return $this->reject('signature_mismatch');
        }

        return true;
    }

    private function reject(string $reason, array $context = []): bool
    {
        // Never log the signing key, signature, raw headers, or message body.
        Log::warning('Voxutel webhook authentication failed', ['reason' => $reason] + $context);

        return false;
    }
}
