<?php

namespace App\Services;

use Illuminate\Http\Request;

class TigerTmsWebhookSignatureValidator
{
    public function verify(Request $request): array
    {
        $secretHex = strtolower(preg_replace('/\s+/', '', (string) config('tigertms.webhook_secret')));

        if ($secretHex === '') {
            return [
                'configured' => false,
                'valid' => false,
                'reason' => 'TigerTMS webhook secret is not configured.',
            ];
        }

        if (! ctype_xdigit($secretHex) || strlen($secretHex) % 2 !== 0) {
            return [
                'configured' => true,
                'valid' => false,
                'reason' => 'TigerTMS webhook secret is not valid hex.',
            ];
        }

        $timestamp = trim((string) $request->header('X-iLink-Timestamp', ''));
        $signature = strtolower(trim((string) $request->header('X-iLink-Signature', '')));

        if ($timestamp === '' || $signature === '') {
            return [
                'configured' => true,
                'valid' => false,
                'reason' => 'TigerTMS signature headers are missing.',
            ];
        }

        if (! preg_match('/^sha256=[a-f0-9]{64}$/', $signature)) {
            return [
                'configured' => true,
                'valid' => false,
                'reason' => 'TigerTMS signature header format is invalid.',
            ];
        }

        $tolerance = max(0, (int) config('tigertms.webhook_signature_tolerance_seconds', 300));
        if ($tolerance > 0) {
            if (! ctype_digit($timestamp)) {
                return [
                    'configured' => true,
                    'valid' => false,
                    'reason' => 'TigerTMS timestamp header is invalid.',
                ];
            }

            if (abs(now()->timestamp - (int) $timestamp) > $tolerance) {
                return [
                    'configured' => true,
                    'valid' => false,
                    'reason' => 'TigerTMS timestamp is outside the allowed tolerance.',
                ];
            }
        }

        $key = hex2bin($secretHex);
        $message = $timestamp . '.' . $request->getContent();
        $expected = 'sha256=' . hash_hmac('sha256', $message, $key);

        return [
            'configured' => true,
            'valid' => hash_equals($expected, $signature),
            'reason' => hash_equals($expected, $signature)
                ? null
                : 'TigerTMS signature did not match the expected HMAC.',
        ];
    }
}
