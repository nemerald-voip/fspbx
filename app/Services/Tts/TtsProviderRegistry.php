<?php

namespace App\Services\Tts;

use RuntimeException;
use App\Services\Interfaces\TtsProviderInterface;

class TtsProviderRegistry
{
    /**
     * Create a TTS provider instance.
     *
     * @param  string|null  $providerKey  'openai', 'elevenlabs', or null for default
     * @return TtsProviderInterface
     */
    public function make(?string $providerKey = null): TtsProviderInterface
    {
        $providerKey = $providerKey ?: $this->defaultProvider();

        return match ($providerKey) {
            'openai'     => new OpenAiTtsService(),
            'elevenlabs' => new ElevenLabsTtsService(),
            default      => throw new RuntimeException("Unsupported TTS provider: {$providerKey}"),
        };
    }

    /**
     * Get the default TTS provider key from domain/system settings.
     */
    private function defaultProvider(): string
    {
        return get_domain_setting('tts_provider') ?? 'openai';
    }
}
