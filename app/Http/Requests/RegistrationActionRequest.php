<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RegistrationActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['reboot', 'provision', 'unregister'])],
            'items' => ['required_without:regs', 'array', 'min:1'],
            'items.*' => ['required', 'string', 'max:512', 'distinct'],
            'regs' => ['required_without:items', 'array', 'min:1'],
            'regs.*' => ['array'],
            'regs.*.call_id' => ['required_with:regs', 'string', 'max:512'],
        ];
    }
}
