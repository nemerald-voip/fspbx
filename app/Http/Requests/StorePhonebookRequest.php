<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
use Illuminate\Support\Facades\Auth;

class StorePhonebookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && userCheckPermission('phonebook_create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'enabled' => ['required', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'include_extensions' => ['nullable', 'boolean'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.first_name' => ['nullable', 'string', 'max:100'],
            'contacts.*.last_name' => ['nullable', 'string', 'max:100'],
            'contacts.*.phone_number' => ['required', 'string', 'max:50'],
        ];
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
            'enabled' => __('Enabled'),
            'is_default' => __('Account default'),
            'include_extensions' => __('Internal extensions'),
            'contacts' => __('Contacts'),
            'contacts.*.first_name' => __('Contact :position first name'),
            'contacts.*.last_name' => __('Contact :position last name'),
            'contacts.*.phone_number' => __('Contact :position phone number'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('enabled')) {
            $this->merge(['enabled' => true]);
        }
    }
}
