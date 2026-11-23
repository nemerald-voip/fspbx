<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('location_create');
    }

    public function rules(): array
    {
        return [
            'name'    => 'required|string|max:255',
            'description'    => 'nullable|string|max:255',
            'domain_uuid' => ['required', 'uuid', Rule::in([session('domain_uuid')])],
        ];
    }

    public function prepareForValidation(): void
    {
        if (!$this->has('domain_uuid')) {
            $this->merge(['domain_uuid' => session('domain_uuid')]);
        }
    }

    public function messages(): array
    {
        return ValidationMessages::common();
    }

    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'description' => __('Description'),
            'domain_uuid' => __('Account'),
        ];
    }
}
