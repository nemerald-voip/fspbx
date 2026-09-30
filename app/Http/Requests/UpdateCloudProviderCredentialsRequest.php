<?php

namespace App\Http\Requests;

use App\Support\Localization\ValidationMessages;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateCloudProviderCredentialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(['polycom', 'yealink'])],
            'require_serial_number' => ['sometimes', 'boolean'],
            'token' => ['nullable', 'required_if:provider,polycom', 'string'],
            'access_key_id' => ['nullable', 'required_if:provider,yealink', 'string', 'max:255'],
            'access_key_secret' => ['nullable', 'required_if:provider,yealink', 'string', 'max:255'],
            'api_url' => [
                'nullable',
                'required_if:provider,yealink',
                'url:https',
                Rule::in(config('services.ztp.yealink.api_urls', [
                    'https://us-api.ymcs.yealink.com',
                    'https://eu-api.ymcs.yealink.com',
                    'https://au-api.ymcs.yealink.com',
                ])),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider' => strtolower(trim((string) $this->input('provider'))),
        ]);

        foreach (['token', 'access_key_id', 'access_key_secret', 'api_url'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => trim((string) $this->input($field))]);
            }
        }
    }

    public function messages(): array
    {
        return ValidationMessages::common();
    }

    public function attributes(): array
    {
        return [
            'provider' => __('Provider'),
            'require_serial_number' => __('Require serial number'),
            'token' => __('API Token'),
            'access_key_id' => __('Access Key ID'),
            'access_key_secret' => __('Access Key Secret'),
            'api_url' => __('API URL'),
        ];
    }
}
