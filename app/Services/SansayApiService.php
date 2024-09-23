<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SansayApiService
{
    protected $servers;

    public function __construct()
    {
        $this->servers = config('sansay.servers');
    }

    public function fetchStats($server)
    {
        $serverDetails = $this->servers[$server];

        $authorization = 'Basic ' . base64_encode($serverDetails['user'] . ':' . $serverDetails['api_key']);


        // Define the endpoint and parameters
        $endpoint = '/SSConfig/webresources/stats/sub_stats';
        $params = ['format' => 'json'];

        // Build the URL by combining base_url and endpoint
        $url = $serverDetails['base_url'] . $endpoint;

        $response = Http::withHeaders([
            'Authorization' => $authorization,
        ])->withoutVerifying()->get($url, $params);

        if ($response->successful()) {
            // logger($response);
            $data = $response->json();
            return collect($data['XBSubStatsList']['XBSubStats']); // Return the data
        }

        // Handle errors
        return [
            'error' => true,
            'message' => $response->body()
        ];
    }
}
