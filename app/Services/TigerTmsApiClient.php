<?php

namespace App\Services;

use App\Models\TigerTmsApiLog;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;
use RuntimeException;

class TigerTmsApiClient
{
    public function __construct(private ?TigerTmsSiteMapper $siteMapper = null)
    {
        $this->siteMapper ??= app(TigerTmsSiteMapper::class);
    }

    public function authenticate(): array
    {
        $username = (string) config('tigertms.username');
        $password = (string) config('tigertms.password');

        if ($username === '' || $password === '') {
            throw new RuntimeException('TigerTMS credentials are not configured.');
        }

        $response = Http::timeout($this->timeout())
            ->acceptJson()
            ->asJson()
            ->post($this->url('/api/authenticate/v1/user'), [
                'username' => $username,
                'password' => $password,
            ]);

        if (! $response->successful()) {
            $information = (string) data_get($response->json(), 'information', $response->body());

            throw new RuntimeException(sprintf(
                'TigerTMS authentication failed (%s): %s',
                $response->status(),
                $information !== '' ? $information : 'No response details returned.'
            ));
        }

        return $response->json();
    }

    public function checkIn(string $domainUuid, string $room, array $reservation): Response
    {
        return $this->request('post', '/api/pms/v1/site/{site}/room/{room}/checkin', $domainUuid, $room, $reservation);
    }

    public function checkOut(string $domainUuid, string $room): Response
    {
        return $this->request('delete', '/api/pms/v1/site/{site}/room/{room}/checkout', $domainUuid, $room);
    }

    public function updateRoom(string $domainUuid, string $room, array $reservation): Response
    {
        return $this->request('put', '/api/pms/v1/site/{site}/room/{room}/updateroom', $domainUuid, $room, $reservation);
    }

    public function transferRoom(string $domainUuid, string $room, string $newRoom): Response
    {
        return $this->request('put', '/api/pms/v1/site/{site}/room/{room}/newroom/{newroom}/transfer', $domainUuid, $room, null, [
            'newroom' => $newRoom,
        ]);
    }

    public function dndOn(string $domainUuid, string $room): Response
    {
        return $this->request('put', '/api/pms/v1.1/site/{site}/room/{room}/dndon', $domainUuid, $room);
    }

    public function dndOff(string $domainUuid, string $room): Response
    {
        return $this->request('put', '/api/pms/v1/site/{site}/room/{room}/dndoff', $domainUuid, $room);
    }

    public function setWakeup(string $domainUuid, string $room, string $dateTime): Response
    {
        return $this->request('post', '/api/wakeup/v1/site/{site}/room/{room}/setwakeup', $domainUuid, $room, [
            'datetime' => $dateTime,
        ]);
    }

    public function deleteWakeups(string $domainUuid, string $room): Response
    {
        return $this->request('delete', '/api/wakeup/v1/site/{site}/room/{room}/deletewakeups', $domainUuid, $room);
    }

    public function request(string $method, string $path, string $domainUuid, string $room, ?array $payload = null, array $extraPathParams = []): Response
    {
        $params = [
            'site' => $this->siteMapper->outbound($domainUuid),
            'room' => $room,
            ...$extraPathParams,
        ];

        $url = $this->url($this->replacePathParams($path, $params));
        $startedAt = microtime(true);
        $log = $this->startLog($domainUuid, $method, $path, $url, $payload, $params);

        try {
            $pending = Http::timeout($this->timeout())
                ->acceptJson()
                ->asJson()
                ->withToken($this->token());

            $response = $payload === null
                ? $pending->{$method}($url)
                : $pending->{$method}($url, $payload);

            $this->finishLog($log, $startedAt, $response);

            return $response;
        } catch (Throwable $e) {
            $this->failLog($log, $startedAt, $e);

            throw $e;
        }
    }

    private function startLog(string $domainUuid, string $method, string $endpoint, string $url, ?array $payload, array $context): ?TigerTmsApiLog
    {
        try {
            return TigerTmsApiLog::create([
                'domain_uuid' => $domainUuid,
                'method' => strtoupper($method),
                'endpoint' => $endpoint,
                'url' => $url,
                'request_context' => $context,
                'request_payload' => $payload,
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    private function finishLog(?TigerTmsApiLog $log, float $startedAt, Response $response): void
    {
        if (! $log) {
            return;
        }

        try {
            $log->update([
                'response_status' => $response->status(),
                'response_body' => $this->responseBody($response),
                'error' => $response->successful() ? null : $response->body(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);
        } catch (Throwable) {
            // Logging must not affect PMS API behavior.
        }
    }

    private function failLog(?TigerTmsApiLog $log, float $startedAt, Throwable $e): void
    {
        if (! $log) {
            return;
        }

        try {
            $log->update([
                'error' => $e->getMessage(),
                'duration_ms' => $this->durationMs($startedAt),
            ]);
        } catch (Throwable) {
            // Logging must not affect PMS API behavior.
        }
    }

    private function responseBody(Response $response): mixed
    {
        $json = $response->json();

        if ($json !== null) {
            return $json;
        }

        $body = $response->body();

        return $body === '' ? null : ['raw' => mb_substr($body, 0, 10000)];
    }

    private function durationMs(float $startedAt): int
    {
        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
    }

    private function token(): string
    {
        if ($token = Cache::get($this->cacheKey())) {
            return (string) $token;
        }

        $auth = $this->authenticate();
        $token = $this->firstString($auth, ['token', 'Token', 'access_token', 'accessToken']);

        if ($token === '') {
            $information = $this->firstString($auth, ['information', 'Information']);

            throw new RuntimeException('TigerTMS authentication did not return a token.'
                . ($information !== '' ? ' ' . $information : ''));
        }

        $ttl = 300;
        if ($expires = $this->firstString($auth, ['expires', 'Expires', 'expires_at', 'expiresAt'])) {
            $ttl = max(60, Carbon::parse($expires)->timestamp - now()->timestamp - 60);
        }

        Cache::put($this->cacheKey(), $token, $ttl);

        return $token;
    }

    private function cacheKey(): string
    {
        return 'tigertms:jwt:' . sha1((string) config('tigertms.base_url') . '|' . (string) config('tigertms.username'));
    }

    private function firstString(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    private function replacePathParams(string $path, array $params): string
    {
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', rawurlencode((string) $value), $path);
        }

        return $path;
    }

    private function url(string $path): string
    {
        return rtrim((string) config('tigertms.base_url'), '/') . '/' . ltrim($path, '/');
    }

    private function timeout(): int
    {
        return max(1, (int) config('tigertms.timeout', 20));
    }
}
