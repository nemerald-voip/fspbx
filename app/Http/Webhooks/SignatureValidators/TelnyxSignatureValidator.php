<?php

namespace App\Http\Webhooks\SignatureValidators;

use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;
use Throwable;

class TelnyxSignatureValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $signature = (string) $request->header('telnyx-signature-ed25519', '');
        $timestamp = (string) $request->header('telnyx-timestamp', '');
        $publicKey = (string) ($config->signingSecret ?? '');
        $tolerance = (int) env('TELNYX_WEBHOOK_TOLERANCE', 300);

        if ($signature === '' || $timestamp === '' || $publicKey === '') {
            messaging_webhook_debug('TelnyxSignatureValidator missing required signature data', [
                'has_signature' => $signature !== '',
                'has_timestamp' => $timestamp !== '',
                'has_public_key' => $publicKey !== '',
            ]);

            return false;
        }

        if (! ctype_digit($timestamp)) {
            messaging_webhook_debug('TelnyxSignatureValidator invalid timestamp format', [
                'timestamp' => $timestamp,
            ]);

            return false;
        }

        if (abs(time() - (int) $timestamp) > $tolerance) {
            messaging_webhook_debug('TelnyxSignatureValidator timestamp outside tolerance', [
                'timestamp' => $timestamp,
                'tolerance' => $tolerance,
            ]);

            return false;
        }

        try {
            $signatureBytes = base64_decode($signature, true);
            $publicKeyBytes = $this->decodePublicKey($publicKey);

            if ($signatureBytes === false || $publicKeyBytes === null) {
                messaging_webhook_debug('TelnyxSignatureValidator failed to decode signature/public key');

                return false;
            }

            $signedPayload = $timestamp . '|' . $request->getContent();

            $isValid = sodium_crypto_sign_verify_detached(
                $signatureBytes,
                $signedPayload,
                $publicKeyBytes
            );

            messaging_webhook_debug('TelnyxSignatureValidator verification completed', [
                'is_valid' => $isValid,
            ]);

            return $isValid;
        } catch (Throwable $e) {
            messaging_webhook_debug('TelnyxSignatureValidator exception', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function decodePublicKey(string $publicKey): ?string
    {
        $trimmed = trim($publicKey);

        $base64 = base64_decode($trimmed, true);
        if ($base64 !== false && strlen($base64) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return $base64;
        }

        if (ctype_xdigit($trimmed) && strlen($trimmed) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES * 2) {
            $hex = hex2bin($trimmed);

            return $hex === false ? null : $hex;
        }

        return null;
    }
}