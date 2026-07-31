<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLegacyProvisionTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('provision_editor_save');
    }

    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:2048'],
            'content' => ['present', 'string', 'max:2097152'],
        ];
    }
}
