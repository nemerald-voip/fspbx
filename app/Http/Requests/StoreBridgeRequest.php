<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'bridge_headers' => ['nullable', 'array'],
            'bridge_headers.*.name' => ['nullable', 'string', 'max:255'],
            'bridge_headers.*.value' => ['nullable', 'string', 'max:1024'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $seen = [];

            $headers = $this->input('bridge_headers', []);
            if (! is_array($headers)) {
                return;
            }

            foreach ($headers as $index => $header) {
                if (! is_array($header)) {
                    continue;
                }

                $name = trim((string) ($header['name'] ?? ''));
                $value = trim((string) ($header['value'] ?? ''));

                if ($name === '' && $value === '') {
                    continue;
                }

                if ($name === '') {
                    $validator->errors()->add("bridge_headers.{$index}.name", __('Enter a header name.'));

                    continue;
                }

                if ($value === '') {
                    $validator->errors()->add("bridge_headers.{$index}.value", __('Enter a header value.'));
                }

                $normalizedName = preg_replace('/^sip_h_/i', '', $name);
                if ($normalizedName === '' || ! preg_match('/^[A-Za-z0-9-]+$/', $normalizedName)) {
                    $validator->errors()->add(
                        "bridge_headers.{$index}.name",
                        __('Use letters, numbers, and hyphens for the header name.')
                    );

                    continue;
                }

                $comparisonName = strtolower($normalizedName);
                if (isset($seen[$comparisonName])) {
                    $validator->errors()->add("bridge_headers.{$index}.name", __('Header names must be unique.'));
                }
                $seen[$comparisonName] = true;

                if (preg_match('/[\r\n,]/', $value)) {
                    $validator->errors()->add(
                        "bridge_headers.{$index}.value",
                        __('Header values cannot contain commas or line breaks.')
                    );
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $normalized = [
            'bridge_enabled' => $this->input('bridge_enabled', 'true'),
        ];

        $bridgeVariables = $this->input('bridge_variables');
        if (is_string($bridgeVariables)) {
            $decoded = json_decode($bridgeVariables, true);

            if (is_array($decoded)) {
                $normalized['bridge_variables'] = $decoded;
            } elseif ($bridgeVariables === '[object Object]') {
                // Compatibility with previously-built VueForm assets that
                // serialized the hidden object using JavaScript string coercion.
                $normalized['bridge_variables'] = null;
            }
        }

        $this->merge($normalized);
    }

    protected function bridgeUuid(): ?string
    {
        return null;
    }
}
