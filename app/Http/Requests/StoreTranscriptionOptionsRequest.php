<?php

namespace App\Http\Requests;
use Illuminate\Validation\Rule;

use Illuminate\Foundation\Http\FormRequest;

class StoreTranscriptionOptionsRequest extends FormRequest
{

    public function authorize(): bool
    {
        // If you gate by permission, swap this with: return $this->user()->can('hotel_rooms_update');
        return true;
    }

    public function rules(): array
    {
        $isSystemScope = empty($this->input('domain_uuid')); // system when domain_uuid is null

        return [
            'enabled' => ['required', 'boolean'],
            'auto_transcribe'  => ['required','boolean'],

            // For system scope: provider is required if enabled
            // For domain scope: provider is optional (inherit system if null)
            'provider_uuid' => [
                $isSystemScope
                    ? Rule::requiredIf(fn () => (bool) $this->input('enabled') === true)
                    : 'nullable',
                'nullable',
                Rule::exists('call_transcription_providers', 'uuid')
                    ->where('is_active', true),
            ],

            'domain_uuid' => ['nullable', 'uuid'],
            'email_transcription'  => ['required','boolean'],
            'email' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider_uuid.required' => 'Select a provider when enabling transcriptions at the system level.',
            'provider_uuid.exists'   => 'Selected provider is not active or does not exist.',
        ];
    }

    public function prepareForValidation(): void
    {
        // logger($this);
    }
}
