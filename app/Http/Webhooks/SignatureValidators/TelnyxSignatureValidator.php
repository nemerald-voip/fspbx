<?php

namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;

class TelnyxSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        // 1. Get the signature and timestamp from headers
        $signature = $request->header('telnyx-signature-ed25519');
        $timestamp = $request->header('telnyx-timestamp');

        // 2. Get your Public Key (mapped to 'signing_secret' in config)
        $publicKey = $config->signingSecret;

        if (! $signature || ! $timestamp || ! $publicKey) {
            return false;
        }

        // 3. Construct the payload string exactly as Telnyx expects: timestamp|json_body
        $signedPayload = $timestamp . '|' . $request->getContent();

        // 4. Decode the Base64 encoded keys/signatures
        // The header signature and your public key are both Base64 encoded
        $decodedSignature = base64_decode($signature);
        $decodedPublicKey = base64_decode($publicKey);

        // 5. Verify using Sodium
        try {
            // sodium_crypto_sign_verify_detached returns true if valid, false otherwise
            $isValid = sodium_crypto_sign_verify_detached(
                $decodedSignature,
                $signedPayload,
                $decodedPublicKey
            );

            if (!$isValid) {
                return false;
            }

            // 6. (Optional) Verify timestamp freshness to prevent replay attacks
            // Reject requests older than 5 minutes (300 seconds)
            $tolerance = 300;
            if (abs(time() - $timestamp) > $tolerance) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            // If keys are malformed, sodium might throw an exception
            return false;
        }
    }
}
