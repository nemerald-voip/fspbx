<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpenAIService
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
    }

    public function textToSpeech($model = 'gpt-4o-mini-tts-2025-12-15', $input, $voice = 'alloy', $response_format = 'wav', $speed = '1.0')
    {
        if (empty($this->apiKey)) {
            throw new \Exception('OpenAI API key is not configured. Please set the API key in your environment file.');
        }

        $url = 'https://api.openai.com/v1/audio/speech';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'model' => $model,
            'input' => $input,
            'voice' => $voice,
            'response_format' => $response_format,
            'speed' => (float) $speed,
        ]);

        return $this->handleResponse($response);
    }

    private function handleResponse($response)
    {
        if ($response->successful()) {
            return $response->body();
        }

        if ($response->clientError()) {
            // Log client errors
            logger('OpenAI API Client Error: ' . $response->body());
            throw new \Exception('There was an error with your request: ' . $response->json('error.message'));
        }

        if ($response->serverError()) {
            // Log server errors
            logger('OpenAI API Server Error: ' . $response->body());
            throw new \Exception('The OpenAI API is currently unavailable. Please try again later.');
        }

        // Handle unexpected errors
        throw new \Exception('An unexpected error occurred. Please try again.');
    }

    public function getVoices()
    {
        return [
            ['value' => 'alloy', 'label' => 'Alloy'],
            ['value' => 'ash', 'label' => 'Ash'],
            ['value' => 'ballad', 'label' => 'Ballad'],
            ['value' => 'coral', 'label' => 'Coral'],
            ['value' => 'echo', 'label' => 'Echo'],
            ['value' => 'fable', 'label' => 'Fable'],
            ['value' => 'onyx', 'label' => 'Onyx'],
            ['value' => 'nova', 'label' => 'Nova'],
            ['value' => 'sage', 'label' => 'Sage'],
            ['value' => 'shimmer', 'label' => 'Shimmer'],
            ['value' => 'verse', 'label' => 'Verse'],
            ['value' => 'marin', 'label' => 'Marin'],
            ['value' => 'cedar', 'label' => 'Cedar'],
        ];
    }

    public function getDefaultVoice()
    {
        return get_domain_setting('openai_default_voice');
    }

    public function getSpeeds()
    {
        $openAiSpeeds = [];

        for ($i = 0.85; $i <= 1.3; $i += 0.05) {
            // Format all with two decimals, or stick with your logic if needed
            $formattedValue = number_format($i, 2, '.', '');
            $openAiSpeeds[] = [
                'value' => $formattedValue,
                'label' => $formattedValue
            ];
        }

        return $openAiSpeeds;
    }

    public function transcribeAudio($filePath, $model = 'whisper-1', $language = null)
    {
        if (empty($this->apiKey)) {
            throw new \Exception('OpenAI API key is not configured. Please set the API key in your environment file.');
        }

        $url = 'https://api.openai.com/v1/audio/transcriptions';

        $params = [
            'model' => $model,
        ];
        if ($language) {
            $params['language'] = $language;
        }

        $response = Http::withToken($this->apiKey)
            ->attach('file', fopen($filePath, 'r'), basename($filePath))
            ->post($url, $params);

        if ($response->successful()) {
            return [
                'message' => $response->json('text')
            ];
        } else {
            logger()->error('OpenAI transcription failed: ' . $response->body());
            return null;
        }
    }

    /**
     * Kick off a background Responses task with your exact system/user prompt and utterances.
     * Returns ["id" => "resp_...","status" => "queued|in_progress|..."].
     */
    public function createBackgroundSummary(array $utteranceLines, string $model = 'gpt-4.1-mini'): array
    {
        $url = 'https://api.openai.com/v1/responses';

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
                'Utterances:',
            ],
            $utteranceLines
        ));

        $payload = [
            'model'        => $model,
            'background'   => true,
            'instructions' => $systemText,
            'input'        => $userText,
            'store' => false,
        ];

        $resp = Http::withToken($this->apiKey)
            ->acceptJson()
            ->post($url, $payload)
            ->throw()
            ->json();

        return [
            'id'     => data_get($resp, 'id'),
            'status' => data_get($resp, 'status'),
        ];
    }


    /**
     * Retrieve a background response by id.
     * Returns the raw JSON and a convenient tuple of [status, outputText].
     */
    public function retrieveResponseById(string $responseId): array
    {
        $url = 'https://api.openai.com/v1/responses';

        $resp = Http::withToken($this->apiKey)
            ->acceptJson()
            ->get($url . '/' . $responseId)
            ->throw()
            ->json();

        // Prefer the top-level output_text when present
        $text = (string) data_get($resp, 'output_text', '');

        if ($text === '') {
            // Fallback 1: first assistant message text
            $text = (string) data_get($resp, 'output.0.content.0.text', '');
            if ($text === '') {
                // Fallback 2: scan for any message item with output_text
                foreach ((array) data_get($resp, 'output', []) as $item) {
                    if (($item['type'] ?? null) === 'message') {
                        $candidate = (string) data_get($item, 'content.0.text', '');
                        if ($candidate !== '') {
                            $text = $candidate;
                            break;
                        }
                    }
                }
            }
        }

        return [
            'raw'    => $resp,
            'status' => data_get($resp, 'status'),
            'text'   => $text, // may be '', caller should handle
        ];
    }
}
