<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
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
        return [
            'contact_organization' => [
                'required',
                'string',
            ],
            'destination_number' => [
                'required',
                'numeric',
            ],
            'phone_speed_dial' => [
                'nullable',
                'string',
            ],
            'contact_users' => 'present',
        ];
    }

    public function messages(): array
    {
        return [
            'contact_organization.required' => 'The contact name field is required',
        ];
    }

    public function prepareForValidation(): void
    {
        // Sanitize contact name
        if ($this->has('contact_organization') && $this->contact_organization) {
            $sanitizedContactOrg = $this->sanitizeInput($this->contact_organization);
            $this->merge(['contact_organization' => $sanitizedContactOrg]);
        }

        // Sanitize speed dial code
        if ($this->has('phone_speed_dial') && $this->phone_speed_dial) {
            $sanitizedSpeedDialCode = $this->sanitizeInput($this->phone_speed_dial);
            $this->merge(['phone_speed_dial' => $sanitizedSpeedDialCode]);
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
}
