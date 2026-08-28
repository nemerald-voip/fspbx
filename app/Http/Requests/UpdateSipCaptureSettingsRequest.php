<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSipCaptureSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $requiredWhenEnabled = Rule::requiredIf(fn () => $this->boolean('enabled'));

        return [
            'enabled' => ['required', 'boolean'],
            'transport' => [$requiredWhenEnabled, 'nullable', Rule::in(['udp', 'tcp'])],
            'collector_host' => [$requiredWhenEnabled, 'nullable', 'string', 'max:253', function ($attribute, $value, $fail) {
                if (! $this->validHost((string) $value)) {
                    $fail('Enter a valid IP address or hostname.');
                }
            }],
            'collector_port' => [$requiredWhenEnabled, 'nullable', 'integer', 'between:1,65535'],
            'profile_uuids' => [$requiredWhenEnabled, 'array', 'min:1'],
            'profile_uuids.*' => ['uuid', 'distinct', 'exists:v_sip_profiles,sip_profile_uuid'],
        ];
    }

    private function validHost(string $host): bool
    {
        $host = trim($host);

        if ($host === '' || str_contains($host, '://') || str_contains($host, '/')) {
            return false;
        }

        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)) {
            return true;
        }

        return (bool) preg_match(
            '/^(?=.{1,253}\.?$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i',
            $host
        );
    }
}
