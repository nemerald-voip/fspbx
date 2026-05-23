<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSwitchVariableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'var_category' => ['required', 'string', 'max:255'],
            'var_name' => ['required', 'string', 'max:255'],
            'var_value' => ['nullable', 'string'],
            'var_command' => ['nullable', Rule::in(['set', 'exec-set'])],
            'var_hostname' => ['nullable', 'string', 'max:255'],
            'var_enabled' => ['required', 'boolean'],
            'var_order' => ['required', 'numeric'],
            'var_description' => ['nullable', 'string'],
        ];
    }
}
