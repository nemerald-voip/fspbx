<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveLetsEncryptSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            // One or more hostnames (SANs), whitespace/comma separated.
            'domain' => ['required', 'string', 'max:1024', $this->hostnameListRule()],
            'account_email' => ['required', 'email', 'max:255'],
            'webroot' => ['required', 'string', 'max:255'],
            'staging' => ['required', 'boolean'],
            'auto_renew' => ['required', 'boolean'],
            'push_secret' => ['nullable', 'string', 'min:16', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => __('The :attribute field is required.'),
            'string' => __('The :attribute must be a string.'),
            'max.string' => __('The :attribute must not be greater than :max characters.'),
            'email' => __('The :attribute must be a valid email address.'),
            'boolean' => __('The :attribute field must be true or false.'),
            'push_secret.min' => __('The peer push secret should be at least 16 characters.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'domain' => __('Hostnames (SANs)'),
            'account_email' => __('ACME account email'),
            'webroot' => __('ACME challenge webroot'),
            'staging' => __('Use staging (test) directory'),
            'auto_renew' => __('Auto-renew'),
            'push_secret' => __('Peer push secret'),
        ];
    }

    /**
     * Validate a whitespace/comma-separated list of FQDNs.
     */
    private function hostnameListRule(): callable
    {
        return function (string $attribute, $value, callable $fail): void {
            $hosts = array_filter(array_map(
                fn ($h) => trim($h, " \t\n\r\0\x0B."),
                preg_split('/[\s,]+/', strtolower(trim((string) $value))) ?: []
            ));

            if (empty($hosts)) {
                $fail(__('At least one hostname is required.'));

                return;
            }

            foreach ($hosts as $host) {
                if (! preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $host)) {
                    $fail(__('Invalid hostname: :hostname.', ['hostname' => $host]));

                    return;
                }
            }
        };
    }
}
