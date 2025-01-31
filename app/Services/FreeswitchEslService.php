<?php

namespace App\Services;

use Throwable;
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

    public function executeCommand($cmd, $disconnect = true)
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
        } catch (Throwable $e) {
            logger('error executing ESL command');
        } finally {
            // Disconnect only if the flag is set to true
            if ($disconnect) {
                $this->disconnect();
            }
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
            throw new \Exception("Freeswitch PHP ESL module is not loaded. Contact administrator");
        }

        // Get all system sip profiles
        $sip_profiles = SipProfiles::where('sip_profile_enabled', 'true')
            ->get(
                [
                    'sip_profile_uuid',
                    'sip_profile_name',
                ]
            );

        $registrations = [];

        foreach ($sip_profiles as $sip_profile) {
            $cmd = "sofia xmlstatus profile '" . $sip_profile['sip_profile_name'] . "' reg";
            $xml = $this->executeCommand($cmd, $disconnect = false); // Do not disconnect after each command

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

                    // Remove expiration date from status
                    $status = (string)$registration->status;
                    // Extract expsecs value if present
                    $expsecs = null;
                    if (preg_match('/expsecs\((\d+)\)/', $status, $expsecsMatches)) {
                        $expsecs = (int)$expsecsMatches[1]; // Capture expsecs value
                    }
                    // Remove expsecs(), exp() part and (unknown) from the status
                    $status = preg_replace([
                        '/expsecs\(\d+\)/', // Remove expsecs()
                        '/exp\(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\)/', // Remove expiration date
                        '/\(unknown\)/' // Remove (unknown)
                    ], '', $status);



                    // Trim any extra spaces and parentheses that may remain
                    $status = trim($status);

                    $registrations[] = [
                        'call_id' => (string)$registration->{'call-id'},
                        'user' => (string)$registration->user,
                        'status' => (string)$status,
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
                        'expsecs' => (string)$expsecs,
                    ];
                }
                // logger($registrations);
            }
        }

        return collect($registrations);
    }

    function getAllChannels()
    {
        // Check if the 'esl' extension is loaded
        if (!extension_loaded('esl')) {
            throw new \Exception("Freeswitch PHP ESL module is not loaded. Contact administrator");
        }

        $cmd = "show channels as json";
        // $cmd = "show channels";
        $result = $this->executeCommand($cmd);

        // Initialize an array to hold channel information
        $channels = [];

        // If we received a valid response, convert the result to an array of channels
        if ($result && !empty($result['rows'])) {
            foreach ($result['rows'] as $channel) {
                // Collect channel data for each active channel 
                $channels[] = [
                    'uuid'               => $channel['uuid'] ?? '',
                    'direction'          => $channel['direction'] ?? '',
                    'created'            => $channel['created'] ?? '',
                    'created_epoch'      => $channel['created_epoch'] ?? '',
                    'name'               => $channel['name'] ?? '',
                    'state'              => $channel['state'] ?? '',
                    'cid_name'           => $channel['cid_name'] ?? '',
                    'cid_num'            => $channel['cid_num'] ?? '',
                    'ip_addr'            => $channel['ip_addr'] ?? '',
                    'dest'               => $channel['dest'] ?? '',
                    'application'        => $channel['application'] ?? '',
                    'application_data'   => $channel['application_data'] ?? '',
                    'dialplan'           => $channel['dialplan'] ?? '',
                    'context'            => $channel['context'] ?? '',
                    'read_codec'         => $channel['read_codec'] ?? '',
                    'read_rate'          => $channel['read_rate'] ?? '',
                    'read_bit_rate'      => $channel['read_bit_rate'] ?? '',
                    'write_codec'        => $channel['write_codec'] ?? '',
                    'write_rate'         => $channel['write_rate'] ?? '',
                    'write_bit_rate'     => $channel['write_bit_rate'] ?? '',
                    'secure'             => $channel['secure'] ?? '',
                    'hostname'           => $channel['hostname'] ?? '',
                    'presence_id'        => $channel['presence_id'] ?? '',
                    'presence_data'      => $channel['presence_data'] ?? '',
                    'accountcode'        => $channel['accountcode'] ?? '',
                    'callstate'          => $channel['callstate'] ?? '',
                    'callee_name'        => $channel['callee_name'] ?? '',
                    'callee_num'         => $channel['callee_num'] ?? '',
                    'callee_direction'   => $channel['callee_direction'] ?? '',
                    'call_uuid'          => $channel['call_uuid'] ?? '',
                    'sent_callee_name'   => $channel['sent_callee_name'] ?? '',
                    'sent_callee_num'    => $channel['sent_callee_num'] ?? '',
                    'initial_cid_name'   => $channel['initial_cid_name'] ?? '',
                    'initial_cid_num'    => $channel['initial_cid_num'] ?? '',
                    'initial_ip_addr'    => $channel['initial_ip_addr'] ?? '',
                    'initial_dest'       => $channel['initial_dest'] ?? '',
                    'initial_dialplan'   => $channel['initial_dialplan'] ?? '',
                    'initial_context'    => $channel['initial_context'] ?? '',
                ];
            }
        }


        return collect($channels);
    }

    function killChannel($uuid)
    {
        // Check if the 'esl' extension is loaded
        if (!extension_loaded('esl')) {
            throw new \Exception("Freeswitch PHP ESL module is not loaded. Contact administrator");
        }

        $cmd = "uuid_kill " . $uuid;
        $result = $this->executeCommand($cmd);

        return $result;
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
            // Check if the response is a valid JSON string
            if ($this->isValidJson($response)) {
                return json_decode($response, true); // Decode JSON response as an associative array
            }

            // Check if the response contains CSV-like data
            if (strpos($response, '|') !== false) {
                return $this->convertEslResponseToArray($response);
            }

            // Assume XML otherwise
            return $this->convertEslResponseToXml($response);
        }

        throw new \Exception("Invalid response format.");
    }

    private function convertEslResponseToXml($responseBody)
    {
        // logger($responseBody);
        try {
            $xml = simplexml_load_string($responseBody);

            if ($xml === false) {
                return null;
            }

            return $xml;
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return null;
        }
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

    // Helper function to check if a string is a valid JSON
    function isValidJson($string)
    {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }
}
