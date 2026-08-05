<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkMakeExtensionUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('extension_create_user');
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
