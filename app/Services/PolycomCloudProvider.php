<?php

namespace App\Services;

use App\Models\DomainSettings;
use App\Models\DefaultSettings;
use Illuminate\Support\Collection;
use App\DTO\PolycomOrganizationDTO;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use App\DTO\OrganizationDTOInterface;
use App\Models\Devices;
use App\Services\Exceptions\ZtpProviderException;
use App\Services\Interfaces\CloudProviderInterface;

class PolycomCloudProvider implements CloudProviderInterface
{
    protected string $providerName = 'polycom';
    protected int $timeout = 60;

    /**
     * Constructor for PolycomCloudProvider.
     * Initializes necessary service objects or parameters.
     */
    public function __construct() {}

    /**
     * Retrieve the Polycom API token from the database.
     *
     * @return string The API token.
     */
    public function getApiToken()
    {
        // Check the DefaultSettings table
        $value = DefaultSettings::where([
            ['default_setting_category', '=', 'cloud_provision'],
            ['default_setting_subcategory', '=', 'polycom_api_token'],
            ['default_setting_enabled', '=', 'true'],
        ])->value('default_setting_value');

        if ($value !== null) {
            return $value;
        }
        return null;
    }

    /**
     * Create or update the Polycom API token in the database.
     *
     * @param string $token
     * @return void
     */
    public function setApiToken(string $token): void
    {
        DefaultSettings::updateOrCreate(
            [
                'default_setting_category'    => 'cloud_provision',
                'default_setting_subcategory' => 'polycom_api_token',
            ],
            [
                'default_setting_value'   => $token,
                'default_setting_enabled' => 'true',
            ]
        );
    }

    /**
     * Ensure an API token exists before making API requests.
     *
     * @return string The validated API token.
     * @throws \Exception If the API token is missing.
     */
    public function ensureApiTokenExists(): string
    {
        $token = $this->getApiToken();

        if (empty($token)) {
            throw new \Exception("Polycom ZTP API token is missing.");
        }

        return $token;
    }

    /**
     * Get the provider name.
     *
     * @return string The provider name.
     */
    public function getProviderName(): string
    {
        return $this->providerName;
    }

    /**
     * Retrieve a list of devices with optional filtering by addresses.
     *
     * @param  array  $addresses  Optional array of device IDs for filtering.
     * @return array The list of devices.
     * @throws \Exception If the API request fails.
     */
    public function getDevices(int $limit = 50, string $cursor = null): array
    {
        $this->ensureApiTokenExists();

        $response = Http::polycom()
            ->timeout($this->timeout)
            ->get('/devices?limit=' . $limit . '&cursor=' . $cursor)
            ->throw(function ($error) {
                throw new \Exception("Unable to retrieve devices: " . json_encode($error));
            });

        return $this->handleResponse($response);
    }



    /**
     * Retrieve a single device by its ID.
     *
     * @param  string  $id  The device ID.
     * @return array The device information.
     * @throws \Exception If the API request fails.
     */
    public function getDevice(string $id): array
    {
        $this->ensureApiTokenExists();

        $response = Http::polycom()
            ->timeout($this->timeout)
            ->get('/devices/' . $id)
            ->throw(function ($error) {
                throw new \Exception("Unable to retrieve devices: " . json_encode($error));
            });

        return $this->handleResponse($response);
    }

    /**
     * Create a new device with the given ID and organization ID.
     *
     * @param  array  $params 
     * @return array The response from the API.
     * @throws \Exception If the API request fails.
     */
    public function createDevice($params): array
    {
        $this->ensureApiTokenExists();

        $orgId = $this->getOrgIdByDomainUuid($params['domain_uuid']);

        $response = Http::polycom()
            ->timeout($this->timeout)
            ->withBody(json_encode([
                'id' => $params['device_address'],
                'profile' => $orgId
            ]), 'application/json')
            ->post('/devices');

        return $this->handleResponse($response);
    }

    /**
     * Delete a device by its ID.
     *
     * @param  string  $id  The device ID.
     * @return array The response from the API.
     * @throws \Exception If the API request fails.
     */
    public function deleteDevice($params): array
    {
        $this->ensureApiTokenExists();

        $response = Http::polycom()
            ->timeout($this->timeout)
            ->delete('/devices/' . $params['device_address']);

        return $this->handleResponse($response);
    }

