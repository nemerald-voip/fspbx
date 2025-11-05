<?php

namespace App\Services\Interfaces;

interface TranscriptionProviderInterface
{
    /**
     * Transcribe an audio URL with provider-specific options.
     * Return provider response (id/status/etc).
     */
    public function transcribe(string $audioUrl, array $options = []): array;

    public function fetchTranscript(string $transcriptId): array;

    public function requestCallSummary(array $utterancesLines): array;

}
