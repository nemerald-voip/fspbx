<?php

namespace App\Services;

use App\DTO\RingotelOrganizationDTO;
use App\Models\DefaultSettings;
use App\Models\Devices;
use App\Services\Exceptions\ZtpProviderException;
use App\Services\Interfaces\ZtpProviderInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PolycomZtpProvider implements ZtpProviderInterface
{
    protected string $providerName = 'polycom';
    protected int $timeout = 30;

    /**
     * ZTPApiService constructor.
     * Retrieves the API key from the configuration and sets the base URL.
     */
    public function __construct()
    {

    }

    /**
     * Retrieve the configuration value for Polycom settings with fallback.
     *
     * @return string
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
     * Ensure that the API token exists before making API calls.
     *
     * @throws \Exception
     * @return string
     */
    protected function ensureApiTokenExists(): string
    {
        $token = $this->getApiToken();

        if (empty($token)) {
            throw new \Exception("Polycom API token is missing. Please configure it in the DefaultSettings table or .env file.");
        }

        return $token;
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    /**
     * Retrieve a list of devices with a configurable limit.
     *
     * @param  array  $addresses
     * @param  int  $limit
     * @return array
     * @throws \Exception
     */
    public function getDevices(array $addresses = [], int $limit = 50): array
    {
        $url = "$this->baseUrl/devices?limit=$limit";

        $token = $this->ensureApiTokenExists();

        $response = Http::withHeaders([
            'API-KEY' => $token,
        ])->get($url);

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
     * Get a device with the given ID..
     *
     * @param  string  $deviceId
     * @return array
     * @throws \Exception
     */
    public function getDevice(string $deviceId): array
    {
        $url = "$this->baseUrl/devices/$deviceId";

        $token = $this->ensureApiTokenExists();

        $response = Http::withHeaders([
            'API-KEY' => $token,
        ])->get($url);

        return $this->handleResponse($response);
    }

    /**
     * Create a new device with the given ID and organisation.
     *
     * @param  string  $deviceId
     * @param  string  $orgId
     * @return array
     * @throws \Exception
     */
    public function createDevice(string $deviceId, string $orgId): array
    {
        $url = "$this->baseUrl/devices";

        $token = $this->ensureApiTokenExists();

        $payload = [
            'id' => $deviceId,
            'profile' => $orgId
        ];

        $response = Http::withHeaders([
            'API-KEY' => $token,
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        return $this->handleResponse($response);
    }

    /**
     * Delete a device by its ID.
     *
     * @param  string  $deviceId
     * @return array
     * @throws \Exception
     */
    public function deleteDevice(string $deviceId): array
    {
        $url = "$this->baseUrl/devices/$deviceId";

        $token = $this->ensureApiTokenExists();

        $response = Http::withHeaders([
            'API-KEY' => $token,
        ])->delete($url);

        return $this->handleResponse($response);
    }

    /**
     * Retrieve a list of profiles with a configurable limit.
     *
     * @throws \Exception
     */
    public function getOrganisations(): array
    {
        $this->ensureApiTokenExists();

        $response = Http::polycom()
            ->timeout($this->timeout)
            ->get('/profiles')
            ->throw(function ($error) {
                throw new \Exception("Unable to retrieve organizations");
            });

        return $this->handleResponse($response)['results'];
    }

    public function createOrganisation(array $params): string
    {
        $this->ensureApiTokenExists();
        // Prepare the payload
        $data = [
            'method' => 'createOrganization',
            'params' => [
                'name' => $params['organization_name'],
                'region' => $params['region'],
                'domain' => $params['organization_domain'],
                'packageid' => (int) $params['package'],
                'params' => [
                    'hidePassInEmail' => $params['dont_send_user_credentials'],
                ],
            ],
        ];

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function () {
                throw new \Exception("Unable to activate organization");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }

        return $response['result'];
    }

    public function updateOrganisation(array $params): string
    {
        $this->ensureApiTokenExists();

        // Prepare the payload
        $data = [
            'method' => 'updateOrganization',
            'params' => [
                'id' => $params['organization_id'],
                'name' => $params['organization_name'],
                'packageid' => (int) $params['package'],
                'params' => [
                    'hidePassInEmail' => $params['dont_send_user_credentials'],
                ],
            ],
        ];

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function () {
                throw new \Exception("Unable to update organization");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        // Handle empty response
        if (!$response) {
            return ['success' => true, 'message' => 'Organization updated successfully'];
        }

        return $response['result'];
    }

    public function getOrganisation($id): string
    {
        $this->ensureApiTokenExists();

        // Prepare the payload
        $data = array(
            'method' => 'getOrganization',
            'params' => array(
                'id' => $id,
            )
        );

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function () {
                throw new \Exception("Unable to fetch organization");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }

        // Transform the result into OrganizationDTO
        return RingotelOrganizationDTO::fromArray($response['result']);
    }

    public function deleteOrganisation($id): string
    {
        $this->ensureApiTokenExists();
        // Prepare the payload
        $data = [
            'method' => 'deleteOrganization',
            'params' => [
                'id' => $id,
            ],
        ];

        // Send the request
        $response = Http::ringotel() // Ensure `ringotel` is configured in the HTTP client
        ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function ($response) {
                throw new \Exception("Failed to delete organization: {$response->body()}");
            })
            ->json();

        // Check for errors in the response
        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        // Handle empty response
        if (!$response) {
            return ['success' => true, 'message' => 'Organization and its connections were successfully deleted.'];
        }

        return $response['result'];
    }

    /**
     * Handle the API response, checking for different types of errors and successes.
     *
     * @param  Response  $response
     * @return array
     * @throws ZtpProviderException
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
}
