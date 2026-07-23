<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExtensionWelcomeEmailOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('extension_welcome_email_send');
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
