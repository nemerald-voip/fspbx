<?php

namespace App\Services;

use App\DTO\OrganizationDTOInterface;
use App\DTO\YealinkRpsServerDTO;
use App\Models\DefaultSettings;
use App\Models\DomainSettings;
use App\Services\Interfaces\CloudProviderInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class YealinkRpsCloudProvider implements CloudProviderInterface
{
    protected string $providerName = 'yealink';
    protected int $timeout = 60;
    protected ?string $accessToken = null;

    public function getCredentials(): array
    {
        $settings = DefaultSettings::query()
            ->where('default_setting_category', 'cloud_provision')
            ->where('default_setting_enabled', 'true')
            ->whereIn('default_setting_subcategory', [
                'yealink_rps_access_key_id',
                'yealink_rps_access_key_secret',
                'yealink_rps_api_url',
            ])
            ->pluck('default_setting_value', 'default_setting_subcategory');

        return [
            'access_key_id' => $settings->get('yealink_rps_access_key_id'),
            'access_key_secret' => $settings->get('yealink_rps_access_key_secret'),
            'api_url' => $settings->get('yealink_rps_api_url')
                ?: config('services.ztp.yealink.api_url', 'https://us-api.ymcs.yealink.com'),
        ];
    }

    public function setCredentials(array $credentials): void
    {
        $this->storeCredential('yealink_rps_access_key_id', $credentials['access_key_id']);
        $this->storeCredential('yealink_rps_access_key_secret', $credentials['access_key_secret']);
        $this->storeCredential(
            'yealink_rps_api_url',
            $credentials['api_url'] ?? config('services.ztp.yealink.api_url', 'https://us-api.ymcs.yealink.com')
        );

        $this->accessToken = null;
    }

    public function hasCredentials(): bool
    {
        $credentials = $this->getCredentials();

        return filled($credentials['access_key_id']) && filled($credentials['access_key_secret']);
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function getDevices(int $limit = 50, ?string $cursor = null): array
    {
        $skip = max(0, (int) ($cursor ?? 0));
        $page = $this->requestJson('POST', '/v2/rps/listDevices', [
            'skip' => $skip,
            'limit' => $limit,
            'autoCount' => true,
            'filter' => (object) [],
        ]);

        $devices = is_array($page['data'] ?? null) ? $page['data'] : [];
        $nextOffset = $skip + count($devices);
        $total = (int) ($page['total'] ?? $nextOffset);

        return [
            'success' => true,
            'data' => [
                'results' => $devices,
                'next' => count($devices) > 0 && $nextOffset < $total ? (string) $nextOffset : null,
            ],
            'status' => 200,
        ];
    }

    public function getDevice(string $id): array
    {
        return [
            'success' => true,
            'data' => $this->requestJson('GET', '/v2/rps/devices/' . rawurlencode($id)),
            'status' => 200,
        ];
    }

    public function createDevice(array $params): array
    {
        $serverId = static::getOrgIdByDomainUuid($params['domain_uuid']);

        if (blank($serverId)) {
            throw new RuntimeException('Yealink RPS server is not configured for this account.');
        }

        $result = $this->requestJson('POST', '/v2/rps/addDevicesByMac', [[
            'mac' => $this->normalizeMac($params['device_address']),
            'serverId' => $serverId,
        ]]);

        return $this->batchResult($result, 'Unable to add the device to Yealink RPS.');
    }

    public function deleteDevice(array $params): array
    {
        $result = $this->requestJson('POST', '/v2/rps/delDevices', [
            'deviceIdType' => 'mac',
            'deviceIds' => [$this->normalizeMac($params['device_address'])],
        ]);

        return $this->batchResult($result, 'Unable to remove the device from Yealink RPS.');
    }

    public function getOrganizations(): Collection
    {
        $servers = collect();
        $skip = 0;
        $limit = 100;

        do {
            $page = $this->requestJson('POST', '/v2/rps/listServers', [
                'skip' => $skip,
                'limit' => $limit,
                'autoCount' => true,
                'filter' => (object) [],
            ]);
            $items = is_array($page['data'] ?? null) ? $page['data'] : [];
            $servers->push(...array_map(
                fn (array $item) => YealinkRpsServerDTO::fromArray($item),
                $items
            ));

            $skip += count($items);
            $total = (int) ($page['total'] ?? $skip);
        } while ($skip < $total && count($items) > 0);

        return $servers;
    }

    public function createOrganization(array $params)
    {
        $server = $this->requestJson('POST', '/v2/rps/servers', $this->serverPayload($params));
        $serverId = $server['id'] ?? null;

        if (blank($serverId)) {
            throw new RuntimeException('Yealink RPS did not return a server ID.');
        }

        $this->pairOrganization(session('domain_uuid'), $serverId);

        return $server;
    }

    public function getOrganization(string $id): OrganizationDTOInterface
    {
        $server = $this->requestJson('GET', '/v2/rps/servers/' . rawurlencode($id));

        if (blank($server['id'] ?? null)) {
            throw new RuntimeException('Yealink RPS returned an invalid server response.');
        }

        return YealinkRpsServerDTO::fromArray($server);
    }

    public function updateOrganization(array $params)
    {
        $this->requestJson(
            'PATCH',
            '/v2/rps/servers/' . rawurlencode($params['organization_id']),
            $this->serverPayload($params)
        );

        return true;
    }

    public function deleteOrganization(string $id)
    {
        $deviceIds = collect();
        $cursor = null;

        do {
            $page = $this->getDevices(100, $cursor);
            $devices = $page['data']['results'] ?? [];

            foreach ($devices as $device) {
                if (($device['serverId'] ?? null) === $id && filled($device['id'] ?? null)) {
                    $deviceIds->push($device['id']);
                }
            }

            $cursor = $page['data']['next'] ?? null;
        } while ($cursor !== null);

        foreach ($deviceIds->chunk(100) as $ids) {
            $result = $this->requestJson('POST', '/v2/rps/delDevices', [
                'deviceIdType' => 'id',
                'deviceIds' => $ids->values()->all(),
            ]);
            $this->throwIfBatchFailed($result, 'Unable to remove devices assigned to the Yealink RPS server.');
        }

        $result = $this->requestJson('POST', '/v2/rps/delServers', ['serverIds' => [$id]]);
        $this->throwIfBatchFailed($result, 'Unable to delete the Yealink RPS server.');

        DomainSettings::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('domain_setting_category', 'cloud provision')
            ->where('domain_setting_subcategory', 'yealink_rps_server_id')
            ->delete();

        return true;
    }

    public function organizationDeletionRemovesDevices(): bool
    {
        return true;
    }

    public function pairOrganization(string $domainUuid, string $organizationId): void
    {
        DomainSettings::updateOrCreate(
            [
                'domain_uuid' => $domainUuid,
                'domain_setting_category' => 'cloud provision',
                'domain_setting_subcategory' => 'yealink_rps_server_id',
            ],
            [
                'domain_setting_name' => 'text',
                'domain_setting_value' => $organizationId,
                'domain_setting_enabled' => true,
            ]
        );
    }

    public static function getOrgIdByDomainUuid(string $domainUuid): mixed
    {
        return DomainSettings::query()
            ->where('domain_uuid', $domainUuid)
            ->where('domain_setting_category', 'cloud provision')
            ->where('domain_setting_subcategory', 'yealink_rps_server_id')
            ->where('domain_setting_enabled', 'true')
            ->value('domain_setting_value');
    }

    public static function getSettings(): array
    {
        return [
            'yealink_provision_url' => rtrim((string) config('app.url'), '/') . '/prov/',
            'http_auth_username' => get_domain_setting('http_auth_username'),
            'http_auth_password' => get_domain_setting('http_auth_password'),
            'provider' => 'yealink',
        ];
    }

    protected function requestJson(string $method, string $path, ?array $payload = null): array
    {
        $response = $this->sendRequest($method, $path, $payload);

        if (! $response->successful()) {
            $message = $this->responseMessage($response, 'Yealink RPS returned an error.');

            logger()->warning('Yealink RPS API error', [
                'status' => $response->status(),
                'error' => $message,
                'path' => (string) $response->effectiveUri(),
            ]);

            throw new RuntimeException($message);
        }

        if ($response->status() === 204 || blank($response->body())) {
            return [];
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException('Yealink RPS returned an invalid JSON response.');
        }

        return $json;
    }

    protected function sendRequest(string $method, string $path, ?array $payload = null): Response
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $request = Http::baseUrl($this->apiUrl())
                ->timeout($this->timeout)
                ->acceptJson()
                ->asJson()
                ->withToken($this->accessToken())
                ->withHeaders($this->requestHeaders());

            $options = $payload === null ? [] : ['json' => $payload];
            $response = $request->send(strtoupper($method), $path, $options);

            if ($response->status() !== 401 || $attempt === 1) {
                return $response;
            }

            $this->accessToken = null;
        }

        throw new RuntimeException('Yealink RPS request failed.');
    }

    protected function accessToken(): string
    {
        if (filled($this->accessToken)) {
            return $this->accessToken;
        }

        $credentials = $this->ensureCredentialsExist();
        $response = Http::baseUrl($this->apiUrl())
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($credentials['access_key_id'], $credentials['access_key_secret'])
            ->withHeaders($this->requestHeaders())
            ->post('/v2/token', ['grant_type' => 'client_credentials']);

        $token = $response->json('access_token');

        if (! $response->successful() || blank($token)) {
            $message = $this->responseMessage($response, 'Unable to authenticate with the Yealink YMCS API.');

            logger()->warning('Yealink RPS authentication error', [
                'status' => $response->status(),
                'error' => $message,
                'path' => (string) $response->effectiveUri(),
            ]);

            throw new RuntimeException($message);
        }

        return $this->accessToken = $token;
    }

    private function requestHeaders(): array
    {
        return [
            'timestamp' => (string) (int) floor(microtime(true) * 1000),
            'nonce' => bin2hex(random_bytes(16)),
        ];
    }

    private function apiUrl(): string
    {
        return rtrim((string) $this->getCredentials()['api_url'], '/');
    }

    private function ensureCredentialsExist(): array
    {
        $credentials = $this->getCredentials();

        if (blank($credentials['access_key_id']) || blank($credentials['access_key_secret'])) {
            throw new RuntimeException('Yealink RPS API credentials are missing.');
        }

        return $credentials;
    }

    private function storeCredential(string $subcategory, string $value): void
    {
        DefaultSettings::updateOrCreate(
            [
                'default_setting_category' => 'cloud_provision',
                'default_setting_subcategory' => $subcategory,
            ],
            [
                'default_setting_name' => 'text',
                'default_setting_value' => $value,
                'default_setting_enabled' => 'true',
            ]
        );
    }

    private function serverPayload(array $params): array
    {
        return [
            'serverName' => $params['name'],
            'url' => $params['address'],
            'authName' => $params['username'],
            'password' => $params['password'],
        ];
    }

    private function batchResult(array $result, string $fallback): array
    {
        if ((int) ($result['failureCount'] ?? 0) > 0) {
            return [
                'success' => false,
                'error' => $this->batchErrorMessage($result, $fallback),
                'status' => 200,
            ];
        }

        return [
            'success' => true,
            'data' => $result,
            'status' => 200,
        ];
    }

    private function throwIfBatchFailed(array $result, string $fallback): void
    {
        if ((int) ($result['failureCount'] ?? 0) > 0) {
            throw new RuntimeException($this->batchErrorMessage($result, $fallback));
        }
    }

    private function batchErrorMessage(array $result, string $fallback): string
    {
        $messages = collect($result['errors'] ?? [])
            ->map(fn (array $error) => $error['errorInfo'] ?? $error['msg'] ?? null)
            ->filter()
            ->unique()
            ->implode(', ');

        return $messages ?: $fallback;
    }

    private function responseMessage(Response $response, string $fallback): string
    {
        $json = $response->json();

        if (! is_array($json)) {
            return $response->body() ?: $fallback;
        }

        $details = collect($json['details'] ?? [])
            ->map(function (array $detail) {
                $message = $detail['message'] ?? $detail['msg'] ?? null;
                $field = $detail['field'] ?? null;

                return $field && $message ? "{$field}: {$message}" : $message;
            })
            ->filter()
            ->implode(', ');

        return $details
            ?: ($json['message'] ?? null)
            ?: ($json['error_description'] ?? null)
            ?: (is_string($json['error'] ?? null) ? $json['error'] : null)
            ?: $fallback;
    }

    private function normalizeMac(string $mac): string
    {
        $normalized = strtolower(preg_replace('/[^a-f0-9]/i', '', $mac));

        if (strlen($normalized) !== 12) {
            throw new RuntimeException('The Yealink device MAC address is invalid.');
        }

        return $normalized;
    }
}
