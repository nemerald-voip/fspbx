<?php

namespace App\Services;

use App\Models\DefaultSettings;
use App\Models\Devices;
use App\Services\Exceptions\ZtpProviderException;
use App\Services\Interfaces\ZtpProviderInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PolycomZtpProvider implements ZtpProviderInterface
{
    protected string $baseUrl;
    protected string $providerName = 'polycom';

    /**
     * ZTPApiService constructor.
     * Retrieves the API key from the configuration and sets the base URL.
     */
    public function __construct()
    {
        $this->baseUrl = 'https://api.ztp.poly.com/preview';
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
    public function listDevices(array $addresses = [], int $limit = 50): array
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
     * Retrieve details of a specific device by its ID.
     *
     * @param  Devices $device
     * @return string
     * @throws \Exception
     *
    public function getDevice(Devices $device): string
    {
        $url = "$this->baseUrl/devices/$device->device_address";

        $token = $this->ensureApiTokenExists();

        logger($url);
        $response = Http::withHeaders([
            'API-KEY' => $token,
        ])->get($url);

        return $this->handleResponse($response);
    }

    /**
     * Retrieve a list of profiles with a configurable limit.
     *
     * @param  int  $limit
     * @return string
     * @throws \Exception
     *
    public function getProfiles(int $limit = 50): string
    {
        $url = "$this->baseUrl/profiles?limit=$limit";

        $token = $this->ensureApiTokenExists();

        $response = Http::withHeaders([
            'API-KEY' => $token,
        ])->get($url);

        return $this->handleResponse($response);
    }

    /**
     * Create a new profile with the given name.
     *
     * @param  string  $name
     * @return string
     * @throws \Exception
     *
    public function createProfile(string $name): string
    {
        $url = "$this->baseUrl/profiles";

        $token = $this->ensureApiTokenExists();

        $payload = [
            'name' => $name
        ];

        $response = Http::withHeaders([
            'API-KEY' => $token,
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        return $this->handleResponse($response);
    }

    /**
     * Retrieve details of a specific profile by its ID.
     *
     * @param  string  $profileId
     * @return string
     * @throws \Exception
     *
    public function getProfile(string $profileId): string
    {
        $url = "$this->baseUrl/profiles/$profileId";

        $token = $this->ensureApiTokenExists();

        $response = Http::withHeaders([
            'API-KEY' => $token,
        ])->get($url);

        return $this->handleResponse($response);
    }

    /**
     * Update a profile by its ID with a new name.
     *
     * @param  string  $profileId
     * @param  string  $name
     * @return string
     * @throws \Exception
     *
    public function updateProfile(string $profileId, string $name): string
    {
        $url = "$this->baseUrl/profiles/$profileId";

        $token = $this->ensureApiTokenExists();

        $payload = [
            'name' => $name
        ];

        $response = Http::withHeaders([
            'API-KEY' => $token,
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        return $this->handleResponse($response);
    }

    /**
     * Delete a profile by its ID.
     *
     * @param  string  $profileId
     * @return string
     * @throws \Exception
     *
    public function deleteProfile(string $profileId): string
    {
        $url = "$this->baseUrl/profiles/$profileId";

        $token = $this->ensureApiTokenExists();

        $response = Http::withHeaders([
            'API-KEY' => $token,
        ])->delete($url);

        return $this->handleResponse($response);
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
