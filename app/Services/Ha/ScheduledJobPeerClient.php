<?php

namespace App\Services\Ha;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ScheduledJobPeerClient
{
    public function __construct(private readonly ScheduledJobPeerAuthenticator $authenticator) {}

    public function identify(string $endpoint): array
    {
        $endpoint = rtrim($endpoint, '/');
        $challenge = bin2hex(random_bytes(16));
        $response = $this->post($endpoint, 'api/ha/node/identify', [
            'challenge' => $challenge,
            'endpoint_identity' => $endpoint,
        ]);

        if (! hash_equals($challenge, (string) ($response['challenge'] ?? ''))
            || ! hash_equals($endpoint, rtrim((string) ($response['endpoint_identity'] ?? ''), '/'))) {
            throw new RuntimeException('The scheduled-job peer returned the wrong identity challenge.');
        }

        return $response;
    }

    public function prepareHandoff(string $endpoint, array $payload, string $idempotencyKey): array
    {
        return $this->post($endpoint, 'api/ha/scheduled-jobs/handoffs', $payload, $idempotencyKey);
    }

    public function handoffStatus(string $endpoint, string $handoffUuid): array
    {
        return $this->get($endpoint, 'api/ha/scheduled-jobs/handoffs/'.$handoffUuid);
    }

    private function post(string $endpoint, string $path, array $payload, ?string $idempotencyKey = null): array
    {
        $idempotencyKey ??= (string) Str::uuid();
        $headers = $this->authenticator->requestHeaders('POST', $path, $payload, $idempotencyKey);
        $requestNonce = $headers[ScheduledJobPeerAuthenticator::NONCE_HEADER];

        try {
            $response = Http::asJson()
                ->withHeaders($headers)
                ->withOptions(['verify' => false, 'allow_redirects' => false])
                ->connectTimeout(2)
                ->timeout(4)
                ->post($this->url($endpoint, $path), $payload);
        } catch (Throwable $exception) {
            throw new RuntimeException('The scheduled-job peer could not be reached: '.$exception->getMessage(), 0, $exception);
        }

        return $this->verifiedResponse($response->status(), $response->json() ?: [], $response->headers(), $path, $requestNonce, $idempotencyKey);
    }

    private function get(string $endpoint, string $path): array
    {
        $idempotencyKey = (string) Str::uuid();
        $headers = $this->authenticator->requestHeaders('GET', $path, [], $idempotencyKey);
        $requestNonce = $headers[ScheduledJobPeerAuthenticator::NONCE_HEADER];

        try {
            $response = Http::withHeaders($headers)
                ->withOptions(['verify' => false, 'allow_redirects' => false])
                ->connectTimeout(2)
                ->timeout(4)
                ->get($this->url($endpoint, $path));
        } catch (Throwable $exception) {
            throw new RuntimeException('The scheduled-job peer could not be reached: '.$exception->getMessage(), 0, $exception);
        }

        return $this->verifiedResponse($response->status(), $response->json() ?: [], $response->headers(), $path, $requestNonce, $idempotencyKey);
    }

    private function verifiedResponse(
        int $status,
        array $payload,
        array $headers,
        string $path,
        string $requestNonce,
        string $idempotencyKey
    ): array {
        if (! $this->authenticator->verifyResponse($path, $payload, $requestNonce, $idempotencyKey, $headers, $status)) {
            throw new RuntimeException('The scheduled-job peer returned an invalid signature.');
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException((string) ($payload['message'] ?? 'The scheduled-job peer rejected the request.'), $status);
        }

        return $payload;
    }

    private function url(string $endpoint, string $path): string
    {
        $endpoint = trim($endpoint);
        if (! str_starts_with(strtolower($endpoint), 'https://')) {
            throw new RuntimeException('Scheduled-job peer endpoints must use HTTPS.');
        }

        return rtrim($endpoint, '/').'/'.ltrim($path, '/');
    }
}
