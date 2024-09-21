<?php

namespace App\Services;

class SansayApiService
{
    protected $servers;

    public function __construct()
    {
        $this->servers = config('sansay.servers');
    }

    public function fetchDataFromServer($server)
    {
        $serverDetails = $this->servers[$server];
        logger($serverDetails);
        // Use $serverDetails['base_url'] and $serverDetails['api_key'] to make API requests
    }


    public function fetchStats($server)
    {
        $serverDetails = $this->servers[$server];

        $response = Http::withHeaders([
            'Authorization' => $this->authorization,
            'Cookie' => $this->cookie
        ])->get($this->url);

        if ($response->successful()) {
            return $response->json(); // Return the JSON data
        }

        // Handle errors
        return [
            'error' => true,
            'message' => $response->body()
        ];
    }
}
