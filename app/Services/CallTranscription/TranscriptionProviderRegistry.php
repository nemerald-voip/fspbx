<?php

namespace App\Services\CallTranscription;

use RuntimeException;
use App\Services\Interfaces\TranscriptionProviderInterface;

class TranscriptionProviderRegistry
{
    public function make(string $providerKey, array $providerConfig): TranscriptionProviderInterface
    {
        switch ($providerKey) {
            case 'assemblyai':
                $apiKey = (string)($providerConfig['api_key'] ?? '');
                if ($apiKey === '') {
                    throw new RuntimeException('AssemblyAI API key is not configured.');
                }

                $conn = [
                    'api_key'  => $apiKey,
                    'region'   => $providerConfig['region']   ?? 'US',
                    'base_url' => $providerConfig['base_url'] ?? null,
                    'timeout'  => (int)($providerConfig['timeout'] ?? 30),
                ];

                // Whitelist option keys you want to pass through
                $allow = [
                    'speech_model','language_code','keyterms_prompt','multichannel',
                    'language_detection','language_confidence_threshold','language_detection_options',
                    'language_codes',
                    'speaker_labels','speaker_options','speakers_expected',
                    'format_text','punctuate','disfluencies','custom_spelling',
                    'audio_start_from','audio_end_at',
                    'content_safety','content_safety_confidence','filter_profanity',
                    'redact_pii','redact_pii_policies','redact_pii_sub','redact_pii_audio',
                    'redact_pii_audio_quality','redact_pii_audio_options',
                    'auto_chapters','auto_highlights','entity_detection','sentiment_analysis','iab_categories','topics',
                    'summarization','summary_model','summary_type',
                ];
                $opts = [];
                foreach ($allow as $k) {
                    if (array_key_exists($k, $providerConfig)) {
                        $opts[$k] = $providerConfig[$k];
                    }
                }

                return new AssemblyAiService($conn, $opts);

            default:
                throw new RuntimeException("Unsupported transcription provider: {$providerKey}");
        }
    }
}