    /**
     * Retrieve a list of organizations.
     *
     * @return Collection The list of organizations.
     * @throws \Exception If the API request fails.
     */
    public function getOrganizations(): Collection
    {
        $this->ensureApiTokenExists();

        $response = Http::polycom()
            ->timeout($this->timeout)
            ->get('/profiles');

        $response = $this->handleResponse($response);

        if ($response['success']) {
            return collect($response['data']['results'])->map(function ($item) {
                return PolycomOrganizationDTO::fromArray($item);
            });
        } else {
            throw new \Exception($response['error']);
        }
    }

    /**
     * Create an organization with the given parameters.
     *
     * @param  array  $params  The organization parameters.
     * @return string The result of the operation.
     * @throws ZtpProviderException If the API request fails.
     * @throws \Exception
     */
    public function createOrganization(array $params)
    {
        $this->ensureApiTokenExists();
    
        $payload = [];
    
        if (isset($params['name'])) {
            $payload['name'] = $params['name'];
        }
        if (isset($params['enabled'])) {
            $payload['enabled'] = $params['enabled'];
        }
    
        $template = [];
    
        if (isset($params['software'])) {
            $template['software']['version'] = $params['software'];
        }
    
        // Provisioning subfields
        if (
            isset($params['address']) || isset($params['username']) || isset($params['password']) ||
            isset($params['polling']) || isset($params['quickSetup'])
        ) {
            $template['provisioning']['server'] = [];
            if (isset($params['address']))   $template['provisioning']['server']['address'] = $params['address'];
            if (isset($params['username']))  $template['provisioning']['server']['username'] = $params['username'];
            if (isset($params['password']))  $template['provisioning']['server']['password'] = $params['password'];
            if (isset($params['polling']))   $template['provisioning']['polling'] = $params['polling'];
            if (isset($params['quickSetup'])) $template['provisioning']['quickSetup'] = $params['quickSetup'];
        }
    
        // DHCP subfields
        if (isset($params['bootServerOption']) || isset($params['option60Type'])) {
            $template['dhcp'] = [];
            if (isset($params['bootServerOption'])) $template['dhcp']['bootServerOption'] = $params['bootServerOption'];
            if (isset($params['option60Type']))    $template['dhcp']['option60Type'] = $params['option60Type'];
        }
    
        // Localization
        if (isset($params['localization'])) {
            $template['localization'] = ['language' => $params['localization']];
        }
    
        if (!empty($template)) {
            $payload['template'] = $template;
        }
    
        // Custom fields
        $custom = [];
        if (isset($params['ucs'])) $custom['ucs'] = $params['ucs'];
        if (isset($params['obi'])) $custom['obi'] = $params['obi'];
        if (!empty($custom)) $payload['custom'] = $custom;
    
        $response = Http::polycom()
            ->timeout($this->timeout)
            ->withBody(json_encode($payload), 'application/json')
            ->post('/profiles');
    
        $response = $this->handleResponse($response);
    
        if ($response['success']) {
            DomainSettings::updateOrCreate(
                [
                    'domain_uuid' => session('domain_uuid'),
                    'domain_setting_category' => 'cloud provision',
                    'domain_setting_subcategory' => 'polycom_ztp_profile_id',
                ],
                [
                    'domain_setting_name' => 'text',
                    'domain_setting_value' => $response['data']['id'] ?? null,
                    'domain_setting_enabled' => true,
                ]
            );
            return $response['data'];
        } else {
            throw new \Exception($response['error']);
        }
    }
    

