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

    public function fetchStats(array $params)
    {
        try {
            if (!isset($params['server'])) {
                throw new \Exception('Server parameter is required');
            }
    
            $serverDetails = $this->servers[$params['server']];

            $authorization = 'Basic ' . base64_encode($serverDetails['user'] . ':' . $serverDetails['api_key']);


            // Define the endpoint and parameters
            $endpoint = '/SSConfig/webresources/stats/sub_stats';
            $queryParams = ['format' => 'json'];

            // Build the URL by combining base_url and endpoint
            $url = $serverDetails['base_url'] . $endpoint;

            $response = Http::withHeaders([
                'Authorization' => $authorization,
            ])->withoutVerifying()->get($url, $queryParams);

            // Check for successful response
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['XBSubStatsList']) && isset($data['XBSubStatsList']['XBSubStats'])) {
                    $collection = collect($data['XBSubStatsList']['XBSubStats']);

                    // Apply additional filters from the array
                    if (!empty($params['userDomain'])) {
                        $collection = $collection->where('userDomain', $params['userDomain']);
                    }
    
                    if (!empty($params['username'])) {
                        $collection = $collection->where('username', $params['username']);
                    }
    
                    return $collection->values(); // Ensure re-indexing of the collection
                } else {
                    return collect();
                }
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
            $xml = $this->generateDeleteStatsXml($statsData);

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

    protected function generateDeleteStatsXml($statsData)
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

    public function fetchActiveCalls($server)
    {
        try {
            $serverDetails = $this->servers[$server];
            $authorization = 'Basic ' . base64_encode($serverDetails['user'] . ':' . $serverDetails['api_key']);

            // Define the endpoint
            $endpoint = '/SSConfig/webresources/stats/active_calls';
            $url = $serverDetails['base_url'] . $endpoint;
            $params = ['format' => 'json'];

            $response = Http::withHeaders([
                'Authorization' => $authorization,
            ])->withoutVerifying()->get($url, $params);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['XBActiveCallsList']) && isset($data['XBActiveCallsList']['XBActiveCall'])) {
                    return collect($data['XBActiveCallsList']['XBActiveCall']);
                } else {
                    return collect();
                }
                
            } else {
                $status = $response->status();
                $errorMessage = $response->body() ?: 'Unknown error occurred';
                throw new \Exception("Failed to download active calls. HTTP Status: $status, Error: $errorMessage");
            }
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            throw new \Exception('Error while downloading active calls: ' . $e->getMessage());
        }
    }

    public function deleteActiveCalls($server, $callData)
    {
        try {
            $serverDetails = $this->servers[$server];
            $authorization = 'Basic ' . base64_encode($serverDetails['user'] . ':' . $serverDetails['api_key']);

            // Define the endpoint
            $endpoint = '/SSConfig/webresources/delete/active_calls';
            $url = $serverDetails['base_url'] . $endpoint;

            // Construct the XML body
            $xml = $this->generateDeleteActiveCallsXml($callData);

            logger($xml);

            $response = Http::withHeaders([
                'Authorization' => $authorization,
                'Content-Type' => 'application/xml',
            ])->withoutVerifying()->send('DELETE', $url, [
                'body' => $xml
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Active calls deleted successfully.',
                ];
            } else {
                $status = $response->status();
                $errorMessage = $response->body() ?: 'Unknown error occurred';
                throw new \Exception("Failed to delete active calls. HTTP Status: $status, Error: $errorMessage");
            }
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            throw new \Exception('Error while deleting active calls: ' . $e->getMessage());
        }
    }

    protected function generateDeleteActiveCallsXml($callData)
    {
        $xml = new \SimpleXMLElement('<XBActiveCallsList/>');

        foreach ($callData as $call) {
            $activeCall = $xml->addChild('XBActiveCall');

            if (isset($call['ssm_index'])) {
                $activeCall->addChild('ssm_index', $call['ssm_index']);
            }
            if (isset($call['orig_tid'])) {
                $activeCall->addChild('orig_tid', $call['orig_tid']);
            }
            if (isset($call['term_tid'])) {
                $activeCall->addChild('term_tid', $call['term_tid']);
            }
            if (isset($call['dnis'])) {
                $activeCall->addChild('dnis', $call['dnis']);
            }
            if (isset($call['ani'])) {
                $activeCall->addChild('ani', $call['ani']);
            }
            if (isset($call['orig_ip'])) {
                $activeCall->addChild('orig_ip', $call['orig_ip']);
            }
            if (isset($call['term_ip'])) {
                $activeCall->addChild('term_ip', $call['term_ip']);
            }
            if (isset($call['inv_time'])) {
                $activeCall->addChild('inv_time', $call['inv_time']);
            }
            if (isset($call['ans_time'])) {
                $activeCall->addChild('ans_time', $call['ans_time']);
            }
            if (isset($call['duration'])) {
                $activeCall->addChild('duration', $call['duration']);
            }
            if (isset($call['callID'])) {
                $activeCall->addChild('callID', $call['callID']);
            }
        }

        return $xml->asXML();
    }
}
