<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Support\Facades\Auth;
use App\Rules\ValidVoicemailPassword;
use Illuminate\Foundation\Http\FormRequest;

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
        //get current UUID from route model binding
        $currentUuid = $this->route('voicemail');

        return [
            'voicemail_id' => [
                'required',
                'numeric',
                new UniqueExtension($currentUuid),
            ],
            'voicemail_password' => ['nullable', 'numeric', new ValidVoicemailPassword],
            'voicemail_mail_to' => 'nullable|email:rfc',
            'voicemail_sms_to' => 'nullable|string',
            'greeting_id' => 'required|string',
            'voicemail_enabled' => 'present',
            'voicemail_tutorial' => 'present',
            'voicemail_alternate_greet_id' => 'nullable|numeric',
            'voicemail_description' => 'nullable|string|max:100',
            'voicemail_transcription_enabled' => 'present',
            'voicemail_file' => 'present',
            'voicemail_local_after_email' => 'present',
            'voicemail_recording_instructions' => 'present',
            'voicemail_copies' => 'nullable|array',
            'extension' => "uuid",
        ];
    }


    public function prepareForValidation(): void
    {
        // logger($this);
        if (!$this->has('domain_uuid')) {
            $this->merge(['domain_uuid' => session('domain_uuid')]);
        }

        // Convert boolean values back to strings
        if ($this->has('voicemail_delete')) {
            $this->merge([
                'voicemail_local_after_email' => $this->voicemail_delete ? 'false' : 'true',
            ]);
        } else {
            //merge default value
            $this->merge([
                'voicemail_local_after_email' => get_domain_setting('keep_local'),
            ]);
        }

        if ($this->has('voicemail_email_attachment')) {
            $this->merge([
                'voicemail_file' => $this->voicemail_email_attachment ? 'attach' : '',
            ]);
        } else {
            // Merge default value
            $this->merge([
                'voicemail_file' => get_domain_setting('voicemail_file'),
            ]);
        }

        if ($this->has('voicemail_transcription_enabled')) {
            $this->merge([
                'voicemail_transcription_enabled' => $this->voicemail_transcription_enabled ? 'true' : 'false',
            ]);
        } else {
            // Merge default value
            $this->merge([
                'voicemail_transcription_enabled' => get_domain_setting('transcription_enabled_default'),
            ]);
        }

        $this->merge([
            'voicemail_mail_to' => $this->voicemail_mail_to ? strtolower($this->voicemail_mail_to) : null,
        ]);

        if ($this->has('voicemail_tutorial')) {
            $this->merge([
                'voicemail_tutorial' => $this->voicemail_tutorial ? 'true' : 'false',
            ]);
        }

        if ($this->has('voicemail_enabled')) {
            $this->merge([
                'voicemail_enabled' => $this->voicemail_enabled ? 'true' : 'false',
            ]);
        }

        if ($this->has('voicemail_play_recording_instructions')) {
            $this->merge([
                'voicemail_recording_instructions' => $this->voicemail_play_recording_instructions ? 'true' : 'false',
            ]);
        }

        // Sanitize voicemail_description
        if ($this->has('voicemail_description') && $this->voicemail_description) {
            $sanitizedDescription = $this->sanitizeInput($this->voicemail_description);
            $this->merge(['voicemail_description' => $sanitizedDescription]);
        }

        if ($this->has('greeting_id')) {
            if ($this->greeting_id == 'NULL') {
                $this->merge(['greeting_id' => '-1']);
            }
           
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
