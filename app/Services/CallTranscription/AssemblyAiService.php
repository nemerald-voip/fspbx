<?php

namespace App\Services\CallTranscription;

use Illuminate\Support\Facades\Http;
use App\Services\Interfaces\TranscriptionProviderInterface;

class AssemblyAiService implements TranscriptionProviderInterface
{
    public array $payload;

    public function __construct(
        private array $conn,    // ['api_key','region'|'base_url','timeout']
        private array $options,  // provider config JSON already mapped to AAI options (raw)
    ) {}

    // ---- public API ----
    public function transcribe(string $audioUrl, array $options = []): array
    {
        $this->payload = ['audio_url' => $audioUrl] + $this->pruneNulls($this->merge($this->options, $options));

        // add webhook info
        $this->payload['webhook_url']              = route('webhook-client-assemblyai'); // public URL
        $this->payload['webhook_auth_header_name'] = config('services.assemblyai.webhook_header_name');
        $this->payload['webhook_auth_header_value'] = config('services.assemblyai.webhook_header_value');

        $res = $this->http()->post('v2/transcript', $this->payload)->throw();

        return $res->json() ?? [];
    }

    public function fetchTranscript(string $transcriptId): array
    {
        $res = $this->http()->get("v2/transcript/{$transcriptId}")->throw();
        return $res->json() ?? [];
    }

    // ---- internals ----
    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        $baseUrl = rtrim($this->resolveBaseUrl(), '/') . '/';
        $timeout = (int) ($this->conn['timeout'] ?? 30);
        $apiKey  = (string) ($this->conn['api_key'] ?? '');

        // Retry on 429 + 5xx + connection errors, small backoff
        return Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->withHeaders([
                // AssemblyAI expects raw API key in Authorization (not Bearer)
                'Authorization' => $apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])
            ->retry(
                3,
                250, // ms
                function ($exception, $request) {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }
                    // Some Laravel versions pass an exception with a response() method
                    $response = method_exists($exception, 'response') ? $exception->response() : null;
                    $status   = $response?->status();

                    return in_array($status, [429, 500, 502, 503, 504], true);
                },
                throw: true
            );
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
                // keep empty arrays (AAI tolerates them), but drop empty sub-arrays if you prefer:
                // if ($a[$k] === []) unset($a[$k]);
            } elseif ($v === null) {
                unset($a[$k]);
            }
        }
        return $a;
    }
}
