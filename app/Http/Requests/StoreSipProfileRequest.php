<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSipProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('sip_profile_add');
    }

    protected function prepareForValidation(): void
    {
        $settings = $this->input('settings');

        if (! is_array($settings)) {
            return;
        }

        foreach ($settings as &$setting) {
            if (! is_array($setting)) {
                continue;
            }

            $value = $setting['sip_profile_setting_value'] ?? null;

            if (is_int($value) || is_float($value)) {
                $setting['sip_profile_setting_value'] = (string) $value;
            }
        }
        unset($setting);

        $this->merge(['settings' => $settings]);
    }

    public function rules(): array
    {
        return $this->rulesForProfile();
    }

    protected function rulesForProfile(?string $ignoreUuid = null): array
    {
        return [
            'sip_profile_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('v_sip_profiles', 'sip_profile_name')->ignore($ignoreUuid, 'sip_profile_uuid'),
            ],
            'sip_profile_hostname' => ['nullable', 'string', 'max:255'],
            'sip_profile_enabled' => ['required', Rule::in(['true', 'false'])],
            'sip_profile_description' => ['nullable', 'string'],
            'domains' => ['array'],
            'domains.*.sip_profile_domain_uuid' => ['nullable', 'uuid'],
            'domains.*.sip_profile_domain_name' => ['nullable', 'string', 'max:255'],
            'domains.*.sip_profile_domain_alias' => ['nullable', Rule::in(['true', 'false'])],
            'domains.*.sip_profile_domain_parse' => ['nullable', Rule::in(['true', 'false'])],
            'settings' => ['array'],
            'settings.*.sip_profile_setting_uuid' => ['nullable', 'uuid'],
            'settings.*.sip_profile_setting_name' => ['nullable', 'string', 'max:255'],
            'settings.*.sip_profile_setting_value' => ['nullable', 'string', 'max:255'],
            'settings.*.sip_profile_setting_enabled' => ['nullable', Rule::in(['true', 'false'])],
            'settings.*.sip_profile_setting_description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
