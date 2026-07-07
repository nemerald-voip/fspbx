<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
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

    protected function prepareForValidation(): void
    {
        if (! $this->has('enabled')) {
            $this->merge(['enabled' => true]);
        }
    }
}
