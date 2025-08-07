<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only allow if user is admin or has permission (customize as needed)
        return userCheckPermission('api_key_create');
    }

    public function rules(): array
    {
        return [
            'name'    => 'required|string|max:255',
            'description'    => 'nullable|string|max:255',
            'domain_uuid' => 'present',
        ];
    }

    public function prepareForValidation(): void
    {
        if (!$this->has('domain_uuid')) {
            $this->merge(['domain_uuid' => session('domain_uuid')]);
        }
    }
}
