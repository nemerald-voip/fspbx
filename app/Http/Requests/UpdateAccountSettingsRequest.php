<?php

namespace App\Http\Requests;

use App\Services\Settings\AccountSettingsSchema;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

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
            'settings.*' => [
                'nullable',
                'string',
            ],
        ];
    }

    /**
     * Validate the submitted settings map against the account settings
     * schema: every key must be a known field, and a value for a field
     * backed by a fixed list (e.g. language) must be one of that list's
     * values. Open fields (e.g. time_zone) are only checked as strings.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $schema = app(AccountSettingsSchema::class);
            $keys = collect($schema->fields())->pluck('key')->all();
            $allowed = $schema->allowedValues();

            foreach ((array) $this->input('settings', []) as $key => $value) {
                if (! in_array($key, $keys, true)) {
                    $validator->errors()->add("settings.{$key}", 'Unknown setting.');
                    continue;
                }

                if ($value === null || $value === '') {
                    continue;
                }

                if (isset($allowed[$key]) && ! in_array($value, $allowed[$key], true)) {
                    $validator->errors()->add("settings.{$key}", 'The selected value is invalid.');
                }
            }
        });
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
