<?php

namespace App\Services\Tts;

use RuntimeException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\ConnectionException;
use App\Services\Interfaces\TtsProviderInterface;

class ElevenLabsTtsService implements TtsProviderInterface
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey  = (string) config('services.elevenlabs.api_key', '');
        $this->baseUrl = rtrim((string) config('services.elevenlabs.base_url', 'https://api.elevenlabs.io'), '/');
        $this->timeout = (int) config('services.elevenlabs.timeout', 60);

        if ($this->apiKey === '') {
            throw new RuntimeException('ElevenLabs API key is not configured. Please set ELEVENLABS_API_KEY in your environment file.');
        }
    }

    public function textToSpeech(string $input, array $options = []): string
    {
        $voiceId = $options['voice'] ?? $this->getDefaultVoice();

        if (empty($voiceId)) {
            throw new RuntimeException('No ElevenLabs voice selected. Please choose a voice.');
        }

        $outputFormat = $this->mapOutputFormat($options['response_format'] ?? 'wav');

        $body = [
            'text'     => $input,
            'model_id' => 'eleven_multilingual_v2',
        ];

        // Optional voice settings
        if (isset($options['stability']) || isset($options['similarity_boost'])) {
            $body['voice_settings'] = array_filter([
                'stability'        => $options['stability'] ?? 0.5,
                'similarity_boost' => $options['similarity_boost'] ?? 0.75,
                'style'            => $options['style'] ?? 0,
                'use_speaker_boost' => $options['use_speaker_boost'] ?? true,
            ], fn($v) => $v !== null);
        }

        $response = $this->http()
            ->post("v1/text-to-speech/{$voiceId}?output_format={$outputFormat}", $body);

        if ($response->successful()) {
            return $response->body();
        }

        logger('ElevenLabs TTS error: ' . $response->body());
        throw new RuntimeException('ElevenLabs TTS failed: ' . ($response->json('detail.message') ?? $response->body()));
    }

    public function getVoices(): array
    {
        return Cache::remember('elevenlabs_voices', 3600, function () {
            $voices = [];
            $nextPageToken = null;

            do {
                $params = ['page_size' => 100];
                if ($nextPageToken) {
                    $params['next_page_token'] = $nextPageToken;
                }

                $response = $this->http()->get('v2/voices', $params);

                if (!$response->successful()) {
                    logger('ElevenLabs voices API error: ' . $response->body());
                    break;
                }

                $data = $response->json();

                foreach ($data['voices'] ?? [] as $voice) {
                    $voices[] = [
                        'value' => $voice['voice_id'],
                        'label' => $voice['name'] . ($voice['category'] ? ' (' . $voice['category'] . ')' : ''),
                    ];
                }

                $nextPageToken = $data['has_more'] ? ($data['next_page_token'] ?? null) : null;
            } while ($nextPageToken);

            return $voices;
        });
    }

    public function getDefaultVoice(): ?string
    {
        $setting = get_domain_setting('elevenlabs_default_voice');
        if ($setting) {
            return $setting;
        }

        // Fall back to first available voice
        $voices = $this->getVoices();
        return $voices[0]['value'] ?? null;
    }

    public function getSpeeds(): array
    {
        // ElevenLabs doesn't have a speed parameter in the same way as OpenAI
        return [];
    }

    public function getOutputFormats(): array
    {
        return [
            ['value' => 'wav', 'label' => 'WAV (16-bit PCM)'],
            ['value' => 'mp3', 'label' => 'MP3 (128kbps)'],
            ['value' => 'ulaw', 'label' => 'u-law 8kHz (telephony)'],
            ['value' => 'pcm', 'label' => 'PCM 16kHz'],
        ];
    }

    /**
     * Map generic format names to ElevenLabs format codes.
     */
    private function mapOutputFormat(string $format): string
    {
        return match ($format) {
            'wav'  => 'pcm_44100',
            'mp3'  => 'mp3_44100_128',
            'ulaw' => 'ulaw_8000',
            'pcm'  => 'pcm_16000',
            default => 'pcm_44100',
        };
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->baseUrl . '/')
            ->timeout($this->timeout)
            ->withHeaders([
                'xi-api-key'   => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])
            ->retry(
                3,
                500,
                function ($exception) {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }
                    $response = method_exists($exception, 'response') ? $exception->response() : null;
                    $status = $response?->status();
                    return in_array($status, [429, 500, 502, 503, 504], true);
                },
                throw: false
            );
    }
}
