<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVirtualReceptionistKeyRequest extends FormRequest
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
            'menu_uuid' => 'present',
            'option_uuid' => 'present',
            'key' => 'required|string|max:11',
            'status' => 'required',
            'action' => 'required',
            'target' => 'required',
            'description' => 'nullable|string|max:255',
        ];
    }


    public function prepareForValidation(): void
    {

        // Check if 'action' is missing or empty and set it to null
        if (!$this->has('action') || $this->input('action') === 'NULL') {
            $this->merge(['action' => null]);
        }

        // Check if 'action' is missing or empty and set it to null
        if (!$this->has('target') || $this->input('target') === 'NULL') {
            $this->merge(['target' => null]);
        }

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
