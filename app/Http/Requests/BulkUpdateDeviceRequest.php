<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\ProvisioningTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateDeviceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [

            'items' => 'required',
            
            'device_profile_uuid' => [
                'nullable',
                Rule::when(
                    function ($input) {
                        // Check if the value is not the literal string "NULL"
                        return ($input['device_profile_uuid'] ?? null) !== 'NULL';
                    },
                    Rule::exists('App\Models\DeviceProfile', 'device_profile_uuid'),
                )
            ],
            'device_key_template_uuid' => [
                'nullable',
                Rule::when(
                    fn ($input) => ($input['device_key_template_uuid'] ?? null) !== 'NULL',
                    Rule::exists('device_key_templates', 'device_key_template_uuid')
                        ->where('domain_uuid', session('domain_uuid')),
                ),
            ],
            'device_template' => [
                'nullable',
                'string',
            ],
            'device_template_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('provisioning_templates', 'template_uuid'),
            ],
            'device_vendor' => [
                'nullable',
                'string',
                'max:100',
            ],
            // 'device_keys' => [
            //     'nullable',
            //     'array'
            // ],
            // // Required fields for each key:
            // 'device_keys.*.line_type_id' => ['required', 'string'],
            // 'device_keys.*.auth_id' => ['required', 'string'],
            // 'device_keys.*.line_number' => ['required', 'numeric'],

            // // These fields can be null/empty:
            // 'device_keys.*.display_name' => ['nullable'],
            // 'device_keys.*.server_address' => ['nullable'],
            // 'device_keys.*.server_address_primary' => ['nullable'],
            // 'device_keys.*.server_address_secondary' => ['nullable'],
            // 'device_keys.*.sip_port' => ['nullable'],
            // 'device_keys.*.sip_transport' => ['nullable'],
            // 'device_keys.*.register_expires' => ['nullable'],
            // 'device_keys.*.domain_uuid' => ['nullable'],
            // 'device_keys.*.device_line_uuid' => ['nullable'],
            // 'device_keys.*.user_id' => ['nullable'],
            
            'domain_uuid' => [
                'nullable',
            ],
            'device_description' => [
                'nullable',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'No items selected to update',
            'domain_uuid.required' => 'Acccount must be selected.',
            'device_key_template_uuid.exists' => 'Selected key template was not found.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (
                $this->assignmentSelected($this->input('device_profile_uuid'))
                && $this->assignmentSelected($this->input('device_key_template_uuid'))
            ) {
                $validator->errors()->add(
                    'device_key_template_uuid',
                    'Choose either a key template or a device profile, not both.'
                );
            }
        });
    }

    private function assignmentSelected(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        return !in_array((string) $value, ['', 'NULL'], true);
    }

    public function prepareForValidation(): void
    {
        $incoming = $this->input('device_template');
        if (is_string($incoming) && Str::isUuid($incoming)) {
            $this->merge([
                'device_template_uuid' => $incoming,
                'device_template' => null,
            ]);
        } elseif ($this->has('device_template') && ! $this->has('device_template_uuid')) {
            $this->merge(['device_template_uuid' => null]);
        }

        $vendor = null;

        $templateUuid = $this->input('device_template_uuid');
        if (is_string($templateUuid) && Str::isUuid($templateUuid)) {
            $templateVendor = ProvisioningTemplate::query()
                ->where('template_uuid', $templateUuid)
                ->value('vendor');

            if (is_string($templateVendor) && $templateVendor !== '') {
                $vendor = strtolower($templateVendor);
            }
        }

        if (! $vendor && is_string($incoming) && str_contains($incoming, '/')) {
            [$vendorPrefix] = explode('/', $incoming, 2);
            if ($vendorPrefix !== '') {
                $vendor = strtolower($vendorPrefix);
            }
        }

        if ($vendor === 'poly') {
            $vendor = 'polycom';
        }

        if ($vendor) {
            $this->merge(['device_vendor' => $vendor]);
        }
    }
}
