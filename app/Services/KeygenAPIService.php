<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class KeygenApiService
{
    protected $baseUrl;
    protected $accountId;

    public function __construct()
    {
        $this->baseUrl = config('services.keygen.api_url'); // e.g. https://api.keygen.sh/v1/accounts/{account_id}
        $this->accountId = config('services.keygen.account_id'); // Account ID from env
    }

    /**
     * Validate the license using the license key.
     *
     * @param string $licenseKey
     * @param array|null $meta (e.g., scope)
     * @return bool|array Returns false if invalid, otherwise returns license data
     */
    public function validateLicenseKey($licenseKey)
    {
        $fingerprint = $this->getMachineFingerprint(); // Get the SHA256 of the machine's MAC address

        $url = "{$this->baseUrl}/v1/accounts/{$this->accountId}/licenses/actions/validate-key";

        // Add the license key to meta data
        $meta['key'] = $licenseKey;

        $response = Http::withHeaders([
            'Content-Type' => 'application/vnd.api+json',
            'Accept' => 'application/vnd.api+json',
        ])
            ->post($url, [
                'meta' => [
                    'key' => $licenseKey,
                    'scope' => [
                        'fingerprint' => $fingerprint
                    ]
                ]
            ]);

        // logger($response);

        if ($response->successful()) {
            return $response->json();
        }

        return false; // License is invalid or there was an error
    }

    public function isLicenseValid($licenseKey)
    {
        // Validate the license key
        $licenseData = $this->validateLicenseKey($licenseKey);

        if ($licenseData && isset($licenseData['meta']['valid'])) {
            return $licenseData['meta']['valid'];  // Return the value of 'valid' from the meta
        }

        return false; // License is invalid if 'valid' is not set or it's false
    }


    /**
     * Activate the machine with the license ID and fingerprint.
     *
     * @param string $licenseId
     * @param string $fingerprint
     * @return bool|array
     */
    public function activateMachine($licenseKey, $licenseId)
    {

        $url = "{$this->baseUrl}/v1/accounts/{$this->accountId}/machines";

        $fingerprint = $this->getMachineFingerprint(); // Get the SHA256 of the machine's MAC address

        $response = Http::withHeaders([
            'Content-Type' => 'application/vnd.api+json',
            'Accept' => 'application/vnd.api+json',
            'Authorization' => 'License ' . $licenseKey
        ])
            ->post($url, [
                'data' => [
                    'type' => 'machines',
                    'attributes' => [
                        'fingerprint' => $fingerprint,
                        'platform' => php_uname('s'), // Get platform dynamically (e.g., Linux, Windows)
                        'name' => gethostname(), // Use the machine's hostname
                    ],
                    'relationships' => [
                        'license' => [
                            'data' => [
                                'type' => 'licenses',
                                'id' => $licenseId,
                            ]
                        ]
                    ]
                ]
            ]);

        // logger($response);

        if ($response->successful()) {
            return $response->json();
        }

        return false; // License is invalid or there was an error
    }

    public function deactivateMachine($licenseKey, $machineId)
    {
        $url = "{$this->baseUrl}/v1/accounts/{$this->accountId}/machines/{$machineId}";

        $response = Http::withHeaders([
            'Accept' => 'application/vnd.api+json',
            'Authorization' => 'License ' . $licenseKey
        ])
            ->delete($url);

        // logger($response);

        // Check if the response is successful and it's a 204 No Content
        if ($response->status() === 204) {
            return true;
        } else {
            return false;
        }
    }


    public function getMachinesByLicense($licenseKey, $machinesLink)
    {
        $url = "{$this->baseUrl}/$machinesLink";

        $response = Http::withHeaders([
            'Accept' => 'application/vnd.api+json',
            'Authorization' => 'License ' . $licenseKey
        ])
            ->get($url);

        return $response->json()['data'] ?? [];
    }


    public function getReleases($licenseKey)
    {
        $url = "{$this->baseUrl}/v1/accounts/{$this->accountId}/releases?limit=15";

        $response = Http::withHeaders([
            'Accept' => 'application/vnd.api+json',
            'Authorization' => 'License ' . $licenseKey
        ])
            ->get($url);

        return $response->json()['data'] ?? [];
    }

    public function downloadArtifact($licenseKey, $releaseVersion, $artifactName)
    {
        $url = "{$this->baseUrl}/v1/accounts/{$this->accountId}/releases/{$releaseVersion}/artifacts/{$artifactName}";

        $response = Http::withHeaders([
            'Authorization' => 'License ' . $licenseKey,
            'Accept' => 'application/vnd.api+json',
        ])->get($url);

        // Check if the response contains file content
        if ($response->ok()) {
            return $response;
        }

        return null;
    }




    /**
     * Generate a SHA256 hash of the machine's MAC address
     *
     * @return string
     */
    public function getMachineFingerprint()
    {
        // Get the MAC address of the machine (assumes Linux-based system)
        $macAddress = exec('cat /sys/class/net/eth0/address');

        // Hash the MAC address using SHA256
        return hash('sha256', $macAddress);
    }
}
