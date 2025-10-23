<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssemblyAiConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        // Helper to turn '' into null recursively
        $nullify = function ($value) use (&$nullify) {
            if (is_array($value)) {
                foreach ($value as $k => $v) $value[$k] = $nullify($v);
                return $value;
            }
            return $value === '' ? null : $value;
        };

        $payload = $this->all();
        $payload = $nullify($payload);

        // Coerce booleans that may arrive as strings
        $bools = [
            'multichannel','language_detection','speaker_labels','format_text','punctuate',
            'disfluencies','content_safety','filter_profanity','redact_pii','redact_pii_audio',
            'auto_chapters','auto_highlights','entity_detection','sentiment_analysis',
            'iab_categories','summarization',
            'language_detection_options.code_switching',
            'redact_pii_audio_options.return_redacted_no_speech_audio',
        ];

        foreach ($bools as $path) {
            $segments = explode('.', $path);
            $ref =& $payload;
            foreach ($segments as $seg) {
                if (!array_key_exists($seg, $ref)) { $ref[$seg] = null; }
                if ($seg === end($segments)) {
                    $ref[$seg] = filter_var($ref[$seg], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                } else {
                    if (!is_array($ref[$seg])) $ref[$seg] = [];
                    $ref =& $ref[$seg];
                }
            }
        }

        // Ensure arrays exist where expected
        if (!isset($payload['custom_spelling']) || !is_array($payload['custom_spelling'])) {
            $payload['custom_spelling'] = [];
        }

        $this->replace($payload);
    }

    public function rules(): array
    {
        return [
            // Optional scope
            'domain_uuid' => ['nullable', 'uuid'],

            // 1) General & Language
            'speech_model'     => ['nullable', Rule::in(['best','slam-1','universal'])],
            'language_code'    => ['nullable','string','max:64'], // e.g. en_us
            'keyterms_prompt'  => ['nullable','string','max:5000'],
            'multichannel'     => ['nullable','boolean'],

            // 2) Language Detection
            'language_detection'                   => ['nullable','boolean'],
            'language_confidence_threshold'        => ['nullable','numeric','min:0','max:1'],
            'language_detection_options'           => ['nullable','array'],
            'language_detection_options.expected_languages'                 => ['nullable','string','max:2000'],
            'language_detection_options.fallback_language'                 => ['nullable','string','max:64'],
            'language_detection_options.code_switching'                    => ['nullable','boolean'],
            'language_detection_options.code_switching_confidence_threshold'=> ['nullable','numeric','min:0','max:1'],
            'language_codes'                      => ['nullable','string','max:1000'],

            // 3) Speaker Identification
            'speaker_labels'               => ['nullable','boolean'],
            'speaker_options'              => ['nullable','array'],
            'speaker_options.min_speakers_expected' => ['nullable','integer','min:1','max:100'],
            'speaker_options.max_speakers_expected' => ['nullable','integer','min:1','max:100'],
            'speakers_expected'            => ['nullable','integer','min:1','max:100'],

            // 4) Formatting & Customization
            'format_text'      => ['nullable','boolean'],
            'punctuate'        => ['nullable','boolean'],
            'disfluencies'     => ['nullable','boolean'],
            'custom_spelling'  => ['array'],
            'custom_spelling.*.from' => ['nullable','string','max:200'],
            'custom_spelling.*.to'   => ['nullable','string','max:200'],
            'audio_start_from'  => ['nullable','integer','min:0'],
            'audio_end_at'      => ['nullable','integer','min:0','gte:audio_start_from'],

            // 5) Content Moderation & Safety
            'content_safety'            => ['nullable','boolean'],
            'content_safety_confidence' => ['nullable','integer','min:25','max:100'],
            'filter_profanity'          => ['nullable','boolean'],

            // 6) PII Redaction
            'redact_pii'            => ['nullable','boolean'],
            'redact_pii_policies'   => ['nullable','string','max:2000'], 
            'redact_pii_sub'        => ['nullable', Rule::in(['entity_type','hash'])],
            'redact_pii_audio'      => ['nullable','boolean'],
            'redact_pii_audio_quality' => ['nullable', Rule::in(['mp3','wav'])],
            'redact_pii_audio_options' => ['nullable','array'],
            'redact_pii_audio_options.return_redacted_no_speech_audio' => ['nullable','boolean'],

            // 7) Content Intelligence & Analysis
            'auto_chapters'     => ['nullable','boolean'],
            'auto_highlights'   => ['nullable','boolean'],
            'entity_detection'  => ['nullable','boolean'],
            'sentiment_analysis'=> ['nullable','boolean'],
            'iab_categories'    => ['nullable','boolean'],
            'topics'            => ['nullable','string','max:2000'],

            // 8) Summarization
            'summarization'   => ['nullable','boolean'],
            'summary_model'   => ['nullable', Rule::in(['informative','conversational','catchy'])],
            'summary_type'    => ['nullable', Rule::in(['bullets','bullets_verbose','gist','headline','paragraph'])],
        ];
    }

    public function messages(): array
    {
        return [
            'content_safety_confidence.min' => 'Content safety confidence must be between 25 and 100.',
            'content_safety_confidence.max' => 'Content safety confidence must be between 25 and 100.',
            'audio_end_at.gte'              => 'Audio end must be greater than or equal to start.',
        ];
    }
}
