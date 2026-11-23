<?php

namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

class SinchSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $apiKey = config('sinch.inbound_api_key');
        $messageId = $request->header('messageID');
        $verificationToken = $request->header('verificationToken');
        $computedHash = hash('sha256', $apiKey . $messageId);
        $valid = $computedHash === $verificationToken;

        if (! $valid) {
            $keyPresent = is_string($apiKey) && $apiKey !== '';
            $messageIdPresent = is_string($messageId) && $messageId !== '';
            $tokenPresent = is_string($verificationToken) && $verificationToken !== '';

            // Log only diagnostic metadata, never credentials, header values, or SMS content.
            Log::warning('Sinch webhook authentication failed', [
                'reason' => ! $keyPresent ? 'missing_signing_key'
                    : ((! $messageIdPresent || ! $tokenPresent) ? 'missing_headers' : 'signature_mismatch'),
                'signing_key_present' => $keyPresent,
                'message_id_present' => $messageIdPresent,
                'verification_token_present' => $tokenPresent,
                'verification_token_length' => strlen($verificationToken ?? ''),
                'verification_token_is_sha256_hex' => preg_match('/\A[0-9a-f]{64}\z/i', $verificationToken ?? '') === 1,
                'voxutel_signature_present' => $request->hasHeader('X-Voxutel-Signature'),
                'voxutel_timestamp_present' => $request->hasHeader('X-Voxutel-Timestamp'),
            ]);
        }

        return $valid;
    }
}
