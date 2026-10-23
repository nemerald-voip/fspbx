<?php

namespace App\Http\Requests;

use App\Services\HomerApiService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHomerApiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return HomerApiService::canManage();
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'base_url' => ['bail', 'nullable', 'required_if:enabled,true', 'string', 'max:2048', function ($attribute, $value, $fail) {
                if (! HomerApiService::validBaseUrl($value)) {
                    $fail(__('Enter an HTTPS base URL without credentials, query parameters, or a fragment.'));
                }
            }],
            'api_token' => ['nullable', 'string', 'max:4096', 'regex:/^[\x21-\x7e]+$/'],
        ];
    }
}
