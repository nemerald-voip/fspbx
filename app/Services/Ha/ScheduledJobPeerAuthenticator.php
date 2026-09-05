<?php

namespace App\Services\Ha;

use App\Models\DefaultSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class ScheduledJobPeerAuthenticator
{
    public const TIMESTAMP_HEADER = 'X-FsPbx-Coord-Timestamp';
    public const NONCE_HEADER = 'X-FsPbx-Coord-Nonce';
    public const IDEMPOTENCY_HEADER = 'X-FsPbx-Coord-Idempotency';
    public const SIGNATURE_HEADER = 'X-FsPbx-Coord-Signature';

    private const MAX_CLOCK_SKEW_SECONDS = 90;
    private const NONCE_TTL_SECONDS = 300;

    public function secretConfigured(): bool
    {
        return strlen($this->secret()) >= 32;
    }

    /** @return array<string, string> */
    public function requestHeaders(string $method, string $path, array $payload, string $idempotencyKey): array
    {
        $timestamp = (string) now()->timestamp;
        $nonce = bin2hex(random_bytes(16));

        return [
            self::TIMESTAMP_HEADER => $timestamp,
            self::NONCE_HEADER => $nonce,
            self::IDEMPOTENCY_HEADER => $idempotencyKey,
            self::SIGNATURE_HEADER => $this->signature($method, $path, $timestamp, $nonce, $idempotencyKey, $payload),
        ];
    }

    public function verifyRequest(Request $request): bool
    {
        $timestamp = (string) $request->header(self::TIMESTAMP_HEADER, '');
        $nonce = (string) $request->header(self::NONCE_HEADER, '');
        $idempotency = (string) $request->header(self::IDEMPOTENCY_HEADER, '');
        $presented = (string) $request->header(self::SIGNATURE_HEADER, '');

        if (! $this->validEnvelope($timestamp, $nonce, $idempotency) || $presented === '') {
            return false;
        }

        $expected = $this->signature($request->method(), $request->path(), $timestamp, $nonce, $idempotency, $request->all());
        if (! hash_equals($expected, $presented)) {
            return false;
        }

        return Cache::add('scheduled-job-peer-nonce:'.$nonce, true, self::NONCE_TTL_SECONDS);
    }

    /** @return array<string, string> */
    public function responseHeaders(string $path, array $payload, string $requestNonce, string $idempotencyKey, int $status = 200): array
    {
        $timestamp = (string) now()->timestamp;

        return [
            self::TIMESTAMP_HEADER => $timestamp,
            self::NONCE_HEADER => $requestNonce,
            self::IDEMPOTENCY_HEADER => $idempotencyKey,
            self::SIGNATURE_HEADER => $this->signature('RESPONSE:'.$status, $path, $timestamp, $requestNonce, $idempotencyKey, $payload),
        ];
    }

    public function verifyResponse(
        string $path,
        array $payload,
        string $requestNonce,
        string $idempotencyKey,
        array $headers,
        int $status = 200
    ): bool {
        $timestamp = $this->headerValue($headers, self::TIMESTAMP_HEADER);
        $nonce = $this->headerValue($headers, self::NONCE_HEADER);
        $responseIdempotency = $this->headerValue($headers, self::IDEMPOTENCY_HEADER);
        $presented = $this->headerValue($headers, self::SIGNATURE_HEADER);

        if ($nonce !== $requestNonce || $responseIdempotency !== $idempotencyKey
            || ! $this->validTimestamp($timestamp) || $presented === '') {
            return false;
        }

        $expected = $this->signature('RESPONSE:'.$status, $path, $timestamp, $nonce, $idempotencyKey, $payload);

        return hash_equals($expected, $presented);
    }

    private function headerValue(array $headers, string $name): string
    {
        foreach ($headers as $header => $values) {
            if (strcasecmp((string) $header, $name) === 0) {
                return (string) (is_array($values) ? ($values[0] ?? '') : $values);
            }
        }

        return '';
    }

    private function validEnvelope(string $timestamp, string $nonce, string $idempotency): bool
    {
        return $this->validTimestamp($timestamp)
            && (bool) preg_match('/^[a-f0-9]{32,64}$/', $nonce)
            && (bool) preg_match('/^[0-9a-f-]{36}$/i', $idempotency)
            && $this->secretConfigured();
    }

    private function validTimestamp(string $timestamp): bool
    {
        return ctype_digit($timestamp)
            && abs(now()->timestamp - (int) $timestamp) <= self::MAX_CLOCK_SKEW_SECONDS;
    }

    private function signature(
        string $method,
        string $path,
        string $timestamp,
        string $nonce,
        string $idempotency,
        array $payload
    ): string {
        $secret = $this->secret();
        if (strlen($secret) < 32) {
            throw new RuntimeException('Scheduled-job peer authentication is not configured.');
        }

        $canonical = implode("\n", [
            strtoupper($method),
            trim($path, '/'),
            $timestamp,
            $nonce,
            strtolower($idempotency),
            hash('sha256', $this->canonicalJson($payload)),
        ]);

        return hash_hmac('sha256', $canonical, $secret);
    }

    private function secret(): string
    {
        $rows = DefaultSettings::query()
            ->where('default_setting_category', 'scheduled_jobs')
            ->where('default_setting_subcategory', 'coordination_secret')
            ->get();

        return $rows->count() === 1 && filter_var($rows->first()->default_setting_enabled, FILTER_VALIDATE_BOOLEAN)
            ? trim((string) $rows->first()->default_setting_value) : '';
    }

    private function canonicalJson(array $payload): string
    {
        $normalize = function (mixed $value) use (&$normalize): mixed {
            if (! is_array($value)) {
                return $value;
            }

            if (array_is_list($value)) {
                return array_map($normalize, $value);
            }

            ksort($value);

            return array_map($normalize, $value);
        };

        $json = json_encode($normalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new RuntimeException('Unable to sign the scheduled-job peer payload.');
        }

        return $json;
    }
}
