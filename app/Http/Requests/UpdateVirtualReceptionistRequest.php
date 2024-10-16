<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Support\Facades\Auth;
use App\Rules\ValidVoicemailPassword;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVirtualReceptionistRequest extends FormRequest
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
        $currentUuid = $this->route('virtual_receptionist');

        logger('rules');
        logger($currentUuid);

        return [
            'ivr_menu_name' => 'string',
            'ivr_menu_extension' => [
                'required',
                'numeric',
                new UniqueExtension($currentUuid),
            ],
            // 'voicemail_password' => ['nullable', 'numeric', new ValidVoicemailPassword],
            // 'voicemail_mail_to' => 'nullable|email:rfc',
            // 'greeting_id' => 'required|string',
            'ivr_menu_enabled' => 'present',
            'ivr_menu_description' => 'nullable|string|max:100',
            // 'voicemail_transcription_enabled' => 'present',
            // 'voicemail_file' => 'present',
            // 'voicemail_local_after_email' => 'present',
            // 'voicemail_recording_instructions' => 'present',
            // 'voicemail_copies' => 'nullable|array',
            // 'extension' => "uuid",
        ];
    }


    public function prepareForValidation(): void
    {
        logger($this);
        if (!$this->has('domain_uuid')) {
            $this->merge(['domain_uuid' => session('domain_uuid')]);
        }

        // Convert boolean values back to strings
        // if ($this->has('voicemail_delete')) {
        //     $this->merge([
        //         'voicemail_local_after_email' => $this->voicemail_delete ? 'false' : 'true',
        //     ]);
        // } else {
        //     //merge default value
        //     $this->merge([
        //         'voicemail_local_after_email' => get_domain_setting('keep_local'),
        //     ]);
        // }

        // if ($this->has('voicemail_email_attachment')) {
        //     $this->merge([
        //         'voicemail_file' => $this->voicemail_email_attachment ? 'attach' : '',
        //     ]);
        // } else {
        //     // Merge default value
        //     $this->merge([
        //         'voicemail_file' => get_domain_setting('voicemail_file'),
        //     ]);
        // }

        // if ($this->has('voicemail_transcription_enabled')) {
        //     $this->merge([
        //         'voicemail_transcription_enabled' => $this->voicemail_transcription_enabled ? 'true' : 'false',
        //     ]);
        // } else {
        //     // Merge default value
        //     $this->merge([
        //         'voicemail_transcription_enabled' => get_domain_setting('transcription_enabled_default'),
        //     ]);
        // }

        // if ($this->has('voicemail_tutorial')) {
        //     $this->merge([
        //         'voicemail_tutorial' => $this->voicemail_tutorial ? 'true' : 'false',
        //     ]);
        // }

        if ($this->has('ivr_menu_enabled')) {
            $this->merge([
                'ivr_menu_enabled' => $this->ivr_menu_enabled ? 'true' : 'false',
            ]);
        }


        // Sanitize voicemail_description
        if ($this->has('ivr_menu_description') && $this->ivr_menu_description) {
            $sanitizedDescription = $this->sanitizeInput($this->ivr_menu_description);
            $this->merge(['ivr_menu_description' => $sanitizedDescription]);
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
            'ivr_menu_name' => 'name',
            'ivr_menu_extension' => 'extension',
            'greeting_id' => 'greeting',
            'ivr_menu_description' => 'email address',
            'voicemail_enabled' => 'enabled',
            'voicemail_description' => 'description',
            'voicemail_alternate_greet_id' => 'value',
        ];
    }
}
