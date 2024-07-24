<?php

namespace App\Services;

use App\Models\Settings;
use ESLconnection;

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


    function convertEslResponse($eslEvent)
    {
        $response = trim($eslEvent->getBody());

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
