<?php

namespace App\Http\Requests;

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
                        'A recipient override can only be used when sending one welcome email.'
                    );
                }
            },
        ];
    }
}
