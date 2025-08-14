<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentGatewayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Adjust authorization logic as needed
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'uuid'                      => ['required', 'uuid'],
            'status'                    => ['required'],
            'sandbox_secret_key'        => ['nullable', 'string'],
            'sandbox_publishable_key'   => ['nullable', 'string'],
            'live_mode_secret_key'      => ['nullable', 'string'],
            'live_mode_publishable_key' => ['nullable', 'string'],
        ];
    }


}
