<?php

namespace App\Services\CallTranscription;

use RuntimeException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use App\Services\Interfaces\TranscriptionProviderInterface;

class ElevenLabsSttService implements TranscriptionProviderInterface
{
    public array $payload;

    public function __construct(
        private array $conn,    // ['api_key', 'base_url', 'timeout']
        private array $options, // provider config from DB
    ) {}

    /**
     * Transcribe audio via ElevenLabs Speech-to-Text API.
     * This is synchronous — the full transcript is returned immediately.
     */
    public function transcribe(string $audioUrl, array $options = []): array
    {
        $merged = $this->merge($this->options, $options);
        $merged = $this->pruneNulls($merged);

        $this->payload = [
            'source_url' => $audioUrl,
            'model_id'   => $merged['model_id'] ?? 'scribe_v2',
        ];

        // Map optional fields
        $optionalFields = [
            'diarize', 'timestamps_granularity', 'language_code',
            'tag_audio_events', 'num_speakers',
        ];

        foreach ($optionalFields as $field) {
            if (isset($merged[$field])) {
                $this->payload[$field] = $merged[$field];
            }
        }

        // Keyterms: ElevenLabs expects an array of strings
        if (!empty($merged['keyterms'])) {
            $this->payload['keyterms'] = is_array($merged['keyterms'])
                ? $merged['keyterms']
                : array_map('trim', explode(',', $merged['keyterms']));
        }

        $res = $this->http()
            ->asMultipart()
            ->post('v1/speech-to-text', $this->buildMultipartPayload())
            ->throw();

        $json = $res->json() ?? [];

        // ElevenLabs returns the full result synchronously.
        // Mark it as completed for the job handler.
        $json['status'] = 'completed';
        $json['id'] = $json['language_code'] ?? 'elevenlabs-' . now()->timestamp;

        return $json;
    }

    /**
     * ElevenLabs STT is synchronous — no transcript polling is needed.
     */
    public function fetchTranscript(string $transcriptId): array
    {
        throw new RuntimeException('ElevenLabs STT is synchronous; fetchTranscript is not supported.');
    }

    // ---- internals ----

    private function buildMultipartPayload(): array
    {
        $parts = [];
        foreach ($this->payload as $key => $value) {
            if (is_array($value)) {
                // Send arrays as JSON-encoded strings
                $parts[] = ['name' => $key, 'contents' => json_encode($value)];
            } elseif (is_bool($value)) {
                $parts[] = ['name' => $key, 'contents' => $value ? 'true' : 'false'];
            } else {
                $parts[] = ['name' => $key, 'contents' => (string) $value];
            }
        }
        return $parts;
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        $baseUrl = rtrim($this->conn['base_url'] ?? 'https://api.elevenlabs.io', '/') . '/';
        $timeout = (int) ($this->conn['timeout'] ?? 60);
        $apiKey  = (string) ($this->conn['api_key'] ?? '');

        return Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->withHeaders([
                'xi-api-key' => $apiKey,
                'Accept'     => 'application/json',
            ])
            ->retry(
                3,
                500,
                function ($exception) {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }
                    $response = method_exists($exception, 'response') ? $exception->response() : null;
                    $status   = $response?->status();
                    return in_array($status, [429, 500, 502, 503, 504], true);
                },
                throw: true
            );
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
