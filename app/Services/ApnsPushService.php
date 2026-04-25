<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ApnsPushService
{
    private string $keyId;
    private string $teamId;
    private string $bundleId;
    private string $keyPath;
    private bool $production;

    public function __construct()
    {
        $this->keyId = (string) config('apns.key_id', '');
        $this->teamId = (string) config('apns.team_id', '');
        $this->bundleId = (string) config('apns.bundle_id', 'com.example.MobileApp');
        $this->keyPath = config('apns.key_path', storage_path('app/apns/AuthKey.p8'));
        $this->production = (bool) config('apns.production', false);
    }

    /**
     * Send a VoIP push notification for an incoming call.
     *
     * `didPrefix` is the per-DID caller-id prefix configured in FreeSWITCH
     * (e.g. "SUPPORT", "SALES"); `didE164` is the called DID. `enrichment`
     * is an optional associative array of CRM-lookup fields (person_id,
     * display_name, company_name, is_vip, last_interaction_at,
     * note_preview) merged into the payload when present. All are optional
     * — clients that don't read them degrade gracefully.
     */
    public function sendIncomingCallPush(
        string $deviceToken,
        string $callerIdName,
        string $callerIdNumber,
        string $callUuid,
        string $didPrefix = '',
        string $didE164 = '',
        ?array $enrichment = null,
    ): bool {
        // VoIP pushes carry an empty `aps` (CallKit reads the custom fields).
        $payload = [
            'aps' => (object) [],
            'caller_id_name' => $callerIdName,
            'caller_id_number' => $callerIdNumber,
            'call_uuid' => $callUuid,
        ];
        if ($didPrefix !== '') {
            $payload['did_prefix'] = $didPrefix;
        }
        if ($didE164 !== '') {
            $payload['did_e164'] = $didE164;
        }

        // Best-effort caller-ID enrichment. The shape is intentionally open
        // so callers (event listeners, decorators, custom subclasses) can
        // attach whatever fields their iOS app's parser understands. APNs
        // VoIP payload cap is 5KB — keep enrichment compact.
        if (is_array($enrichment)) {
            foreach ($enrichment as $k => $v) {
                if ($v !== null && $v !== '' && !isset($payload[$k])) {
                    $payload[$k] = $v;
                }
            }
        }

        return $this->send($deviceToken, $payload);
    }

    /**
     * Send a VoIP push notification via APNs HTTP/2 API.
     */
    private function send(string $deviceToken, array $payload): bool
    {
        $jwt = $this->generateJwt();
        if (!$jwt) {
            Log::error('[APNs] Failed to generate JWT');
            return false;
        }

        $host = $this->production
            ? 'https://api.push.apple.com'
            : 'https://api.sandbox.push.apple.com';

        $url = "{$host}/3/device/{$deviceToken}";
        $body = json_encode($payload);

        $headers = [
            "authorization: bearer {$jwt}",
            "apns-topic: {$this->bundleId}.voip",
            "apns-push-type: voip",
            "apns-priority: 10",
            "apns-expiration: 0",
            "content-type: application/json",
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error("[APNs] cURL error: {$error}");
            return false;
        }

        if ($httpCode !== 200) {
            Log::warning("[APNs] Push failed with HTTP {$httpCode}", [
                'device_token' => substr($deviceToken, 0, 8) . '...',
                'response' => $response,
            ]);
            return false;
        }

        Log::info("[APNs] Push sent successfully", [
            'device_token' => substr($deviceToken, 0, 8) . '...',
            'call_uuid' => $payload['call_uuid'] ?? 'unknown',
        ]);

        return true;
    }

    /**
     * Generate a JWT for APNs authentication.
     */
    private function generateJwt(): ?string
    {
        if (!file_exists($this->keyPath)) {
            Log::error("[APNs] Key file not found: {$this->keyPath}");
            return null;
        }

        $key = file_get_contents($this->keyPath);
        $header = base64url_encode(json_encode([
            'alg' => 'ES256',
            'kid' => $this->keyId,
        ]));
        $claims = base64url_encode(json_encode([
            'iss' => $this->teamId,
            'iat' => time(),
        ]));

        $signingInput = "{$header}.{$claims}";
        $signature = '';
        $success = openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256);

        if (!$success) {
            Log::error('[APNs] Failed to sign JWT');
            return null;
        }

        // Convert DER signature to raw r||s format for ES256
        $signature = $this->derToRaw($signature);

        return "{$header}.{$claims}." . base64url_encode($signature);
    }

    /**
     * Convert a DER-encoded ECDSA signature to raw r||s format.
     */
    private function derToRaw(string $der): string
    {
        $hex = unpack('H*', $der)[1];
        // Skip sequence tag and length
        $pos = 4;
        if (hexdec(substr($hex, 2, 2)) > 128) {
            $pos = 6;
        }

        // Read r
        $rLen = hexdec(substr($hex, $pos + 2, 2)) * 2;
        $r = substr($hex, $pos + 4, $rLen);
        // Remove leading zero padding
        if (strlen($r) > 64) {
            $r = substr($r, -64);
        }
        $r = str_pad($r, 64, '0', STR_PAD_LEFT);

        // Read s
        $sPos = $pos + 4 + $rLen;
        $sLen = hexdec(substr($hex, $sPos + 2, 2)) * 2;
        $s = substr($hex, $sPos + 4, $sLen);
        if (strlen($s) > 64) {
            $s = substr($s, -64);
        }
        $s = str_pad($s, 64, '0', STR_PAD_LEFT);

        return pack('H*', $r . $s);
    }
}

/**
 * URL-safe base64 encoding.
 */
if (!function_exists('base64url_encode')) {
    function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
