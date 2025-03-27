<?php

namespace App\Services;

use App\DTO\OrganizationDTOInterface;
use App\DTO\PolycomOrganizationDTO;
use App\DTO\RingotelOrganizationDTO;
use App\Models\DefaultSettings;
use App\Models\Devices;
use App\Models\DomainSettings;
use App\Services\Exceptions\ZtpProviderException;
use App\Services\Interfaces\ZtpProviderInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class PolycomZtpProvider implements ZtpProviderInterface
{
    protected string $providerName = 'polycom';
    protected int $timeout = 30;

    /**
     * Constructor for PolycomZtpProvider.
     * Initializes necessary service objects or parameters.
     */
    public function __construct()
    {

    }

    /**
     * Retrieve the Polycom API token from the database or configuration.
     *
     * @return string The API token.
     */
    public function getApiToken(): string
    {
        // Check the DefaultSettings table
        $value = DefaultSettings::where([
            ['default_setting_category', '=', 'cloud provision'],
            ['default_setting_subcategory', '=', 'polycom_api_token'],
            ['default_setting_enabled', '=', 'true'],
        ])->value('default_setting_value');

        if ($value !== null) {
            return $value;
        }

        // Fallback to config and .env
        return config("services.ztp.polycom.api_key", '');
    }

    /**
     * Ensure an API token exists before making API requests.
     *
     * @return string The validated API token.
     * @throws \Exception If the API token is missing.
     */
    protected function ensureApiTokenExists(): string
    {
        $token = $this->getApiToken();

        if (empty($token)) {
            throw new \Exception("Polycom API token is missing. Please configure it in the DefaultSettings table or .env file.");
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
    public function getDevices(array $addresses = []): array
    {
        $this->ensureApiTokenExists();

        $response = Http::polycom()
            ->timeout($this->timeout)
            ->get('/devices')
            ->throw(function ($error) {
                throw new \Exception("Unable to retrieve devices: ".json_encode($error));
            });

        $allDevices = $this->handleResponse($response)['results'];

        // Ensure ids and results are normalized and comparable
        $indexedDevices = [];
        foreach ($allDevices as $device) {
            if (isset($device['id'])) {
                $indexedDevices[strtolower($device['id'])] = $device;
            }
        }

        if (empty($addresses)) {
            return $indexedDevices;
        }

        // Filter devices based on provided ids
        return array_filter($indexedDevices, function ($id) use ($addresses) {
            return in_array($id, $addresses, true);
        }, ARRAY_FILTER_USE_KEY);
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
     * @param  string  $id  The device ID.
     * @param  string  $orgId  The organization ID.
     * @return array The response from the API.
     * @throws \Exception If the API request fails.
     */
    public function createDevice(string $id, string $orgId): array
    {
        $this->ensureApiTokenExists();

        $response = Http::polycom()
            ->timeout($this->timeout)
            ->withBody(json_encode([
                'id' => $id,
                'profile' => $orgId
            ]), 'application/json')
            ->post('/devices')
            ->throw(function ($error) {
                throw new \Exception("Unable to create device: ".json_encode($error));
            });

        return $this->handleResponse($response);
    }

    /**
     * Delete a device by its ID.
     *
     * @param  string  $id  The device ID.
     * @return array The response from the API.
     * @throws \Exception If the API request fails.
     */
    public function deleteDevice(string $id): array
    {
        $this->ensureApiTokenExists();

        $response = Http::polycom()
            ->timeout($this->timeout)
            ->delete('/devices/'.$id)
            ->throw(function ($error) {
                throw new \Exception("Unable to delete device: ".json_encode($error));
            });

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
     * @throws ZtpProviderException If an error occurs during processing.
     */
    private function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            return (!empty($response->body())) ? json_decode($response->body(), true) : [];
        }

        if ($response->clientError() or $response->serverError()) {
            throw new ZtpProviderException($response->body());
        }

        // Handle unexpected errors
        throw new ZtpProviderException($response);
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
