<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use libphonenumber\NumberParseException;
use Propaganistas\LaravelPhone\PhoneNumber;

class UpdateAccountSettingsRequest extends FormRequest
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
            'domain_uuid' => [
                'required',
                'uuid',
            ],
            'domain_name' => [
                'required',
                'string',
            ],
            'domain_description' => [
                'required',
                'string'
            ],
            'domain_enabled' => [
                'present',
                'boolean',
            ],
            'settings' => [
                'present',
                'array',
            ],
        ];
    }


    public function messages(): array
    {
        return [
        ];
    }

    public function prepareForValidation(): void
    {
        // Sanitize description
        if ($this->has('domain_description') && $this->domain_description) {
            $sanitizedDescription = $this->sanitizeInput($this->domain_description);
            $this->merge(['domain_description' => $sanitizedDescription]);
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
