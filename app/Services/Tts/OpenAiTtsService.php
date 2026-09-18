<?php

namespace App\Services\Tts;

use App\Services\OpenAIService;
use App\Services\Interfaces\TtsProviderInterface;

class OpenAiTtsService implements TtsProviderInterface
{
    private OpenAIService $openAi;

    public function __construct()
    {
        $this->openAi = app(OpenAIService::class);
    }

    public function textToSpeech(string $input, array $options = []): string
    {
        $model  = $options['model'] ?? 'gpt-4o-mini-tts-2025-12-15';
        $voice  = $options['voice'] ?? 'alloy';
        $format = $options['response_format'] ?? 'wav';
        $speed  = $options['speed'] ?? '1.0';

        return $this->openAi->textToSpeech($model, $input, $voice, $format, $speed);
    }

    public function getVoices(): array
    {
        return $this->openAi->getVoices();
    }

    public function getDefaultVoice(): ?string
    {
        return $this->openAi->getDefaultVoice();
    }

    public function getSpeeds(): array
    {
        return $this->openAi->getSpeeds();
    }

    public function getOutputFormats(): array
    {
        return [
            ['value' => 'wav', 'label' => 'WAV'],
            ['value' => 'mp3', 'label' => 'MP3'],
            ['value' => 'opus', 'label' => 'Opus'],
            ['value' => 'aac', 'label' => 'AAC'],
            ['value' => 'flac', 'label' => 'FLAC'],
            ['value' => 'pcm', 'label' => 'PCM'],
        ];
    }
}
