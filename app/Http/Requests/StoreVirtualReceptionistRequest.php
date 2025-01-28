<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class StoreVirtualReceptionistRequest extends FormRequest
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
            'ivr_menu_name' => 'required|string|max:75',
            'ivr_menu_extension' => [
                'required',
                'numeric',
                new UniqueExtension($currentUuid),
            ],
            'ivr_menu_enabled' => 'present',
            'ivr_menu_description' => 'nullable|string|max:100',
            'caller_id_prefix' => 'nullable|string|max:25',
            'digit_length' => 'required|numeric',
            'prompt_timeout' => 'required|numeric',
            'pin' => 'nullable|numeric',
            'ring_back_tone' => 'present',
            'invalid_input_message' => 'present',
            'direct_dial' => 'present',
        ];
    }


    public function prepareForValidation(): void
    {
        // logger($this);

        if ($this->has('ivr_menu_greet_long') && $this->ivr_menu_greet_long == 'NULL') {
            $this->merge([
                'ivr_menu_greet_long' => null,
            ]);
        }

        if ($this->has('ivr_menu_enabled')) {
            $this->merge([
                'ivr_menu_enabled' => $this->ivr_menu_enabled ? 'true' : 'false',
            ]);
        }

        // Sanitize description
        if ($this->has('ivr_menu_description') && $this->ivr_menu_description) {
            $sanitizedDescription = $this->sanitizeInput($this->ivr_menu_description);
            $this->merge(['ivr_menu_description' => $sanitizedDescription]);
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
            'ivr_menu_greet_long' => 'audio prompt',
            'caller_id_prefix' => 'caller id name prefix',
        ];
    }

    public function messages(): array
    {
        return [
            'exit_target.required' => 'The Exit Target is required when an Exit Action is selected.', 
        ];
    }
}
