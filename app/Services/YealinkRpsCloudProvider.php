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

    public function __construct(
        protected ?YealinkRpsRequestSigner $signer = null,
    ) {
        $this->signer ??= new YealinkRpsRequestSigner();
    }

    public function getCredentials(): array
    {
        $settings = DefaultSettings::query()
            ->where('default_setting_category', 'cloud_provision')
            ->where('default_setting_enabled', 'true')
            ->whereIn('default_setting_subcategory', [
                'yealink_rps_access_key_id',
                'yealink_rps_access_key_secret',
            ])
            ->pluck('default_setting_value', 'default_setting_subcategory');

        return [
            'access_key_id' => $settings->get('yealink_rps_access_key_id'),
            'access_key_secret' => $settings->get('yealink_rps_access_key_secret'),
        ];
    }

    public function setCredentials(array $credentials): void
    {
        $this->storeCredential('yealink_rps_access_key_id', $credentials['access_key_id']);
        $this->storeCredential('yealink_rps_access_key_secret', $credentials['access_key_secret']);
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
        $response = $this->post('/api/open/v1/device/list', [
            'skip' => $skip,
            'limit' => $limit,
            'autoCount' => true,
        ]);

        if (! $response['success']) {
            return $response;
        }

        $page = is_array($response['data']) ? $response['data'] : [];
        $devices = is_array($page['data'] ?? null) ? $page['data'] : [];
        $nextOffset = $skip + count($devices);
        $total = (int) ($page['total'] ?? $nextOffset);

        $response['data'] = [
            'results' => $devices,
            'next' => count($devices) > 0 && $nextOffset < $total ? (string) $nextOffset : null,
        ];

        return $response;
    }

    public function getDevice(string $id): array
    {
        return $this->get('/api/open/v1/device/detail', ['id' => $id]);
    }

    public function createDevice(array $params): array
    {
        $serverId = static::getOrgIdByDomainUuid($params['domain_uuid']);

        if (blank($serverId)) {
            throw new RuntimeException('Yealink RPS server is not configured for this account.');
        }

        return $this->post('/api/open/v1/device/add', [
            'macs' => [$this->normalizeMac($params['device_address'])],
            'serverId' => $serverId,
        ]);
    }

    public function deleteDevice(array $params): array
    {
        return $this->post('/api/open/v1/device/delete', [
            'macs' => [$this->normalizeMac($params['device_address'])],
        ]);
    }

    public function getOrganizations(): Collection
    {
        $servers = collect();
        $skip = 0;
        $limit = 100;

        do {
            $response = $this->post('/api/open/v1/server/list', [
                'skip' => $skip,
                'limit' => $limit,
                'autoCount' => true,
            ]);
            $this->throwIfFailed($response, 'Unable to retrieve Yealink RPS servers.');

            $page = is_array($response['data']) ? $response['data'] : [];
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
        $response = $this->post('/api/open/v1/server/add', $this->serverPayload($params));
        $this->throwIfFailed($response, 'Unable to create the Yealink RPS server.');

        $server = is_array($response['data']) ? $response['data'] : [];
        $serverId = $server['id'] ?? null;

        if (blank($serverId)) {
            throw new RuntimeException('Yealink RPS did not return a server ID.');
        }

        $this->pairOrganization(session('domain_uuid'), $serverId);

        return $server;
    }

    public function getOrganization(string $id): OrganizationDTOInterface
    {
        $response = $this->get('/api/open/v1/server/detail', ['id' => $id]);
        $this->throwIfFailed($response, 'Unable to retrieve the Yealink RPS server.');

        if (! is_array($response['data'])) {
            throw new RuntimeException('Yealink RPS returned an invalid server response.');
        }

        return YealinkRpsServerDTO::fromArray($response['data']);
    }

    public function updateOrganization(array $params)
    {
        $response = $this->post('/api/open/v1/server/edit', array_merge(
            ['id' => $params['organization_id']],
            $this->serverPayload($params)
        ));
        $this->throwIfFailed($response, 'Unable to update the Yealink RPS server.');

        return $response['data'];
    }

    public function deleteOrganization(string $id)
    {
        $deviceIds = collect();
        $cursor = null;

        do {
            $page = $this->getDevices(100, $cursor);
            $this->throwIfFailed($page, 'Unable to retrieve devices assigned to the Yealink RPS server.');

            $devices = $page['data']['results'] ?? [];
            foreach ($devices as $device) {
                if (($device['serverId'] ?? null) === $id && filled($device['id'] ?? null)) {
                    $deviceIds->push($device['id']);
                }
            }

            $cursor = $page['data']['next'] ?? null;
        } while ($cursor !== null);

        foreach ($deviceIds->chunk(100) as $ids) {
            $response = $this->post('/api/open/v1/device/delete', ['ids' => $ids->values()->all()]);
            $this->throwIfFailed($response, 'Unable to remove devices assigned to the Yealink RPS server.');
        }

        $response = $this->post('/api/open/v1/server/delete', ['ids' => [$id]]);
        $this->throwIfFailed($response, 'Unable to delete the Yealink RPS server.');

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

    protected function post(string $path, array $payload): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $credentials = $this->ensureCredentialsExist();
        $headers = $this->signer->headers(
            'POST',
            $path,
            $credentials['access_key_id'],
            $credentials['access_key_secret'],
            $body,
        );

        $response = Http::baseUrl(config('services.ztp.yealink.api_url', 'https://api-dm.yealink.com:8443'))
            ->timeout($this->timeout)
            ->acceptJson()
            ->withHeaders($headers)
            ->withBody($body, 'application/json;charset=UTF-8')
            ->post($path);

        return $this->handleResponse($response);
    }

    protected function get(string $path, array $query): array
    {
        $credentials = $this->ensureCredentialsExist();
        $headers = $this->signer->headers(
            'GET',
            $path,
            $credentials['access_key_id'],
            $credentials['access_key_secret'],
            null,
            $query,
        );

        $response = Http::baseUrl(config('services.ztp.yealink.api_url', 'https://api-dm.yealink.com:8443'))
            ->timeout($this->timeout)
            ->acceptJson()
            ->withHeaders($headers)
            ->get($path, $query);

        return $this->handleResponse($response);
    }

    protected function handleResponse(Response $response): array
    {
        $json = $response->json();
        $providerSuccess = is_array($json) && (int) ($json['ret'] ?? -1) >= 0;

        if ($response->successful() && $providerSuccess) {
            return [
                'success' => true,
                'data' => $json['data'] ?? null,
                'status' => $response->status(),
            ];
        }

        $error = is_array($json)
            ? ($json['error'] ?? $json['errors'] ?? null)
            : null;
        $message = is_array($error)
            ? ($error['msg'] ?? null)
            : null;

        if (blank($message) && is_array($error) && is_array($error['fieldErrors'] ?? null)) {
            $message = collect($error['fieldErrors'])
                ->pluck('msg')
                ->filter()
                ->implode(', ');
        }

        $message = $message ?: $response->body() ?: 'Yealink RPS returned an error.';

        logger()->warning('Yealink RPS API error', [
            'status' => $response->status(),
            'error' => $message,
            'path' => (string) $response->effectiveUri(),
        ]);

        return [
            'success' => false,
            'error' => $message,
            'status' => $response->status(),
        ];
    }

    private function ensureCredentialsExist(): array
    {
        $credentials = $this->getCredentials();

        if (! $this->hasCredentials()) {
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

    private function throwIfFailed(array $response, string $fallback): void
    {
        if (! $response['success']) {
            throw new RuntimeException($response['error'] ?: $fallback);
        }
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
