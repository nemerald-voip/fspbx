<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Devices;
use Illuminate\Support\Str;
use App\Models\ProvisioningTemplate;

class UpdateDeviceRequest extends \App\Http\Requests\UpdateDeviceRequest
{
    use ValidatesDeviceReferences;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_address' => ['sometimes', 'filled', 'mac_address'],
            'serial_number' => ['sometimes', 'nullable', 'string'],
            ...$this->deviceReferenceRules(),
            'device_template' => ['sometimes', 'nullable', 'string'],
            'device_description' => ['sometimes', 'nullable'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $normalizedMac = (string) $this->input('device_address_modified');

            if ($normalizedMac === '') {
                return;
            }

            $exists = Devices::query()
                ->where('device_address', $normalizedMac)
                ->where('device_uuid', '!=', (string) $this->route('device_uuid'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('device_address', 'Duplicate MAC address has been found');
            }
        });
    }

    public function prepareForValidation(): void
    {
        if ($this->input('device_profile_uuid') === 'NULL') {
            $this->merge(['device_profile_uuid' => null]);
        }

        if ($this->has('device_address')) {
            $macAddress = strtolower(trim(tokenizeMacAddress($this->input('device_address') ?? '')));
            $this->merge([
                'device_address' => formatMacAddress($macAddress),
                'device_address_modified' => $macAddress,
            ]);
        }

        if ($this->has('serial_number')) {
            $serialInput = $this->input('serial_number');
            if ($serialInput !== null && $serialInput !== '') {
                $serialNorm = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $serialInput));
                $this->merge(['serial_number' => $serialNorm !== '' ? $serialNorm : null]);
            }
        }

        if ($this->has('device_template')) {
            $incoming = $this->input('device_template');
            if (is_string($incoming) && Str::isUuid($incoming)) {
                $this->merge([
                    'device_template_uuid' => $incoming,
                    'device_template' => null,
                ]);
            } elseif (! $this->has('device_template_uuid')) {
                $this->merge(['device_template_uuid' => null]);
            }
        }

        $vendor = null;

        $tplUuid = $this->input('device_template_uuid');
        if (is_string($tplUuid) && Str::isUuid($tplUuid)) {
            $resolvedVendor = ProvisioningTemplate::visibleToDomain((string) $this->route('domain_uuid'))
                ->whereKey($tplUuid)
                ->value('vendor');

            if (is_string($resolvedVendor) && $resolvedVendor !== '') {
                $vendor = strtolower($resolvedVendor);
            }
        }

        if (! $vendor && $this->has('device_template')) {
            $legacy = $this->input('device_template');
            if (is_string($legacy) && strpos($legacy, '/') !== false) {
                [$vendorPrefix] = explode('/', $legacy, 2);
                if ($vendorPrefix !== '') {
                    $vendor = strtolower($vendorPrefix);
                }
            }
        }

        if ($vendor) {
            if ($vendor === 'poly') {
                $vendor = 'polycom';
            }

            $this->merge(['device_vendor' => $vendor]);
        }
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
            'device_description' => [
                'description' => 'Optional label or description for the device.',
                'example' => 'Reception desk device',
            ],
        ];
    }
}
