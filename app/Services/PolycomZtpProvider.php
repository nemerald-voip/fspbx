<?php

namespace App\Services;

use App\Models\Devices;
use App\Services\Interfaces\ZtpProviderInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PolycomZtpProvider implements ZtpProviderInterface
{
    protected string $apiKey;
    protected string $baseUrl;

    /**
     * ZTPApiService constructor.
     * Retrieves the API key from the configuration and sets the base URL.
     */
    public function __construct()
    {
        $this->apiKey = config('services.ztp.polycom.api_key');
        $this->baseUrl = 'https://api.ztp.poly.com/preview';
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

        $response = Http::withHeaders([
            'API-KEY' => $this->apiKey,
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

        $response = Http::withHeaders([
            'API-KEY' => $this->apiKey,
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

        $payload = [
            'id' => $deviceId,
            'profile' => $orgId
        ];

        $response = Http::withHeaders([
            'API-KEY' => $this->apiKey,
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

        $response = Http::withHeaders([
            'API-KEY' => $this->apiKey,
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

        logger($url);
        $response = Http::withHeaders([
            'API-KEY' => $this->apiKey,
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

        $response = Http::withHeaders([
            'API-KEY' => $this->apiKey,
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

        $payload = [
            'name' => $name
        ];

        $response = Http::withHeaders([
            'API-KEY' => $this->apiKey,
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

        $response = Http::withHeaders([
            'API-KEY' => $this->apiKey,
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

        $payload = [
            'name' => $name
        ];

        $response = Http::withHeaders([
            'API-KEY' => $this->apiKey,
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

        $response = Http::withHeaders([
            'API-KEY' => $this->apiKey,
        ])->delete($url);

        return $this->handleResponse($response);
    }

    /**
     * Handle the API response, checking for different types of errors and successes.
     *
     * @param  Response  $response
     * @return array
     * @throws \Exception
     */
    private function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            return (!empty($response->body())) ? json_decode($response->body(), true) : [];
        }

        if ($response->clientError()) {
            // Log client errors
            logger('ZTP API Client Error: '.$response->body());
            throw new \Exception('ZTP API Response: '.$response->json('message'));
        }

        if ($response->serverError()) {
            // Log server errors
            logger('ZTP API Server Error: '.$response->body());
            throw new \Exception('The ZTP API is currently unavailable. Please try again later.');
        }

        // Handle unexpected errors
        logger($response);
        throw new \Exception('An unexpected error occurred. Please try again.');
    }
}
