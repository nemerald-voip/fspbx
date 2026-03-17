<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use App\Rules\ValidVoicemailPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use libphonenumber\PhoneNumberFormat;

class UpdateVoicemailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $currentUuid = $this->route('voicemail');

        return [
            'domain_uuid' => ['sometimes', 'uuid'],
            'voicemail_enabled' => ['required', 'in:true,false'],

            'voicemail_id' => ['sometimes', 'numeric', new UniqueExtension($currentUuid)],
            'voicemail_password' => ['nullable', 'numeric', new ValidVoicemailPassword],
            'voicemail_mail_to' => ['nullable', 'email:rfc'],
            'voicemail_sms_to' => ['nullable', 'regex:/^\+?[1-9]\d{1,14}$/'],
            'greeting_id' => ['sometimes', 'string'],

            'voicemail_tutorial' => ['sometimes', 'in:true,false'],
            'voicemail_transcription_enabled' => ['sometimes', 'in:true,false'],
            'voicemail_local_after_email' => ['sometimes', 'in:true,false'],
            'voicemail_recording_instructions' => ['sometimes', 'in:true,false'],

            'voicemail_file' => ['sometimes', 'nullable'],
            'voicemail_alternate_greet_id' => ['nullable', 'numeric'],
            'voicemail_description' => ['nullable', 'string', 'max:100'],
            'voicemail_copies' => ['nullable', 'array'],
            'extension' => ['nullable', 'uuid'],
        ];
    }


    protected function prepareForValidation(): void
    {
        // logger($this);
        $merge = [];

        if (!$this->has('domain_uuid')) {
            $merge['domain_uuid'] = session('domain_uuid');
        }

        if ($this->has('voicemail_mail_to')) {
            $merge['voicemail_mail_to'] = $this->voicemail_mail_to
                ? strtolower(trim($this->voicemail_mail_to))
                : null;
        }

        if ($this->has('voicemail_description') && $this->voicemail_description) {
            $merge['voicemail_description'] = $this->sanitizeInput($this->voicemail_description);
        }

        if ($this->has('greeting_id') && $this->greeting_id === 'NULL') {
            $merge['greeting_id'] = '-1';
        }

        if ($this->has('voicemail_file')) {
            $this->merge([
                'voicemail_file' => $this->input('voicemail_file') === 'attach' ? 'attach' : '',
            ]);
        }

        if ($this->has('voicemail_sms_to') && !blank($this->input('voicemail_sms_to'))) {
            $countryCode = get_domain_setting('country', session('domain_uuid')) ?? 'US';

            try {
                $this->merge([
                    'voicemail_sms_to' => formatPhoneNumber(
                        $this->input('voicemail_sms_to'),
                        $countryCode,
                        PhoneNumberFormat::E164
                    ),
                ]);
            } catch (\Throwable $e) {
                // Leave original value as-is so validation can fail naturally if needed
            }
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * Sanitize the input field to prevent XSS and remove unwanted characters.
     *
     * @param string $input
     * @return string
     */
    protected function sanitizeInput(string $input): string
    {
        // Trim whitespace
        $input = trim($input);

        // Strip HTML tags
        $input = strip_tags($input);

        // Escape special characters
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        // Remove any non-ASCII characters if necessary (optional)
        $input = preg_replace('/[^\x20-\x7E]/', '', $input);

        return $input;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'voicemail_id' => 'voicemail extension',
            'voicemail_password' => 'voicemail password',
            'greeting_id' => 'greeting',
            'voicemail_mail_to' => 'email address',
            'voicemail_enabled' => 'enabled',
            'voicemail_description' => 'description',
            'voicemail_alternate_greet_id' => 'value',
        ];
    }
}
