<?php

namespace App\Services;

use ESLconnection;
use App\Models\Settings;
use App\Models\SipProfiles;

class FreeswitchEslService
{
    protected $conn;

    public function __construct()
    {
        // Check if the 'esl' extension is loaded
        if (!extension_loaded('esl')) {
            throw new \Exception("Freeswitch PHP ESL module is not loaded. Contact the administrator.");
        }

        // Get event socket credentials
        $settings = Settings::first();

        if (!$settings) {
            throw new \Exception("Event socket settings are not configured.");
        }

        // Create the event socket connection
        $this->conn = new ESLconnection(
            $settings->event_socket_ip_address,
            $settings->event_socket_port,
            $settings->event_socket_password
        );

        if (!$this->conn->connected()) {
            throw new \Exception("Failed to connect to FreeSWITCH event socket.");
        }
    }

    public function executeCommand($cmd)
    {
        try {
            // Send the command and get the response in ESLevent Format
            $eslEvent = $this->conn->api($cmd);

            if (!$eslEvent) {
                return null;
            }

            // Check for errors in the response
            $this->handleResponseErrors($eslEvent);

            // Convert response to XML
            return $this->convertEslResponse($eslEvent);
        } finally {
            // Ensure the connection is always disconnected
            $this->disconnect();
        }
    }

    public function disconnect()
    {
        if ($this->conn) {
            $this->conn->disconnect();
        }
    }

    function getAllSipRegistrations()
    {
        // Check if the 'esl' extension is loaded
        if (!extension_loaded('esl')) {
            throw new \Exception("Freeswitch PHP ESL module is not loaded. Contact adminstrator");
        }

        // Get event socket credentials
        $settings = Settings::first();

        // //create the event socket connection
        // $conn = new \ESLconnection(
        //     $settings->event_socket_ip_address,
        //     $settings->event_socket_port,
        //     $settings->event_socket_password,
        // );

        // Get all system sip profiles
        $sip_profiles = SipProfiles::where('sip_profile_enabled', 'true')
            ->get();

        foreach ($sip_profiles as $sip_profile) {
            $cmd = "sofia xmlstatus profile '" . $sip_profile['sip_profile_name'] . "' reg";
            $xml = $this->executeCommand($cmd);


            // $xml = convertEslResponseToXml($response);

            if ($xml) {
                foreach ($xml->registrations->registration as $registration) {
                    $contact = (string)$registration->contact;
                    $contactData = [];

                    // Example of using regular expressions to extract information
                    if (preg_match('/sip:([^@]+)@([^;]+);transport=([^;]+);/', $contact, $matches)) {
                        $contactData['user'] = $matches[1];
                        $contactData['ip_with_port'] = $matches[2];
                        $contactData['transport'] = $matches[3];

                        // Further splitting to separate IP and port if needed
                        $ipPort = explode(':', $contactData['ip_with_port']);
                        $contactData['ip'] = $ipPort[0];
                        $contactData['port'] = $ipPort[1] ?? null; // Check if port is present
                    }

                    // Extracting the WAN IP from fs_path
                    if (preg_match('/fs_path=sip%3A([^;]+)/', $contact, $fsPathMatches)) {
                        $decodedFsPath = urldecode($fsPathMatches[1]);
                        // Extract the IP from the decoded string
                        if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $decodedFsPath, $ipMatches)) {
                            $contactData['wan_ip'] = $ipMatches[1];
                        }
                    }

                    $registrations[] = [
                        'user' => (string)$registration->user,
                        'status' => (string)$registration->status,
                        'lan_ip' => $contactData['ip'] ?? '',
                        'port' => $contactData['port'] ?? '',
                        'contact' => $contact,
                        'agent' => (string)$registration->agent,
                        'transport' => $contactData['transport'] ?? '',
                        'wan_ip' => $contactData['wan_ip'] ?? '',
                        'sip_profile_name' => $sip_profile['sip_profile_name'],
                        'sip_auth_user' => (string)$registration->{'sip-auth-user'}, // Add this line
                        'sip_auth_realm' => (string)$registration->{'sip-auth-realm'}, 
                        'ping_time' => (string)$registration->{'ping-time'}, 
                    ];
                }
                // logger($registrations);
            }
        }

        if (sizeof($registrations) > 0) {
            // Return collection of all regisrations
            return collect($registrations);
        } else {
            return null;
        }
    }


    function convertEslResponse($eslEvent)
    {
        $response = trim($eslEvent->getBody());

        // Check for '+OK Job-UUID' pattern and extract the Job-UUID
        if (preg_match('/^\+OK Job-UUID: ([a-f0-9-]+)$/i', $response, $matches)) {
            return ['job_uuid' => $matches[1]];
        }

        if ($response === '+OK') {
            return null;
        }

        if ($response) {
            // Check if the response contains CSV-like data
            if (strpos($response, '|') !== false) {
                return $this->convertEslResponseToArray($response);
            } else {
                // Attempt to parse as XML
                return $this->convertEslResponseToXml($response);
            }
        }

        throw new \Exception("Invalid response format.");
    }

    private function convertEslResponseToXml($responseBody)
    {
        $xml = simplexml_load_string($responseBody);

        if ($xml === false) {
            logger('false');
        }

        if ($xml === false) {
            throw new \Exception("Failed to parse ESL response as XML.");
        }
        return $xml;
    }

    private function convertEslResponseToArray($responseBody)
    {
        $lines = explode("\n", trim($responseBody));
        $headers = explode('|', array_shift($lines));
        $data = [];

        foreach ($lines as $line) {
            if (strpos($line, '+OK') === 0) {
                continue;
            }

            $row = explode('|', $line);
            $data[] = array_combine($headers, $row);
        }

        return $data;
    }

    private function handleResponseErrors($eslEvent)
    {
        if (strpos($eslEvent->getBody(), '-ERR') !== false) {
            throw new \Exception("ESL API Error: " . $eslEvent->getBody());
        }
    }
}
