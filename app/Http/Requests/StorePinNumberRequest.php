<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
use Illuminate\Validation\Rule;

class StorePinNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('pin_number_add');
    }

    public function rules(): array
    {
        return [
            'pin_number' => ['required', 'string', 'max:255'],
            'accountcode' => ['nullable', 'string', 'max:255'],
            'enabled' => ['required', Rule::in(['true', 'false'])],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => $this->input('enabled', 'true'),
        ]);
    }

    public function messages(): array
    {
        return ValidationMessages::common();
    }

    public function attributes(): array
    {
        return [
            'pin_number' => __('PIN Number'),
            'accountcode' => __('Account Code'),
            'enabled' => __('Enabled'),
            'description' => __('Description'),
        ];
    }
}
