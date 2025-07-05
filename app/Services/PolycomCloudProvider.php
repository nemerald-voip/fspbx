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

        return collect($response['data']['results'])->map(function ($item) {
            return PolycomOrganizationDTO::fromArray($item);
        });
    }

    /**
     * Create an organization with the given parameters.
     *
     * @param  array  $params  The organization parameters.
     * @return string The result of the operation.
     * @throws ZtpProviderException If the API request fails.
     * @throws \Exception
     */
    public function createOrganization(array $params): string
    {
        $this->ensureApiTokenExists();

        $payload = [
            "name" => $params['organization_name'],
            "enabled" => $params['enabled'],
            "template" => [
                "software" => [
                    "version" => $params['software_version'],
                ],
                "provisioning" => [
                    "server" => [
                        "address" => $params['provisioning_server_address'],
                        "username" => $params['provisioning_server_username'],
                        "password" => $params['provisioning_server_password']
                    ],
                    "polling" => $params['provisioning_polling'],
                    "quickSetup" => $params['provisioning_quick_setup'],
                ],
                "dhcp" => [
                    "bootServerOption" => $params['dhcp_boot_server_option'] ?? null,
                    "option60Type" => $params['dhcp_option_60_type'] ?? null,
                ],
                "localization" => [
                    "language" => $params['localization_language'] ?? null,
                ],
            ],
        ];

        $response = Http::polycom()
            ->timeout($this->timeout)
            ->withBody(json_encode($payload), 'application/json')
            ->post('/profiles');

        return $this->handleResponse($response)['data']['id'];
    }

    /**
     * Update an existing organization with the given ID and parameters.
     *
     * @param  string  $id  The organization ID.
     * @param  array  $params  The parameters to update.
     * @return string The result of the operation.
     * @throws \Exception If the API request fails.
     */
    public function updateOrganization(string $id, array $params): string
    {
        $this->ensureApiTokenExists();

        $payload = [
            "name" => $params['organization_name'],
            "enabled" => $params['enabled'],
            "template" => [
                "software" => [
                    "version" => $params['software_version'],
                ],
                "provisioning" => [
                    "server" => [
                        "address" => $params['provisioning_server_address'],
                        "username" => $params['provisioning_server_username'],
                        "password" => $params['provisioning_server_password']
                    ],
                    "polling" => $params['provisioning_polling'],
                    "quickSetup" => $params['provisioning_quick_setup'],
                ],
                "dhcp" => [
                    "bootServerOption" => $params['dhcp_boot_server_option'] ?? null,
                    "option60Type" => $params['dhcp_option_60_type'] ?? null,
                ],
                "localization" => [
                    "language" => $params['localization_language'] ?? null,
                ],
            ],
        ];

        $response = Http::polycom()
            ->timeout($this->timeout)
            ->withBody(json_encode($payload), 'application/json')
            ->put('/profiles/' . $id)
            ->throw(function () {
                throw new \Exception("Unable to update organization");
            });

        return $this->handleResponse($response)['id'];
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
    public function deleteOrganization(string $id): string
    {
        $this->ensureApiTokenExists();

        $response = Http::polycom()
            ->timeout($this->timeout)
            ->delete('/profiles/' . $id);


        $response = $this->handleResponse($response);

        return $response['data']['message'];
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
        ];
    }
}
