<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only allow if user is admin or has permission (customize as needed)
        return userCheckPermission('api_key_create');
    }

    public function rules(): array
    {
        return [
            'user_uuid' => ['required', 'exists:v_users,user_uuid'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'user_uuid' => __('User'),
        ];
    }

    public function messages(): array
    {
        return [
            'required' => __('The :attribute field is required.'),
            'string' => __('The :attribute must be a string.'),
            'max.string' => __('The :attribute must not be greater than :max characters.'),
            'in' => __('The selected :attribute is invalid.'),
            'exists' => __('The selected :attribute is invalid.'),
            'uuid' => __('The :attribute must be a valid UUID.'),
        ];
    }
}
