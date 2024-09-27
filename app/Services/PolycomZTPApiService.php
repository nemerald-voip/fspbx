<?php

namespace App\Services;

use App\Models\Devices;
use App\Services\Interfaces\ZtpProviderInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PolycomZTPApiService implements ZtpProviderInterface
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
     * @param  int  $limit
     * @return string
     * @throws \Exception
     *
    public function getDevices(int $limit = 50): string
    {
        $url = "$this->baseUrl/devices?limit=$limit";

        $response = Http::withHeaders([
            'API-KEY' => $this->apiKey,
        ])->get($url);

        return $this->handleResponse($response);
    }

    /**
     * Create a new device with the given ID and profile.
     *
     * @param  string  $id
     * @param  string  $profile
     * @return string
     * @throws \Exception
     *
    public function createDevice(string $id, string $profile): string
    {
        $url = "$this->baseUrl/devices";

        $payload = [
            'id' => $id,
            'profile' => $profile
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
     * @return string
     * @throws \Exception
     *
    public function deleteDevice(string $deviceId): string
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
     * @return string
     * @throws \Exception
     *
    private function handleResponse(Response $response): string
    {
        if ($response->successful()) {
            return $response->body();
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
    }*/
}
