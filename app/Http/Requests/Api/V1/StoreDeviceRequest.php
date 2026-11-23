<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Devices;
use Illuminate\Validation\Rule;

class StoreDeviceRequest extends \App\Http\Requests\StoreDeviceRequest
{
    use ValidatesDeviceReferences;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = array_replace(parent::rules(), $this->deviceReferenceRules());

        // API v1 has route-based account authorization and no SPA session context.
        $rules['device_key_template_uuid'] = [
            'nullable',
            Rule::when(
                fn ($input) => ($input['device_key_template_uuid'] ?? null) !== 'NULL',
                Rule::exists('device_key_templates', 'device_key_template_uuid')
                    ->where('domain_uuid', (string) $this->route('domain_uuid')),
            ),
        ];

        unset(
            $rules['device_address_modified'],
            $rules['device_vendor'],
            $rules['device_lines'],
            $rules['device_lines.*.line_type_id'],
            $rules['device_lines.*.auth_id'],
            $rules['device_lines.*.line_number'],
            $rules['device_lines.*.display_name'],
            $rules['device_lines.*.server_address'],
            $rules['device_lines.*.server_address_primary'],
            $rules['device_lines.*.server_address_secondary'],
            $rules['device_lines.*.outbound_proxy_primary'],
            $rules['device_lines.*.outbound_proxy_secondary'],
            $rules['device_lines.*.sip_port'],
            $rules['device_lines.*.sip_transport'],
            $rules['device_lines.*.register_expires'],
            $rules['device_lines.*.device_line_uuid'],
            $rules['device_provisioning'],
            $rules['domain_uuid'],
            $rules['device_enabled'],
        );

        return $rules;
    }

    public function withValidator($validator): void
    {
        parent::withValidator($validator);
        $validator->after(function ($validator) {
            $normalizedMac = (string) $this->input('device_address_modified');

            if ($normalizedMac === '') {
                return;
            }

            $exists = Devices::query()
                ->where('device_address', $normalizedMac)
                ->exists();

            if ($exists) {
                $validator->errors()->add('device_address', 'Duplicate MAC address has been found');
            }
        });
    }

    public function prepareForValidation(): void
    {
        $this->merge(['domain_uuid' => $this->route('domain_uuid')]);
        if ($this->input('device_profile_uuid') === 'NULL') {
            $this->merge(['device_profile_uuid' => null]);
        }

        parent::prepareForValidation();
    }

    public function bodyParameters(): array
    {
        return [
            'device_address' => [
                'description' => 'MAC address for the device.',
                'example' => '0004f23a5bc7',
            ],
            'serial_number' => [
                'description' => 'Optional device serial number.',
                'example' => '8603123456789',
            ],
            'device_profile_uuid' => [
                'description' => 'Optional device profile UUID.',
                'example' => '51759db8-c8bf-4b2f-b48a-6577d7ad6a1a',
            ],
            'device_template' => [
                'description' => 'Deprecated legacy template identifier. Prefer device_template_uuid.',
                'example' => 'Yealink T46U',
            ],
            'device_template_uuid' => [
                'description' => 'Optional provisioning template UUID.',
                'example' => 'a6cf59ba-4b2b-4bdd-b870-35cc55bca146',
            ],
            'device_keys' => [
                'description' => 'Optional device key assignments.',
                'example' => [],
            ],
            'phonebook_mode' => [
                'description' => 'Use account-default phonebooks or a custom list.',
                'example' => 'default',
            ],
            'phonebook_uuids' => [
                'description' => 'Phonebook UUIDs to assign when phonebook_mode is custom.',
                'example' => [],
            ],
            'device_description' => [
                'description' => 'Optional label or description for the device.',
                'example' => 'Reception desk device',
            ],
        ];
    }
}
