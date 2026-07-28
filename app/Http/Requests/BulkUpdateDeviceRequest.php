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
     * Line settings that can be applied in bulk, mapped to their v_device_lines column.
     * These are "connectivity" attributes: identical across every line of a device, so
     * they need a single value rather than a per-line editor.
     */
    public const LINE_ATTRIBUTE_MAP = [
        'line_sip_port' => 'sip_port',
        'line_sip_transport' => 'sip_transport',
    ];

    /**
     * Inputs that modify how the line settings are applied, but are not written to a column.
     */
    public const LINE_CONTROL_FIELDS = [
        'line_scope',
        'line_numbers',
        'include_external_lines',
        'resync_devices',
    ];

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

            // Line settings (applied to v_device_lines rows of the selected devices)
            'line_scope' => [
                'nullable',
                Rule::in(['all', 'first', 'list']),
            ],
            'line_numbers' => [
                'nullable',
                'string',
                'max:255',
            ],
            'include_external_lines' => [
                'nullable',
                'boolean',
            ],
            'resync_devices' => [
                'nullable',
                'boolean',
            ],
            'line_sip_port' => [
                'nullable',
                'integer',
                'min:1',
                'max:65535',
            ],
            'line_sip_transport' => [
                'nullable',
                'string',
                Rule::in(['udp', 'tcp', 'tls', 'dns srv']),
            ],
        ];
    }

    /**
     * The line columns the caller opted into, keyed by database column name.
     */
    public function lineAttributes(): array
    {
        $attributes = [];

        foreach (self::LINE_ATTRIBUTE_MAP as $input => $column) {
            $value = $this->input($input);

            if ($value !== null && $value !== '') {
                $attributes[$column] = $value;
            }
        }

        return $attributes;
    }

    /**
     * Which lines of each selected device the update applies to.
     */
    public function lineScope(): array
    {
        return [
            'mode' => $this->input('line_scope') ?: 'all',
            'line_numbers' => $this->lineNumbers(),
            'include_external' => $this->boolean('include_external_lines'),
        ];
    }

    /**
     * Parse a line number expression such as "1,3-4" into ['1', '3', '4'].
     * line_number is a text column, so values are returned as strings.
     */
    public function lineNumbers(): array
    {
        $raw = trim((string) ($this->input('line_numbers') ?? ''));

        if ($raw === '') {
            return [];
        }

        $numbers = [];

        foreach (explode(',', $raw) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $matches)) {
                $start = (int) $matches[1];
                $end = (int) $matches[2];

                if ($start > $end) {
                    [$start, $end] = [$end, $start];
                }

                // Keep a pathological range like "1-99999" from exploding the query
                $end = min($end, $start + 99);

                for ($i = $start; $i <= $end; $i++) {
                    $numbers[] = (string) $i;
                }
            } elseif (ctype_digit($part)) {
                $numbers[] = (string) (int) $part;
            } else {
                // Unparseable token - surfaced as a validation error in withValidator()
                return [];
            }
        }

        return array_values(array_unique($numbers));
    }

    public function messages(): array
    {
        return [
            'items.required' => 'No items selected to update',
            'domain_uuid.required' => 'Acccount must be selected.',
            'device_key_template_uuid.exists' => 'Selected key template was not found.',
            'line_sip_port.integer' => 'SIP port must be a number between 1 and 65535.',
            'line_sip_port.min' => 'SIP port must be a number between 1 and 65535.',
            'line_sip_port.max' => 'SIP port must be a number between 1 and 65535.',
            'line_sip_transport.in' => 'Selected SIP transport is not supported.',
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

            if (empty($this->lineAttributes())) {
                return;
            }

            if ($this->input('line_scope') !== 'list') {
                return;
            }

            if (trim((string) $this->input('line_numbers')) === '') {
                $validator->errors()->add(
                    'line_numbers',
                    'Enter the line numbers to update, for example 1,3-4.'
                );
            } elseif (empty($this->lineNumbers())) {
                $validator->errors()->add(
                    'line_numbers',
                    'Line numbers must be digits or ranges separated by commas, for example 1,3-4.'
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
