<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;

class BulkSettingsActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'string'],
            'target_domain_uuid' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return ValidationMessages::common();
    }

    public function attributes(): array
    {
        return [
            'items' => __('Selected items'),
            'items.*' => __('Selected item'),
            'target_domain_uuid' => __('Target domain'),
        ];
    }
}
