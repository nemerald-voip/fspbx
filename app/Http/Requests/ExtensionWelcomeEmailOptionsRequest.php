<?php

namespace App\Http\Requests;

use App\Support\Localization\ValidationMessages;
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

    public function messages(): array
    {
        return ValidationMessages::common();
    }

    public function attributes(): array
    {
        return [
            'items' => __('Extensions'),
            'items.*' => __('Extension'),
        ];
    }
}