    /**
     * Update an existing organization with the given ID and parameters.
     *
     * @param  string  $id  The organization ID.
     * @param  array  $params  The parameters to update.
     * @return string The result of the operation.
     * @throws \Exception If the API request fails.
     */
    public function updateOrganization(array $params)
    {
        $this->ensureApiTokenExists();

        $payload = [];

        if (isset($params['name'])) {
            $payload['name'] = $params['name'];
        }
        if (isset($params['enabled'])) {
            $payload['enabled'] = $params['enabled'];
        }

        $template = [];

        if (isset($params['software'])) {
            $template['software']['version'] = $params['software'];
        }

        // Provisioning subfields
        if (
            isset($params['address']) || isset($params['username']) || isset($params['password']) ||
            isset($params['polling']) || isset($params['quickSetup'])
        ) {
            $template['provisioning']['server'] = [];
            if (isset($params['address']))   $template['provisioning']['server']['address'] = $params['address'];
            if (isset($params['username']))  $template['provisioning']['server']['username'] = $params['username'];
            if (isset($params['password']))  $template['provisioning']['server']['password'] = $params['password'];
            if (isset($params['polling']))   $template['provisioning']['polling'] = $params['polling'];
            if (isset($params['quickSetup'])) $template['provisioning']['quickSetup'] = $params['quickSetup'];
        }

        // DHCP subfields
        if (isset($params['bootServerOption']) || isset($params['option60Type'])) {
            $template['dhcp'] = [];
            if (isset($params['bootServerOption'])) $template['dhcp']['bootServerOption'] = $params['bootServerOption'];
            if (isset($params['option60Type']))    $template['dhcp']['option60Type'] = $params['option60Type'];
        }

        // Localization
        if (isset($params['localization'])) {
            $template['localization'] = ['language' => $params['localization']];
        }

        if (!empty($template)) {
            $payload['template'] = $template;
        }

        $custom = [];
        if (isset($params['ucs'])) $custom['ucs'] = $params['ucs'];
        if (isset($params['obi'])) $custom['obi'] = $params['obi'];
        if (!empty($custom)) $payload['custom'] = $custom;

        $response = Http::polycom()
            ->timeout($this->timeout)
            ->withBody(json_encode($payload), 'application/json')
            ->put('/profiles/' . $params['organization_id']);

        $response = $this->handleResponse($response);

        if ($response['success']) {
            return $response['data'];
        } else {
            throw new \Exception($response['error']);
        }
    }


    /**
     * Retrieve details of a specific organization by ID.
     *
     * @param  string  $id  The ID of the organization to retrieve.
     * @return OrganizationDTOInterface The organization information as a DTO.
     * @throws \Exception If the API request fails or returns an error.
     */
    public function getOrganization(string $id): OrganizationDTOInterface
    {
        $this->ensureApiTokenExists();

        $response = Http::polycom()
            ->timeout($this->timeout)
            ->get('/profiles/' . $id);

        // logger($response);

        $response = $this->handleResponse($response);

        if (empty($response['data']) || !is_array($response['data'])) {
            throw new \Exception('Polycom organization not found or invalid response: ' . json_encode($response));
        }

        return PolycomOrganizationDTO::fromArray($response['data'] ?? null);
    }

    /**
     * Delete an organization by its ID.
     *
     * @param  string  $id  The organization ID.
     * @return string The result of the operation.
     * @throws ZtpProviderException|\Exception If the API request fails.
     */
    public function deleteOrganization(string $id)
    {
        $this->ensureApiTokenExists();

        // Remove local references from the database
        DomainSettings::where('domain_uuid', session('domain_uuid'))
            ->where('domain_setting_category', 'cloud provision')
            ->where('domain_setting_subcategory', 'polycom_ztp_profile_id')
            ->delete();

        $response = Http::polycom()
            ->timeout($this->timeout)
            ->delete('/profiles/' . $id);


        $response = $this->handleResponse($response);

        if ($response['success']) {
            return $response['data'];
        } else {
            throw new \Exception($response['error']);
        }
    }

