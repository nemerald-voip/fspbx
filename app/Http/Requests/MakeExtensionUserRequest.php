<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MakeExtensionUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match ($this->input('role')) {
            'user' => userCheckPermission('extension_create_user'),
            'admin' => userCheckPermission('extension_create_admin'),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'extension_uuid' => [
                'required',
                'uuid',
                Rule::exists('v_extensions', 'extension_uuid')
                    ->where('domain_uuid', session('domain_uuid')),
            ],
            'role' => ['required', Rule::in(['user', 'admin'])],
        ];
    }
}
