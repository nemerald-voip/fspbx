<?php

namespace App\Http\Requests;

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
}
