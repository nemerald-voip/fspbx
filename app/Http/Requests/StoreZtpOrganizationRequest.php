<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class StoreZtpOrganizationRequest extends FormRequest
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
            'organization_name' => 'required|string|max:100',
            'domain_uuid' => 'present',
        ];
    }


    public function prepareForValidation(): void
    {
        if (!$this->has('boot_server_option') || $this->input('boot_server_option') === 'NULL') {
            $this->merge(['boot_server_option' => null]);
        }

        // Check if 'package' is missing or empty and set it to null
        if (!$this->has('option_60_type') || $this->input('option_60_type') === 'NULL') {
            $this->merge(['option_60_type' => null]);
        }

        if (!$this->has('localization_language') || $this->input('localization_language') === 'NULL') {
            $this->merge(['localization_language' => null]);
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
