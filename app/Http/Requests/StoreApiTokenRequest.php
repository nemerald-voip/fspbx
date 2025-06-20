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
}
