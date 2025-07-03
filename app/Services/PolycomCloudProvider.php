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
    public function __construct()
    {

    }

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
     * Ensure an API token exists before making API requests.
     *
     * @return string The validated API token.
     * @throws \Exception If the API token is missing.
     */
    public function ensureApiTokenExists(): string
    {
        $token = $this->getApiToken();

        if (empty($token)) {
            throw new \Exception("Polycom API token is missing. Please configure it in the Default Settings");
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
            ->get('/devices?limit='.$limit.'&cursor='.$cursor)
            ->throw(function ($error) {
                throw new \Exception("Unable to retrieve devices: ".json_encode($error));
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
            ->get('/devices/'.$id)
            ->throw(function ($error) {
                throw new \Exception("Unable to retrieve devices: ".json_encode($error));
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
            ->delete('/devices/'.$params['device_address']);

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
            ->get('/profiles')
            ->throw(function ($error) {
                throw new \Exception("Unable to retrieve organizations: ".json_encode($error));
            });

        $response = $this->handleResponse($response);

        return collect($response['results'])->map(function ($item) {
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
            ->post('/profiles')
            ->throw(function () {
                throw new \Exception("Unable to activate organization");
            });

        return $this->handleResponse($response)['id'];
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
            ->put('/profiles/'.$id)
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
        $response = Http::polycom()
            ->timeout($this->timeout)
            ->get('/profiles/'.$id)
            ->throw(function ($error) {
                throw new \Exception("Unable to retrieve organizations: ".json_encode($error));
            });
        return PolycomOrganizationDTO::fromArray($this->handleResponse($response));
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
            ->delete('/profiles/'.$id)
            ->throw(function ($error) {
                throw new \Exception("Unable to delete organization: ".json_encode($error));
            });

        return $this->handleResponse($response)['message'];
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
    

    public function getOrgIdByDomainUuid(string $domainUuid): mixed
    {
        return DomainSettings::where([
            ['domain_uuid', '=', $domainUuid],
            ['domain_setting_category', '=', 'cloud provision'],
            ['domain_setting_subcategory', '=', 'polycom_ztp_profile_id'],
            ['domain_setting_enabled', '=', true],
        ])->value('domain_setting_value');
    }
}
