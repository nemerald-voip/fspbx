<?php

namespace App\Http\Requests;

use App\Services\Settings\SystemSettingsSchema;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'settings' => [
                'present',
                'array',
            ],
            // A global default is the root of the inheritance chain -- there
            // is nothing below it to fall back to -- so its value cannot be
            // blank, unlike a per-account override.
            'settings.*' => [
                'required',
                'string',
            ],
        ];
    }

    /**
     * Validate the submitted settings map against the system settings schema:
     * every key must be a known field, and a value for a field backed by a
     * fixed list (e.g. language) must be one of that list's values.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $schema = app(SystemSettingsSchema::class);
            $keys = collect($schema->fields())->pluck('key')->all();
            $allowed = $schema->allowedValues();

            foreach ((array) $this->input('settings', []) as $key => $value) {
                if (! in_array($key, $keys, true)) {
                    $validator->errors()->add("settings.{$key}", 'Unknown setting.');
                    continue;
                }

                if ($value === null || $value === '') {
                    continue; // the 'required' rule already reports this
                }

                if (isset($allowed[$key]) && ! in_array($value, $allowed[$key], true)) {
                    $validator->errors()->add("settings.{$key}", 'The selected value is invalid.');
                }
            }
        });
    }
}
