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
            'push_secret.min' => 'The peer push secret should be at least 16 characters.',
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
                $fail('At least one hostname is required.');

                return;
            }

            foreach ($hosts as $host) {
                if (! preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $host)) {
                    $fail("Invalid hostname: {$host}.");

                    return;
                }
            }
        };
    }
}
