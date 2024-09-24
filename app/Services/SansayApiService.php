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
        try {
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

            // Check for successful response
            if ($response->successful()) {
                $data = $response->json();
                return collect($data['XBSubStatsList']['XBSubStats']);
            } else {
                // Handle unsuccessful responses
                $status = $response->status();
                $errorMessage = $response->body() ?: 'Unknown error occurred';
                throw new \Exception("Failed to fetch stats. HTTP Status: $status, Error: $errorMessage");
            }
        } catch (\Exception $e) {
            // Log and rethrow the exception
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            throw new \Exception('Error while fetching stats: ' . $e->getMessage());
        }
    }


    public function deleteStats($server, $statsData)
    {
        try {
            $serverDetails = $this->servers[$server];
            $authorization = 'Basic ' . base64_encode($serverDetails['user'] . ':' . $serverDetails['api_key']);

            $endpoint = '/SSConfig/webresources/delete/sub_stats';
            $url = $serverDetails['base_url'] . $endpoint;

            // Construct the XML body
            $xml = $this->generateDeleteXml($statsData);

            $response = Http::withHeaders([
                'Authorization' => $authorization,
                'Content-Type' => 'application/xml',
            ])->withoutVerifying()->send('DELETE', $url, [
                'body' => $xml
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Stats deleted successfully.',
                ];
            } else {
                // Handle unsuccessful responses
                $status = $response->status();
                $errorMessage = $response->body() ?: 'Unknown error occurred';

                throw new \Exception("Failed to delete stats. HTTP Status: $status, Error: $errorMessage");
            }

            return [
                'error' => true,
                'message' => $response->body(),
            ];
        } catch (\Exception $e) {
            // Log the error and rethrow the exception
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            throw new \Exception('Error while deleting stats: ' . $e->getMessage());
        }
    }

    protected function generateDeleteXml($statsData)
    {
        $xml = new \SimpleXMLElement('<XBSubStatsList/>');

        foreach ($statsData as $stat) {
            $subStat = $xml->addChild('XBSubStats');

            if (isset($stat['username'])) {
                $subStat->addChild('username', $stat['username']);
            }
            if (isset($stat['trunkId'])) {
                $subStat->addChild('trunkId', $stat['trunkId']);
            }
            if (isset($stat['id'])) {
                $subStat->addChild('id', $stat['id']);
            }
            if (isset($stat['userDomain'])) {
                $subStat->addChild('userDomain', $stat['userDomain']);
            }
            if (isset($stat['userIp'])) {
                $subStat->addChild('userIp', $stat['userIp']);
            }
        }

        return $xml->asXML();
    }
}