    /**
     * Handle the API responses, checking for errors or returning data.
     *
     * @param  Response  $response  The HTTP response.
     * @return array The decoded JSON response.
     * @throws Exception If an error occurs during processing.
     */
    protected function handleResponse($response): array
    {
        // Laravel HTTP client uses $response->successful(), $response->failed(), etc.
        if ($response->successful()) {
            // Attempt to decode JSON, fallback to raw
            $json = $response->json();
            if (is_array($json)) {
                return [
                    'success' => true,
                    'data' => $json,
                    'status' => $response->status(),
                ];
            } else {
                // In case response isn't JSON
                return [
                    'success' => true,
                    'data' => $response->body(),
                    'status' => $response->status(),
                ];
            }
        }

        logger()->warning('Polycom ZTP API error', [
            'status' => $response->status(),
            'body' => $response->body(),
            'headers' => $response->headers(),
            'url' => $response->effectiveUri() ?? null,
        ]);

        // If it wasn't a 2xx
        $error = $response->json();
        $errorMessage = null;

        // Many OpenAPI APIs send 'error', 'message', or similar
        if (is_array($error)) {
            $errorMessage = $error['error'] ?? $error['message'] ?? json_encode($error);
        }

        // Fallback to status text if nothing in body
        if (!$errorMessage) {
            $errorMessage = $response->body() ?: $response->status();
        }

        // Optionally, you can throw or return an error array
        // throw new \Exception("API Error ({$response->status()}): " . $errorMessage);

        // Or, if you want to return an error array instead of throwing:
        return [
            'success' => false,
            'error' => $errorMessage,
            'status' => $response->status(),
        ];
    }


    public static function getOrgIdByDomainUuid(string $domainUuid): mixed
    {
        return DomainSettings::where([
            ['domain_uuid', '=', $domainUuid],
            ['domain_setting_category', '=', 'cloud provision'],
            ['domain_setting_subcategory', '=', 'polycom_ztp_profile_id'],
            ['domain_setting_enabled', '=', true],
        ])->value('domain_setting_value');
    }

    public static function getSettings(): array
    {
        return [
            'dhcp_option_60_type_list' => [
                ['value' => 'ASCII', 'label' => 'ASCII'],
                ['value' => 'BINARY', 'label' => 'BINARY'],
            ],
            'dhcp_boot_server_option_list' => [
                ['value' => 'OPTION66', 'label' => 'OPTION66'],
                ['value' => 'CUSTOM', 'label' => 'CUSTOM'],
                ['value' => 'STATIC', 'label' => 'STATIC'],
                ['value' => 'CUSTOM_OPTION66', 'label' => 'CUSTOM_OPTION66'],
            ],
            'locales' => [
                ['value' => 'Chinese_China', 'label' => 'Chinese_China'],
                ['value' => 'Chinese_Taiwan', 'label' => 'Chinese_Taiwan'],
                ['value' => 'Danish_Denmark', 'label' => 'Danish_Denmark'],
                ['value' => 'Dutch_Netherlands', 'label' => 'Dutch_Netherlands'],
                ['value' => 'English_Canada', 'label' => 'English_Canada'],
                ['value' => 'English_United_Kingdom', 'label' => 'English_United_Kingdom'],
                ['value' => 'English_United_States', 'label' => 'English_United_States'],
                ['value' => 'French_France', 'label' => 'French_France'],
                ['value' => 'German_Germany', 'label' => 'German_Germany'],
                ['value' => 'Italian_Italy', 'label' => 'Italian_Italy'],
                ['value' => 'Japanese_Japan', 'label' => 'Japanese_Japan'],
                ['value' => 'Korean_Korea', 'label' => 'Korean_Korea'],
                ['value' => 'Norwegian_Norway', 'label' => 'Norwegian_Norway'],
                ['value' => 'Polish_Poland', 'label' => 'Polish_Poland'],
                ['value' => 'Portuguese_Portugal', 'label' => 'Portuguese_Portugal'],
                ['value' => 'Russian_Russia', 'label' => 'Russian_Russia'],
                ['value' => 'Slovenian_Slovenia', 'label' => 'Slovenian_Slovenia'],
                ['value' => 'Spanish_Spain', 'label' => 'Spanish_Spain'],
                ['value' => 'Swedish_Sweden', 'label' => 'Swedish_Sweden'],
            ],
            'polycom_api_token' => get_domain_setting('polycom_api_token') ?? null,
            'polycom_provision_url' =>     get_domain_setting('polycom_provision_url') ?? null,
            'http_auth_username' => get_domain_setting('http_auth_username') ?? null,
            'http_auth_password' => get_domain_setting('http_auth_password') ?? null,
            'polycom_custom_configuration' => get_domain_setting('polycom_custom_configuration') ?? null,
            'provider' => 'polycom',
        ];
    }
}
