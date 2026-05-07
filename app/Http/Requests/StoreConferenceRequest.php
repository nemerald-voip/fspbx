<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Foundation\Http\FormRequest;

class StoreConferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('conference_add');
    }

    public function rules(): array
    {
        return [
            'conference_name' => ['required', 'string', 'max:255'],
            'conference_extension' => ['required', 'string', 'max:255', new UniqueExtension($this->conferenceUuid())],
            'conference_pin_number' => ['nullable', 'string', 'max:255'],
            'conference_profile' => ['required', 'string', 'max:255'],
            'conference_flags' => ['nullable', 'string', 'max:255'],
            'conference_email_address' => ['nullable', 'email', 'max:255'],
            'conference_account_code' => ['nullable', 'string', 'max:255'],
            'conference_order' => ['nullable', 'integer', 'min:0'],
            'conference_description' => ['nullable', 'string', 'max:255'],
            'conference_enabled' => ['required', 'in:true,false'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'conference_name' => preg_replace('/[^A-Za-z0-9\- ]/', '', (string) $this->input('conference_name')),
            'conference_pin_number' => preg_replace('/\D/', '', (string) $this->input('conference_pin_number')),
            'conference_profile' => $this->input('conference_profile', 'default') ?: 'default',
            'conference_order' => $this->input('conference_order', 0),
            'conference_enabled' => $this->input('conference_enabled', 'true'),
        ]);
    }

    protected function conferenceUuid(): ?string
    {
        return null;
    }
}
