<?php

namespace App\Services;

class VoicemailTranscriptionService
{
    public function transcribe(array $options)
    {
        try {

            $provider = $options['provider'] ?? 'openai';
            $filePath = $options['file_path'] ?? null;
            $language = $options['language'] ?? null;
            $domain_uuid = $options['domain_uuid'] ?? null;

            if ($provider === 'openai') {
                // Get the model from your settings or default
                $model = get_domain_setting('openai_transcription_model', $domain_uuid) ?? 'whisper-1';

                /** @var \App\Services\OpenAIService $openAI */
                $openAI = app(\App\Services\OpenAIService::class);

                return $openAI->transcribeAudio($filePath, $model, $language = null);
            }

            if ($provider === 'google') {
                /** @var \App\Services\GoogleService $google */
                $google = app(\App\Services\GoogleService::class);

                return $google->transcribe($filePath, $language);
            }

            if ($provider === 'azure') {
                /** @var \App\Services\AzureService $azure */
                $azure = app(\App\Services\AzureService::class);

                return $azure->transcribe($filePath, $language);
            }

            if ($provider === 'watson') {
                /** @var \App\Services\WatsonService $watson */
                $watson = app(\App\Services\WatsonService::class);

                return $watson->transcribe($filePath, $language);
            }
            
        } catch (\Throwable $e) {
            logger("VoicemailTranscriptionService@transcribe: An unexpected error occurred during transcription with provider '{$provider}'.", [
                'error' => $e->getMessage(),
                'file' => $filePath,
            ]);
        }

        // Add support for other providers here

        return null;
    }
}
