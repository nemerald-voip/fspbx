<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateVirtualReceptionistKeyRequest extends FormRequest
{
    protected const ACTIONS_WITHOUT_TARGET = [
        'company_directory',
        'check_voicemail',
        'hangup',
    ];

    protected const ACTIONS = [
        'extensions',
        'voicemails',
        'ring_groups',
        'ivrs',
        'business_hours',
        'time_conditions',
        'contact_centers',
        'bridges',
        'faxes',
        'call_flows',
        'dynamic_routes',
        'recordings',
        'conferences',
        'conference_centers',
        'ai_agents',
        'check_voicemail',
        'company_directory',
        'hangup',
    ];

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
            'menu_uuid' => 'required|uuid',
            'domain_uuid' => 'required|uuid',
            'key' => 'required|string|max:11',
            'status' => 'required|boolean',
            'action' => ['required', 'string', Rule::in(self::ACTIONS)],
            'target' => [
                'nullable',
                Rule::requiredIf(fn () => !in_array(
                    $this->input('action'),
                    self::ACTIONS_WITHOUT_TARGET,
                    true
                )),
                'string',
                'max:255',
            ],
            'extension' => [
                'nullable',
                Rule::requiredIf(fn () => !in_array(
                    $this->input('action'),
                    [...self::ACTIONS_WITHOUT_TARGET, 'bridges'],
                    true
                )),
                'string',
                'max:255',
            ],
            'description' => 'nullable|string|max:255',
        ];
    }


    public function prepareForValidation(): void
    {
        $target = $this->input('target');
        $normalizedTarget = $this->selectionValue($target, ['value', 'bridge_uuid']);
        $normalizedExtension = $this->selectionValue($this->input('extension'), ['extension', 'value']);

        if (blank($normalizedExtension) && is_array($target)) {
            $normalizedExtension = $this->selectionValue($target, ['extension']);
        }

        $this->merge([
            'action' => $this->selectionValue($this->input('action'), ['value']),
            'target' => $normalizedTarget,
            'extension' => $normalizedExtension,
            'status' => $this->booleanValue($this->input('status')),
        ]);

        // Sanitize description
        if (is_string($this->input('description')) && $this->input('description') !== '') {
            $sanitizedDescription = $this->sanitizeInput($this->description);
            $this->merge(['description' => $sanitizedDescription]);
        }
    }

    protected function selectionValue(mixed $value, array $keys): mixed
    {
        if ($value === 'NULL' || $value === '') {
            return null;
        }

        if (!is_array($value)) {
            return $value;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $value) && is_scalar($value[$key])) {
                return (string) $value[$key];
            }
        }

        return $value;
    }

    protected function booleanValue(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value) || is_int($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $value;
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
