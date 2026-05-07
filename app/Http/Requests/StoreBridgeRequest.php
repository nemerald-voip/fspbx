<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBridgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('bridge_add');
    }

    public function rules(): array
    {
        return [
            'bridge_name' => ['required', 'string', 'max:255'],
            'bridge_action' => ['nullable', 'string', Rule::in(['user', 'gateway', 'profile', 'loopback'])],
            'bridge_profile' => ['nullable', 'string', 'max:255'],
            'bridge_gateway_1' => ['nullable', 'string', 'max:255'],
            'bridge_gateway_2' => ['nullable', 'string', 'max:255'],
            'bridge_gateway_3' => ['nullable', 'string', 'max:255'],
            'destination_number' => ['nullable', 'string'],
            'bridge_destination' => ['nullable', 'string'],
            'bridge_enabled' => ['required', 'in:true,false'],
            'bridge_description' => ['nullable', 'string', 'max:255'],
            'bridge_variables' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'bridge_enabled' => $this->input('bridge_enabled', 'true'),
        ]);
    }

    protected function bridgeUuid(): ?string
    {
        return null;
    }
}
