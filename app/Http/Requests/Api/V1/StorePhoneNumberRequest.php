<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePhoneNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        // API uses middleware for permissions + domain scope
        return true;
    }

    public function rules(): array
    {
        $domainUuid = (string) $this->route('domain_uuid');

        return [
            'destination_number' => [
                'required',
                'string',
                'regex:/^\+?\d+$/',
                Rule::unique('v_destinations', 'destination_number')->where(fn ($q) => $q->where('domain_uuid', $domainUuid)),
            ],

            'destination_prefix' => ['nullable', 'string', 'max:5'],

            'destination_description' => ['nullable', 'string', 'max:150'],

            'destination_record' => ['sometimes', 'boolean'],
            'destination_type_fax' => ['sometimes', 'boolean'],

            'destination_hold_music' => ['nullable', 'string', 'max:120'],
            'destination_distinctive_ring' => ['nullable', 'string', 'max:30'],
            'destination_cid_name_prefix' => ['nullable', 'string', 'max:20'],
            'destination_accountcode' => ['nullable', 'string', 'max:50'],
            'fax_uuid' => ['nullable', 'uuid', Rule::exists('v_fax', 'fax_uuid')],
            'destination_enabled' => ['sometimes', 'boolean'],

            'routing_options' => ['nullable', 'array'],
            'routing_options.*.type' => [
                'required_with:routing_options',
                Rule::in([
                    'extensions',
                    'ring_groups',
                    'ivrs',
                    'business_hours',
                    'contact_centers',
                    'faxes',
                    'conferences',
                    'call_flows',
                    'voicemails',
                    'company_directory',
                    'check_voicemail',
                    'hangup',
                ]),
            ],
            'routing_options.*.extension' => [
                'present',
                function ($attribute, $value, $fail) {
                    $type = data_get($this->input(), str_replace('.extension', '.type', $attribute));

                    // These types don't require extension digits
                    if (in_array($type, ['hangup'], true)) {
                        return;
                    }

                    if ($value === null || $value === '') {
                        $fail('The extension is required for this routing option.');
                        return;
                    }

                    if (!preg_match('/^\+?\d+$/', (string) $value)) {
                        $fail('The extension must contain only digits and may include a single leading +.');
                    }
                },
            ],
        ];
    }

    public function prepareForValidation(): void
    {
        // normalize destination_number: keep digits only (and optional leading +), then strip +1 if present
        if ($this->has('destination_number')) {
            $v = preg_replace('/[^\d+]+/', '', (string) $this->input('destination_number'));
            $v = ltrim($v);
            if (str_starts_with($v, '+1')) $v = substr($v, 2);
            $v = ltrim($v, '+'); // store as digits only, like your example
            $this->merge(['destination_number' => $v]);
        }


        // normalize routing_options.extension
        $routing = $this->input('routing_options', []);
        if (is_array($routing) && count($routing)) {
            foreach ($routing as $i => $r) {
                if (array_key_exists('extension', $r) && $r['extension'] !== null && $r['extension'] !== '') {
                    $v = preg_replace('/[^\d+]+/', '', (string) $r['extension']);
                    // allow one leading +
                    $hadPlus = str_starts_with($v, '+');
                    $v = str_replace('+', '', $v);
                    $routing[$i]['extension'] = $hadPlus ? ('+' . $v) : $v;
                }
            }
            $this->merge(['routing_options' => $routing]);
        }
    }

    public function bodyParameters(): array
    {
        return [
            'destination_number' => [
                'description' => 'Phone number. Digits and + only',
                'example' => '5023883937',
            ],
            'destination_prefix' => [
                'description' => 'Optional country prefix.',
                'example' => '1',
            ],
            'destination_enabled' => [
                'description' => 'Whether the phone number is enabled. Default: true',
                'example' => true,
            ],
            'destination_description' => [
                'description' => 'Optional label/description.',
                'example' => 'Main inbound line',
            ],
            'fax_uuid' => [
                'description' => 'Optional fax UUID. Helps with fax detection',
                'example' => '9a458dfd-a989-4e77-ab1d-711f5ecd35cb',
            ],
            'destination_record' => [
                'description' => 'Whether to record calls for this phone number.',
                'example' => false,
            ],
            'destination_type_fax' => [
                'description' => 'Activate this setting if calls will be routed directly to a physical fax machine. This ensures proper handling of fax transmissions.',
                'example' => false,
            ],
            'destination_hold_music' => [
                'description' => 'Optional hold music value.',
                'example' => null,
            ],
            'destination_distinctive_ring' => [
                'description' => 'Optional distinctive ring value.',
                'example' => 'ring4',
            ],
            'destination_cid_name_prefix' => [
                'description' => 'Optional caller ID name prefix.',
                'example' => 'Sales',
            ],
            'destination_accountcode' => [
                'description' => 'Optional account code.',
                'example' => '23435456',
            ],
            'routing_options' => [
                'description' => 'Optional call routing options.',
                'example' => [
                    ['type' => 'ring_groups', 'extension' => '9000'],
                ],
            ],
            'routing_options.*.type' => [
                'description' => 'Routing type.',
                'example' => 'ring_groups',
            ],
            'routing_options.*.extension' => [
                'description' => 'Digits target for this routing type (extension or external).',
                'example' => '9000',
            ],
        ];
    }
}
