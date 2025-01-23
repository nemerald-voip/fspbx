<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Support\Facades\Auth;
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

        return [
            'ivr_menu_name' => 'string',
            'ivr_menu_extension' => [
                'required',
                'numeric',
                new UniqueExtension($currentUuid),
            ],
            'ivr_menu_greet_long' => 'present',
            // 'greeting_id' => 'required|string',
            'ivr_menu_enabled' => 'present',
            'ivr_menu_description' => 'nullable|string|max:100',
            'repeat_prompt' => 'required',
            'caller_id_prefix' => 'nullable|string|max:25',
            'digit_length' => 'required|numeric',
            'prompt_timeout' => 'required|numeric',
            'pin' => 'nullable|numeric',
            'ring_back_tone' => 'present',
            'invalid_input_message' => 'present',
            'exit_message' => 'present',
            'direct_dial' => 'present',
            // 'extension' => "uuid",
        ];
    }


    public function prepareForValidation(): void
    {
        // logger($this);
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

        if ($this->has('repeat_prompt') && $this->repeat_prompt == 'NULL') {
            $this->merge([
                'repeat_prompt' => null,
            ]);
        }

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
            'caller_id_prefix' => 'caller id name prefix',
            'voicemail_enabled' => 'enabled',
            'voicemail_description' => 'description',
            'voicemail_alternate_greet_id' => 'value',
        ];
    }
}
