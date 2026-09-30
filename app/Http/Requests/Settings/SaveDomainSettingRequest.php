<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
use Illuminate\Validation\Rule;

class SaveDomainSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'domain_setting_category' => ['required', 'string', 'max:255'],
            'domain_setting_subcategory' => ['required', 'string', 'max:255'],
            'domain_setting_name' => ['required', Rule::in(['array', 'boolean', 'code', 'dir', 'name', 'numeric', 'text', 'uuid'])],
            'domain_setting_value' => ['nullable', 'string'],
            'domain_setting_order' => ['nullable', 'numeric'],
            'domain_setting_enabled' => ['required', 'boolean'],
            'domain_setting_description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return ValidationMessages::common();
    }

    public function attributes(): array
    {
        return [
            'domain_setting_category' => __('Category'),
            'domain_setting_subcategory' => __('Setting Name'),
            'domain_setting_name' => __('Type'),
            'domain_setting_value' => __('Value'),
            'domain_setting_order' => __('Order'),
            'domain_setting_enabled' => __('Enabled'),
            'domain_setting_description' => __('Description'),
        ];
    }
}
