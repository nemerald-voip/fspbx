<?php

namespace App\Http\Requests;

use App\Support\Localization\ValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendExtensionWelcomeEmailRequest extends FormRequest
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
            'recipient' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('recipient') && count($this->input('items', [])) !== 1) {
                    $validator->errors()->add(
                        'recipient',
                        __('A recipient override can only be used when sending one welcome email.')
                    );
                }
            },
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
            'recipient' => __('Recipient'),
        ];
    }
}
