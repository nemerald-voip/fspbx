<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAccessControlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('access_control_add');
    }

    public function rules(): array
    {
        return [
            'access_control_name' => ['required', 'string', 'max:255'],
            'access_control_default' => ['required', 'in:allow,deny'],
            'access_control_description' => ['nullable', 'string', 'max:255'],
            'nodes' => ['nullable', 'array'],
            'nodes.*.node_type' => ['nullable', 'in:allow,deny'],
            'nodes.*.node_cidr' => ['nullable', 'string', 'max:255'],
            'nodes.*.node_description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('nodes', []) as $index => $node) {
                $cidr = $node['node_cidr'] ?? null;

                if (blank($cidr) || $this->isValidCidr($cidr)) {
                    continue;
                }

                $validator->errors()->add("nodes.{$index}.node_cidr", 'Enter a valid IP address or CIDR range.');
            }
        });
    }

    private function isValidCidr(string $value): bool
    {
        $parts = explode('/', str_replace('\\', '/', trim($value)), 2);
        $ip = $parts[0] ?? null;

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (!isset($parts[1])) {
            return true;
        }

        $max = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 32 : 128;

        return is_numeric($parts[1]) && $parts[1] >= 0 && $parts[1] <= $max;
    }
}
