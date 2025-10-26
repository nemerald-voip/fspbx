<?php
namespace App\Services\CallTranscription;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use App\Services\Interfaces\TranscriptionProviderInterface;

class AssemblyAiService implements TranscriptionProviderInterface
{
    public function __construct(
        private array $conn,    // ['api_key','region'|'base_url','timeout']
        private array $options  // provider config JSON already mapped to AAI options (raw)
    ) {}

    // ---- public API ----
    public function transcribe(string $audioUrl, array $options = []): array
    {
        $payload = $this->pruneNulls($this->merge($this->options, $options));
        $res = $this->request('POST', 'v2/transcripts', ['json' => ['audio_url' => $audioUrl] + $payload]);
        return json_decode((string) $res->getBody(), true) ?: [];
    }

    public function fetchTranscript(string $transcriptId): array
    {
        $res = $this->request('GET', "v2/transcripts/{$transcriptId}");
        return json_decode((string) $res->getBody(), true) ?: [];
    }

    public function fetchParagraphs(string $transcriptId, array $query = []): array
    {
        $res = $this->request('GET', "v2/transcripts/{$transcriptId}/paragraphs", ['query' => $query]);
        return json_decode((string) $res->getBody(), true) ?: [];
    }

    public function fetchSentences(string $transcriptId, array $query = []): array
    {
        $res = $this->request('GET', "v2/transcripts/{$transcriptId}/sentences", ['query' => $query]);
        return json_decode((string) $res->getBody(), true) ?: [];
    }

    public function fetchSubtitles(string $transcriptId, string $format = 'srt'): string
    {
        $res = $this->request('GET', "v2/transcripts/{$transcriptId}/subtitles", [
            'query' => ['format' => $format],
            'headers' => ['Accept' => 'text/plain, application/octet-stream, */*'],
        ]);
        return (string) $res->getBody();
    }

    // ---- internals ----
    private function http(): Client
    {
        return new Client([
            'base_uri' => rtrim($this->resolveBaseUrl(), '/') . '/',
            'timeout'  => (int) ($this->conn['timeout'] ?? 30),
            'headers'  => [
                'Authorization' => (string) ($this->conn['api_key'] ?? ''),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ]);
    }

    private function request(string $method, string $uri, array $options = [], int $retries = 2)
    {
        $http = $this->http();
        $attempt = 0;
        start:
        try {
            return $http->request($method, $uri, $options);
        } catch (RequestException $e) {
            $code = $e->getResponse()?->getStatusCode();
            if ($attempt < $retries && in_array($code, [429,500,502,503,504], true)) {
                $attempt++;
                usleep((int) (200000 * $attempt + random_int(0, 100000))); // backoff + jitter
                goto start;
            }
            throw $e;
        }
    }

    private function resolveBaseUrl(): string
    {
        $base = trim((string) ($this->conn['base_url'] ?? ''));
        if ($base !== '') return $base;

        $region = strtoupper((string) ($this->conn['region'] ?? 'US'));
        return $region === 'EU'
            ? 'https://api.eu.assemblyai.com'
            : 'https://api.assemblyai.com';
    }

    private function merge(array $a, array $b): array
    {
        foreach ($b as $k => $v) {
            if (is_array($v) && isset($a[$k]) && is_array($a[$k])) {
                $a[$k] = $this->merge($a[$k], $v);
            } else {
                $a[$k] = $v;
            }
        }
        return $a;
    }

    private function pruneNulls(array $a): array
    {
        foreach ($a as $k => $v) {
            if (is_array($v)) {
                $a[$k] = $this->pruneNulls($v);
            } elseif ($v === null) {
                unset($a[$k]);
            }
        }
        return $a;
    }
}
