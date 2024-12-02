<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class StoreWhitelistNumberRequest extends FormRequest
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
            'number' => [
                'required',
                'regex:/^\d+$/', // Ensures only digits
            ],
            'description' => 'nullable|string|max:100',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'number.regex' => 'The number field must contain only digits.',
        ];
    }


    public function prepareForValidation(): void
    {
        // Sanitize description
        if ($this->has('description') && $this->description) {
            $sanitizedDescription = $this->sanitizeInput($this->description);
            $this->merge(['description' => $sanitizedDescription]);
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
