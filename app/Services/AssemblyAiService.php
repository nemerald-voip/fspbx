<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class AssemblyAiService
{
    /** Build an HTTP client from dynamic cfg */
    private function makeHttp(string $baseUrl, string $apiKey, int $timeout): Client
    {
        return new Client([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'timeout'  => $timeout,
            'headers'  => [
                'Authorization' => $apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ]);
    }

    /** Basic retry/backoff for 429/5xx */
    private function request(Client $http, string $method, string $uri, array $options = [], int $retries = 2)
    {
        $attempt = 0;
        start:
        try {
            return $http->request($method, $uri, $options);
        } catch (RequestException $e) {
            $code = $e->getResponse()?->getStatusCode();
            if ($attempt < $retries && in_array($code, [429, 500, 502, 503, 504], true)) {
                $attempt++;
                // exp backoff with jitter
                usleep((int) (200000 * $attempt + random_int(0, 100000)));
                goto start;
            }
            throw $e;
        }
    }

    /** Create transcript */
    public function createTranscript(array $conn, string $audioUrl, array $options = []): array
    {
        $http = $this->makeHttp(
            baseUrl: $this->resolveBaseUrl($conn),
            apiKey:  (string) ($conn['api_key'] ?? ''),
            timeout: (int)    ($conn['timeout'] ?? 30),
        );

        $payload = array_merge(['audio_url' => $audioUrl], $options);

        $res = $this->request($http, 'POST', 'v2/transcripts', ['json' => $payload]);
        return json_decode((string) $res->getBody(), true) ?: [];
    }

    /** Get transcript */
    public function getTranscript(array $conn, string $transcriptId): array
    {
        $http = $this->makeHttp(
            baseUrl: $this->resolveBaseUrl($conn),
            apiKey:  (string) ($conn['api_key'] ?? ''),
            timeout: (int)    ($conn['timeout'] ?? 30),
        );

        $res = $this->request($http, 'GET', "v2/transcripts/{$transcriptId}");
        return json_decode((string) $res->getBody(), true) ?: [];
    }

    public function getParagraphs(array $conn, string $transcriptId, array $query = []): array
    {
        $http = $this->makeHttp(
            baseUrl: $this->resolveBaseUrl($conn),
            apiKey:  (string) ($conn['api_key'] ?? ''),
            timeout: (int)    ($conn['timeout'] ?? 30),
        );

        $res = $this->request($http, 'GET', "v2/transcripts/{$transcriptId}/paragraphs", ['query' => $query]);
        return json_decode((string) $res->getBody(), true) ?: [];
    }

    public function getSentences(array $conn, string $transcriptId, array $query = []): array
    {
        $http = $this->makeHttp(
            baseUrl: $this->resolveBaseUrl($conn),
            apiKey:  (string) ($conn['api_key'] ?? ''),
            timeout: (int)    ($conn['timeout'] ?? 30),
        );

        $res = $this->request($http, 'GET', "v2/transcripts/{$transcriptId}/sentences", ['query' => $query]);
        return json_decode((string) $res->getBody(), true) ?: [];
    }

    public function getSubtitles(array $conn, string $transcriptId, string $format = 'srt'): string
    {
        $http = $this->makeHttp(
            baseUrl: $this->resolveBaseUrl($conn),
            apiKey:  (string) ($conn['api_key'] ?? ''),
            timeout: (int)    ($conn['timeout'] ?? 30),
        );

        $res = $this->request($http, 'GET', "v2/transcripts/{$transcriptId}/subtitles", [
            'query'   => ['format' => $format],
            'headers' => ['Accept' => 'text/plain, application/octet-stream, */*'],
        ]);

        return (string) $res->getBody();
    }

    /** Resolve base URL from region or explicit base_url in config */
    private function resolveBaseUrl(array $conn): string
    {
        $base = trim((string) ($conn['base_url'] ?? ''));
        if ($base !== '') return $base;

        $region = strtoupper((string) ($conn['region'] ?? 'US'));
        return $region === 'EU'
            ? 'https://api.eu.assemblyai.com'
            : 'https://api.assemblyai.com';
    }
}
