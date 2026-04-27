<?php

namespace App\Services\Interfaces;

interface TtsProviderInterface
{
    /**
     * Convert text to speech audio.
     *
     * @param  string  $input  The text to synthesize
     * @param  array   $options  Provider-specific options (voice, model, format, speed, etc.)
     * @return string  Binary audio data
     */
    public function textToSpeech(string $input, array $options = []): string;

    /**
     * Get the list of available voices.
     *
     * @return array  [{value: string, label: string}, ...]
     */
    public function getVoices(): array;

    /**
     * Get the default voice ID/name.
     */
    public function getDefaultVoice(): ?string;

    /**
     * Get the list of available speed options.
     *
     * @return array  [{value: string, label: string}, ...]
     */
    public function getSpeeds(): array;

    /**
     * Get the list of available output formats.
     *
     * @return array  [{value: string, label: string}, ...]
     */
    public function getOutputFormats(): array;
}
