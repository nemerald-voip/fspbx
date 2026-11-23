<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwitchDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'domain_uuid' => ['required', 'uuid'],
            'redirect_url' => ['nullable', 'string'],
        ];
    }
}
