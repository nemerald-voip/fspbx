<?php

namespace App\Http\Requests;

use App\Rules\UniqueExtension;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class StoreVirtualReceptionistRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
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
            'exit_message' => 'present',
            'direct_dial' => 'present',
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->has('ivr_menu_description') && $this->ivr_menu_description) {
            $this->merge([
                'ivr_menu_description' => $this->sanitizeInput($this->ivr_menu_description),
            ]);
        }
    }

    /**
     * Sanitize the input field to prevent XSS and remove unwanted characters.
     */
    protected function sanitizeInput(string $input): string
    {
        $input = trim($input);
        $input = strip_tags($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        $input = preg_replace('/[^\x20-\x7E]/', '', $input);

        return $input;
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'ivr_menu_name' => 'name',
            'ivr_menu_extension' => 'extension',
            'caller_id_prefix' => 'caller id name prefix',
            'prompt_timeout' => 'input timeout',
            'digit_length' => 'digit length',
            'ring_back_tone' => 'ring back tone',
            'invalid_input_message' => 'invalid input message',
            'exit_message' => 'exit message',
            'direct_dial' => 'direct dialing',
        ];
    }
}