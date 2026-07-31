<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && userCheckPermission('device_profile_add');
    }

    public function rules(): array
    {
        return [
            'domain_uuid' => ['nullable', 'uuid', 'exists:v_domains,domain_uuid'],
            'device_profile_name' => ['required', 'string', 'max:255'],
            'device_profile_enabled' => ['required', Rule::in(['true', 'false'])],
            'device_profile_description' => ['nullable', 'string', 'max:255'],

            'keys' => ['nullable', 'array'],
            'keys.*.device_profile_key_uuid' => ['nullable', 'uuid'],
            'keys.*.profile_key_category' => [
                'required',
                Rule::in([
                    'line',
                    'any',
                    'unassigned',
                    'blf',
                    'efk',
                    'speeddial',
                    'presense',
                    'presence',
                    'memory',
                    'programmable',
                    'expansion',
                    'expansion-1',
                    'expansion-2',
                    'expansion-3',
                    'expansion-4',
                    'expansion-5',
                    'expansion-6',
                ]),
            ],
            'keys.*.profile_key_id' => ['required', 'integer', 'min:1', 'max:255'],
            'keys.*.profile_key_vendor' => ['required', 'string', 'max:255'],
            'keys.*.profile_key_type' => ['required', 'string', 'max:255'],
            'keys.*.profile_key_subtype' => ['nullable', 'string', 'max:255'],
            'keys.*.profile_key_line' => ['nullable', 'integer', 'min:0', 'max:12'],
            'keys.*.profile_key_value' => ['nullable', 'string', 'max:255'],
            'keys.*.profile_key_extension' => ['nullable', 'string', 'max:255'],
            'keys.*.profile_key_protected' => ['nullable', Rule::in(['true', 'false'])],
            'keys.*.profile_key_label' => ['nullable', 'string', 'max:255'],
            'keys.*.profile_key_icon' => ['nullable', 'string', 'max:255'],

            'settings' => ['nullable', 'array'],
            'settings.*.device_profile_setting_uuid' => ['nullable', 'uuid'],
            'settings.*.profile_setting_name' => ['required', 'string', 'max:255'],
            'settings.*.profile_setting_value' => ['nullable', 'string', 'max:255'],
            'settings.*.profile_setting_enabled' => ['required', Rule::in(['true', 'false'])],
            'settings.*.profile_setting_description' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $domainUuid = $this->input('domain_uuid');

        $this->merge([
            'domain_uuid' => in_array($domainUuid, ['', '__global__'], true) ? null : $domainUuid,
            'device_profile_enabled' => $this->input('device_profile_enabled', 'true'),
        ]);
    }
}
