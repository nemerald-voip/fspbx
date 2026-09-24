<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAssemblyAiConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        // '' -> null (deep)
        $nullify = function ($value) use (&$nullify) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $value[$k] = $nullify($v);
                }
                return $value;
            }
            return $value === '' ? null : $value;
        };

        $payload = $nullify($this->all());

        // Normalize booleans that might arrive as strings
        $bools = [
            'multichannel',
            'language_detection',
            'speaker_labels',
            'format_text',
            'punctuate',
            'disfluencies',
            'content_safety',
            'filter_profanity',
            'redact_pii',
            'redact_pii_audio',
            'auto_chapters',
            'auto_highlights',
            'entity_detection',
            'sentiment_analysis',
            'iab_categories',
            'summarization',
            'language_detection_options.code_switching',
            'redact_pii_audio_options.return_redacted_no_speech_audio',
        ];

        foreach ($bools as $path) {
            $segments = explode('.', $path);
            $lastIdx  = array_key_last($segments);

            $ref = &$payload;
            foreach ($segments as $i => $seg) {
                if (!array_key_exists($seg, $ref)) {
                    $ref[$seg] = null;
                }
                if ($i === $lastIdx) {
                    $ref[$seg] = filter_var($ref[$seg], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                } else {
                    if (!is_array($ref[$seg])) {
                        $ref[$seg] = [];
                    }
                    $ref = &$ref[$seg];
                }
            }
        }

        // Ensure arrays exist where expected
        if (!isset($payload['custom_spelling']) || !is_array($payload['custom_spelling'])) {
            $payload['custom_spelling'] = [];
        }

        // Add this for speech_models:
        if (!isset($payload['speech_models']) || !is_array($payload['speech_models'])) {
            $payload['speech_models'] = [];
        }

        // Normalize integer fields (convert numeric strings to actual integers)
        $intFields = [
            'speaker_options.min_speakers_expected',
            'speaker_options.max_speakers_expected',
            'speakers_expected',
        ];

        foreach ($intFields as $path) {
            $segments = explode('.', $path);
            $lastIdx  = array_key_last($segments);

            $ref = &$payload;
            foreach ($segments as $i => $seg) {
                if (!array_key_exists($seg, $ref)) {
                    $ref[$seg] = null;
                }

                if ($i === $lastIdx) {
                    // Convert to int when numeric, otherwise leave null
                    $ref[$seg] = is_numeric($ref[$seg]) ? (int)$ref[$seg] : null;
                } else {
                    if (!is_array($ref[$seg])) {
                        $ref[$seg] = [];
                    }
                    $ref = &$ref[$seg];
                }
            }
        }

        $this->replace($payload);
    }

    public function rules(): array
    {
        return [
            // Optional scope
            'domain_uuid' => ['nullable', 'uuid'],

            // 1) General & Language
            'speech_models'    => ['nullable', 'array'],
            'speech_models.*'  => ['string', Rule::in(['universal-3-pro', 'universal-2'])],
            'language_code'    => ['nullable', 'string', 'max:64'], // e.g. en_us
            'keyterms_prompt'  => ['nullable', 'string', 'max:5000'],
            'multichannel'     => ['nullable', 'boolean'],

            // 2) Language Detection
            'language_detection'                   => ['nullable', 'boolean'],
            'language_confidence_threshold'        => ['nullable', 'numeric', 'min:0', 'max:1'],
            'language_detection_options'           => ['nullable', 'array'],
            'language_detection_options.expected_languages'                 => ['nullable', 'string', 'max:2000'],
            'language_detection_options.fallback_language'                 => ['nullable', 'string', 'max:64'],
            'language_detection_options.code_switching'                    => ['nullable', 'boolean'],
            'language_detection_options.code_switching_confidence_threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'language_codes'                      => ['nullable', 'string', 'max:1000'],

            // 3) Speaker Identification
            'speaker_labels'               => ['nullable', 'boolean'],
            'speaker_options'              => ['nullable', 'array'],
            'speaker_options.min_speakers_expected' => ['nullable', 'integer', 'between:1,100'],
            'speaker_options.max_speakers_expected' => ['nullable', 'integer', 'between:1,100'],
            'speakers_expected'                     => ['nullable', 'integer', 'between:1,100'],

            // 4) Formatting & Customization
            'format_text'      => ['nullable', 'boolean'],
            'punctuate'        => ['nullable', 'boolean'],
            'disfluencies'     => ['nullable', 'boolean'],
            'custom_spelling'  => ['array'],
            'custom_spelling.*.from' => ['nullable', 'string', 'max:200'],
            'custom_spelling.*.to'   => ['nullable', 'string', 'max:200'],
            'audio_start_from'  => ['nullable', 'integer', 'min:0'],
            'audio_end_at'      => ['nullable', 'integer', 'min:0', 'gte:audio_start_from'],

            // 5) Content Moderation & Safety
            'content_safety'            => ['nullable', 'boolean'],
            'content_safety_confidence' => ['nullable', 'integer', 'min:25', 'max:100'],
            'filter_profanity'          => ['nullable', 'boolean'],

            // 6) PII Redaction
            'redact_pii'            => ['nullable', 'boolean'],
            'redact_pii_policies'   => ['nullable', 'array'],
            'redact_pii_sub'        => ['nullable', Rule::in(['entity_name', 'hash'])],
            'redact_pii_audio'      => ['nullable', 'boolean'],
            'redact_pii_audio_quality' => ['nullable', Rule::in(['mp3', 'wav'])],
            'redact_pii_audio_options' => ['nullable', 'array'],
            'redact_pii_audio_options.return_redacted_no_speech_audio' => ['nullable', 'boolean'],

            // 7) Content Intelligence & Analysis
            'auto_chapters'     => ['nullable', 'boolean'],
            'auto_highlights'   => ['nullable', 'boolean'],
            'entity_detection'  => ['nullable', 'boolean'],
            'sentiment_analysis' => ['nullable', 'boolean'],
            'iab_categories'    => ['nullable', 'boolean'],
            'topics'            => ['nullable', 'string', 'max:2000'],

            // 8) Summarization
            'summarization'   => ['nullable', 'boolean'],
            'summary_model'   => ['nullable', Rule::in(['informative', 'conversational', 'catchy'])],
            'summary_type'    => ['nullable', Rule::in(['bullets', 'bullets_verbose', 'gist', 'headline', 'paragraph'])],
        ];
    }

    public function messages(): array
    {
        return [
            ...ValidationMessages::common(),
            'language_confidence_threshold.min' => __('Language confidence must be between 0 and 1.'),
            'language_confidence_threshold.max' => __('Language confidence must be between 0 and 1.'),

            'language_detection_options.code_switching_confidence_threshold.min'
            => __('Code-switching confidence must be between 0 and 1.'),
            'language_detection_options.code_switching_confidence_threshold.max'
            => __('Code-switching confidence must be between 0 and 1.'),

            'content_safety_confidence.min' => __('Content safety confidence must be between 25 and 100.'),
            'content_safety_confidence.max' => __('Content safety confidence must be between 25 and 100.'),
            'redact_pii_policies.required' => __('Select at least one PII redaction policy when redacting transcribed text.'),
            'redact_pii_policies.min' => __('Select at least one PII redaction policy when redacting transcribed text.'),
            // Audio timing
            'audio_start_from.integer' => __('Start time must be a whole number (milliseconds).'),
            'audio_start_from.min'     => __('Start time can’t be negative.'),
            'audio_end_at.integer'     => __('End time must be a whole number (milliseconds).'),
            'audio_end_at.min'         => __('End time can’t be negative.'),
            'audio_end_at.gte'         => __('End time must be greater than or equal to the start time.'),

            // Speaker ranges
            'speaker_options.min_speakers_expected.between' => __('Minimum speakers must be between 1 and 100.'),
            'speaker_options.max_speakers_expected.between' => __('Maximum speakers must be between 1 and 100.'),
            'speakers_expected.between'                     => __('Number of expected speakers must be between 1 and 100.'),

            // Optional: cleaner integer messages
            'speaker_options.min_speakers_expected.integer' => __('Minimum speakers must be a whole number.'),
            'speaker_options.max_speakers_expected.integer' => __('Maximum speakers must be a whole number.'),
            'speakers_expected.integer'                     => __('Number of expected speakers must be a whole number.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->sometimes('redact_pii_policies', ['required', 'array', 'min:1'], function () {
            return $this->boolean('redact_pii');
        });
    }

    public function attributes(): array
    {
        return [
            'domain_uuid' => __('Account'),
            'speech_models' => __('Speech Models'),
            'speech_models.*' => __('Speech Models'),
            'language_code' => __('Language Code'),
            'keyterms_prompt' => __('Key terms'),
            'multichannel' => __('Enable Multichannel transcription'),
            'language_detection' => __('Language Detection'),
            'language_confidence_threshold' => __('Language Confidence Threshold'),
            'language_detection_options' => __('Language Detection Options (Optional)'),
            'language_detection_options.expected_languages' => __('Expected Languages'),
            'language_detection_options.fallback_language' => __('Fallback Language'),
            'language_detection_options.code_switching' => __('Code Switching'),
            'language_detection_options.code_switching_confidence_threshold' => __('Code Switching Confidence Threshold'),
            'language_codes' => __('Language Codes'),
            'speaker_labels' => __('Enable Speaker diarization'),
            'speaker_options' => __('Speaker Diarization Options (Optional)'),
            'speaker_options.min_speakers_expected' => __('Minimum Speakers Expected'),
            'speaker_options.max_speakers_expected' => __('Maximum Speakers Expected'),
            'speakers_expected' => __('Number of Expected Speakers'),
            'format_text' => __('Enable Text Formatting'),
            'punctuate' => __('Enable Automatic Punctuation'),
            'disfluencies' => __('Transcribe filler words (e.g., “umm”)'),
            'custom_spelling' => __('Custom Spelling'),
            'custom_spelling.*.from' => __('From'),
            'custom_spelling.*.to' => __('To'),
            'audio_start_from' => __('Audio Start From (ms)'),
            'audio_end_at' => __('Audio End At (ms)'),
            'content_safety' => __('Content Safety'),
            'content_safety_confidence' => __('Content Safety Confidence'),
            'filter_profanity' => __('Filter profanity from the transcribed text'),
            'redact_pii' => __('Redact PII in transcribed text'),
            'redact_pii_policies' => __('PII Redaction Policies'),
            'redact_pii_sub' => __('Replacement Logic for Detected PII'),
            'redact_pii_audio' => __('Redact PII in audio (beeped out)'),
            'redact_pii_audio_quality' => __('Redacted Audio Quality'),
            'redact_pii_audio_options' => __('Redacted Audio Options (Optional)'),
            'redact_pii_audio_options.return_redacted_no_speech_audio' => __('Return redacted audio even when there is no speech'),
            'auto_chapters' => __('Enable Auto Chapters'),
            'auto_highlights' => __('Enable Key Phrases'),
            'entity_detection' => __('Enable Entity Detection'),
            'sentiment_analysis' => __('Enable Sentiment Analysis'),
            'iab_categories' => __('Enable Topic Detection'),
            'topics' => __('Topics'),
            'summarization' => __('Summarization'),
            'summary_model' => __('Summary Model'),
            'summary_type' => __('Summary Type'),
        ];
    }
}
