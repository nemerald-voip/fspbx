<?php

namespace App\Http\Requests;

use App\Support\Localization\ValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ApplyVirtualReceptionistGreetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && session('domain_uuid') && userCheckPermission('ivr_menu_edit');
    }

    public function rules(): array
    {
        return [
            'ivr' => ['required', 'uuid'],
            'file_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ValidationMessages::common();
    }

    public function attributes(): array
    {
        return [
            'ivr' => __('Virtual Receptionist'),
            'file_name' => __('Greeting'),
        ];
    }
}
