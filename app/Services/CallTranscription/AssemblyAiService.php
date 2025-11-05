<?php

namespace App\Services\CallTranscription;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
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

    /**
     * Summarize a call using AssemblyAI's LLM Gateway.
     * @param array $utterancesLines e.g. ["A: Hello", "B: Hi", ...]
     * @return array Decoded JSON that follows our summary schema
     * @throws \RuntimeException
     */
    public function requestCallSummary(array $utterancesLines): array
    {
        $model = config('services.assemblyai.llm_model', 'gpt-5-nano');
    
        $systemText = implode("\n", [
            'You are a precise call-summary assistant for a VoIP platform.',
            'Transform call transcripts into a concise summary and structured insights.',
            'Rules:',
            '- Use only information present; do not guess or invent.',
            '- Attribute statements correctly when relevant.',
            '- Prefer plain, clear business language.',
            '- If a field is unknown, use null.',
            '- If you can guess the participants name, use that name in your responses.',
            '- If the name is unknown, use the guessed role instead (e.g., "Agent", "Customer").',
            '- Return ONLY valid JSON matching the schema below—no prose.',
            '',
            'Output JSON schema:',
            '{',
            '  "summary": "string (2-4 sentences)",',
            '  "participants": [',
            '    {"label": "A|B|C...", "role_guess": "agent|customer|other|null", "name_guess": "string|null"}',
            '  ],',
            '  "key_points": ["string"],',
            '  "decisions_made": ["string"],',
            '  "action_items": [',
            '    {"owner": "name_guess|role_guess|name|null", "description": "string", "due": "ISO-8601 date or null"}',
            '  ],',
            '  "follow_up_risks": ["string"],',
            '  "sentiment_overall": "positive|neutral|negative|null",',
            '  "compliance_flags": ["string"],',
            '  "next_best_step": "string",',
            '  "confidence": 0.0',
            '}',
        ]);
    
        $userText = implode("\n", array_merge(
            [
                'Using the utterances below (speaker-labeled, no timestamps), produce the Output JSON.',
                'Return ONLY the JSON object (no markdown, no commentary).',
                '',
                'Utterances:'
            ],
            $utterancesLines
        ));
    
        $payload = [
            'model' => $model,
            'temperature' => 0.2,
            'messages' => [
                [ 'role' => 'system', 'content' => $systemText ],
                [ 'role' => 'user',   'content' => $userText   ],
            ],
        ];
    
        $resp = $this->httpLlm()->post('v1/chat/completions', $payload)->throw();
    
        $content = data_get($resp->json(), 'choices.0.message.content', '{}');
        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON from LLM: ' . json_last_error_msg());
        }
    
        return $decoded;
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

    /**
     * HTTP client for AssemblyAI LLM Gateway (Bearer token; different base URL).
     */
    private function httpLlm(): \Illuminate\Http\Client\PendingRequest
    {
        $timeout = (int) ($this->conn['timeout'] ?? 3);
        logger($timeout);
        $apiKey  = (string) ($this->conn['api_key'] ?? '');
        $llmBase   = rtrim((string) config('services.assemblyai.llm_gateway_base', 'https://llm-gateway.assemblyai.com'), '/') . '/';

        return Http::baseUrl($llmBase)
            ->timeout($timeout)
            ->withHeaders([
                // LLM Gateway expects Bearer
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])
            ->retry(
                3,
                250,
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
