<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
use Illuminate\Validation\Rule;

class SaveDefaultSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'default_setting_category' => ['required', 'string', 'max:255'],
            'default_setting_subcategory' => ['required', 'string', 'max:255'],
            'default_setting_name' => ['required', Rule::in(['array', 'boolean', 'code', 'dir', 'name', 'numeric', 'text', 'uuid'])],
            'default_setting_value' => ['nullable', 'string'],
            'default_setting_order' => ['nullable', 'numeric'],
            'default_setting_enabled' => ['required', 'boolean'],
            'default_setting_description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return ValidationMessages::common();
    }

    public function attributes(): array
    {
        return [
            'default_setting_category' => __('Category'),
            'default_setting_subcategory' => __('Setting Name'),
            'default_setting_name' => __('Type'),
            'default_setting_value' => __('Value'),
            'default_setting_order' => __('Order'),
            'default_setting_enabled' => __('Enabled'),
            'default_setting_description' => __('Description'),
        ];
    }
}
