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
                // 1) Secrets & connection from config/services.php
                $svc = config('services.assemblyai', []);

                $apiKey  = (string)($svc['api_key']  ?? '');
                $region  = (string)($svc['region']   ?? 'US');
                $baseUrl = $svc['base_url'] ?? null;          // null => auto by region inside provider
                $timeout = (int)($svc['timeout']  ?? 30);

                if ($apiKey === '') {
                    throw new RuntimeException('AssemblyAI API key is not configured.');
                }

                $conn = [
                    'api_key'  => $apiKey,
                    'region'   => $region,
                    'base_url' => $baseUrl,
                    'timeout'  => $timeout,
                ];

                // 2) Options come from DB. Your payload has them under ["config"].
                $rawOptions = $providerConfig['config'] ?? $providerConfig;

                // Whitelist option keys you want to pass through to the provider client
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
                    if (array_key_exists($k, $rawOptions)) {
                        $opts[$k] = $rawOptions[$k];
                    }
                }

                return new AssemblyAiService($conn, $opts);

            default:
                throw new RuntimeException("Unsupported transcription provider: {$providerKey}");
        }
    }
}