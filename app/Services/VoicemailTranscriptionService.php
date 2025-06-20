<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class VoicemailTranscriptionService
{
    public function transcribe(array $options)
    {
        $provider = $options['provider'] ?? 'openai';
        $filePath = $options['file_path'] ?? null;
        $language = $options['language'] ?? null;
        $domain_uuid = $options['domain_uuid'] ?? null;
    
        if ($provider === 'openai') {
            // Get the model from your settings or default
            $model = get_domain_setting('openai_transcription_model', $domain_uuid) ?? 'whisper-1';
    
            /** @var \App\Services\OpenAIService $openAI */
            $openAI = app(\App\Services\OpenAIService::class);
    
            return $openAI->transcribeAudio($filePath, $model, $language);
        }

        // Add support for other providers here

        return null;
    }
}
