<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreElevenLabsConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
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

        // Normalize booleans
        $bools = ['diarize', 'tag_audio_events'];
        foreach ($bools as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = filter_var($payload[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
        }

        // Normalize integer fields
        if (isset($payload['num_speakers']) && is_numeric($payload['num_speakers'])) {
            $payload['num_speakers'] = (int) $payload['num_speakers'];
        }

        $this->replace($payload);
    }

    public function rules(): array
    {
        return [
            'domain_uuid'             => ['nullable', 'uuid'],
            'model_id'                => ['nullable', 'string', Rule::in(['scribe_v2', 'scribe_v1'])],
            'diarize'                 => ['nullable', 'boolean'],
            'timestamps_granularity'  => ['nullable', 'string', Rule::in(['word', 'character', 'none'])],
            'language_code'           => ['nullable', 'string', 'max:64'],
            'keyterms'                => ['nullable', 'string', 'max:5000'],
            'tag_audio_events'        => ['nullable', 'boolean'],
            'num_speakers'            => ['nullable', 'integer', 'between:1,32'],
        ];
    }

    public function messages(): array
    {
        return [
            'num_speakers.between' => 'Number of speakers must be between 1 and 32.',
        ];
    }
}
