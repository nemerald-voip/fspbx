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
            'sandbox'                   => ['nullable', 'string'],
            // Validate Stripe key prefixes only when a value is provided (blank = keep saved key).
            'sandbox_secret_key'        => ['nullable', 'string', 'regex:/^(sk|rk)_test_[A-Za-z0-9]+$/'],
            'sandbox_publishable_key'   => ['nullable', 'string', 'regex:/^pk_test_[A-Za-z0-9]+$/'],
            'live_mode_secret_key'      => ['nullable', 'string', 'regex:/^(sk|rk)_live_[A-Za-z0-9]+$/'],
            'live_mode_publishable_key' => ['nullable', 'string', 'regex:/^pk_live_[A-Za-z0-9]+$/'],
            'webhook_secret'            => ['nullable', 'string', 'regex:/^whsec_[A-Za-z0-9]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'sandbox_secret_key.regex'        => 'Sandbox secret key must start with sk_test_ (or rk_test_).',
            'sandbox_publishable_key.regex'   => 'Sandbox publishable key must start with pk_test_.',
            'live_mode_secret_key.regex'      => 'Live secret key must start with sk_live_ (or rk_live_).',
            'live_mode_publishable_key.regex' => 'Live publishable key must start with pk_live_.',
            'webhook_secret.regex'            => 'Webhook signing secret must start with whsec_.',
        ];
    }
}
