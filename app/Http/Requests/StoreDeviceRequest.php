<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\ProvisioningTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreDeviceRequest extends FormRequest
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
            'device_address' => [
                'required',
                'mac_address',
            ],
            'serial_number' => [
                'nullable',
                'string',
            ],
            'device_address_modified' => [
                'nullable',
                Rule::unique('v_devices', 'device_address'),
            ],
            'device_profile_uuid' => [
                'nullable',
                Rule::when(
                    function ($input) {
                        // Check if the value is not the literal string "NULL"
                        return $input['device_profile_uuid'] !== 'NULL';
                    },
                    Rule::exists('App\Models\DeviceProfile', 'device_profile_uuid'),
                )
            ],

            // LEGACY template path (kept only when not a UUID)
            'device_template' => ['nullable', 'string'],

            // NEW: DB template pointer (populated from device_template if it's a UUID)
            'device_template_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('provisioning_templates', 'template_uuid'),
            ],

            'device_vendor' => ['nullable', 'string', 'max:100'],

            'device_lines' => [
                'nullable',
                'array'
            ],
            // Required fields for each key:
            'device_lines.*.line_type_id' => ['required', 'string'],
            'device_lines.*.auth_id' => ['required', 'string'],
            'device_lines.*.line_number' => ['required', 'numeric'],

            // These fields can be null/empty:
            'device_lines.*.display_name' => ['nullable'],
            'device_lines.*.server_address' => ['nullable'],
            'device_lines.*.server_address_primary' => ['nullable'],
            'device_lines.*.server_address_secondary' => ['nullable'],
            'device_lines.*.outbound_proxy_primary' => ['nullable'],
            'device_lines.*.outbound_proxy_secondary' => ['nullable'],
            'device_lines.*.sip_port' => ['nullable'],
            'device_lines.*.sip_transport' => ['nullable'],
            'device_lines.*.register_expires' => ['nullable'],
            'device_lines.*.device_line_uuid' => ['nullable'],
            
            'device_provisioning' => [
                'boolean'
            ],
            'domain_uuid' => [
                'required',
            ],
            'device_description' => [
                'nullable',
            ],
            'device_enabled' => [
                'nullable',
            ],
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator)
    {
        // Get the original error messages from the validator
        $errors = $validator->errors();

        // Check if the specific error for device_address_modified.unique exists
        if ($errors->has('device_address_modified')) {
            // Add the error to the device_address field instead
            $errors->add('device_address', $errors->first('device_address_modified'));

            // Optionally, remove the error from device_address_modified if it should only be reported under device_address
            $errors->forget('device_address_modified');
        }

        $responseData = array('errors' => $errors);

        throw new HttpResponseException(response()->json($responseData, 422));
    }

    public function messages(): array
    {
        return [
            'device_address.required' => 'MAC address is required',
            'device_address.mac_address' => 'MAC address is invalid',
            'device_profile_uuid.required' => 'Profile is required',
            'device_template.required' => 'Template is required',
            'device_address_modified.unique' => 'Duplicate MAC address has been found',
            'device_template_uuid.uuid'   => 'Selected template is invalid.',
            'device_template_uuid.exists' => 'Selected template was not found.',
        ];
    }

    public function prepareForValidation(): void
    {
        // Normalize MAC
        $macAddress = strtolower(trim(tokenizeMacAddress($this->input('device_address') ?? '')));
        $this->merge([
            'device_address'          => formatMacAddress($macAddress),
            'device_address_modified' => $macAddress,
        ]);
    
        // Default domain
        if (!$this->has('domain_uuid')) {
            $this->merge(['domain_uuid' => session('domain_uuid')]);
        }

        if (!$this->has('device_enabled')) {
            $this->merge(['device_enabled' => 'true']);
        }
    
        // Normalize serial
        $serialInput = $this->input('serial_number');
        if ($serialInput !== null && $serialInput !== '') {
            $serialNorm = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $serialInput));
            $this->merge(['serial_number' => $serialNorm !== '' ? $serialNorm : null]);
        }
    
        // Map device_template → device_template_uuid when UUID is sent
        $incoming = $this->input('device_template');
        if (is_string($incoming) && Str::isUuid($incoming)) {
            $this->merge([
                'device_template_uuid' => $incoming,
                'device_template'      => null,   // clear legacy path
            ]);
        } elseif (!$this->has('device_template_uuid')) {
            $this->merge(['device_template_uuid' => null]);
        }
    
        // Derive device_vendor from UUID or legacy path
        $vendor = null;
    
        // Prefer UUID → look up vendor from DB
        $tplUuid = $this->input('device_template_uuid');  
        if (is_string($tplUuid) && Str::isUuid($tplUuid)) {
            $v = ProvisioningTemplate::query()
                ->where('template_uuid', $tplUuid)
                ->value('vendor');
            if (is_string($v) && $v !== '') {
                $vendor = strtolower($v);
            }
        }

        // Fallback: legacy path prefix "<vendor>/<template>"
        if (!$vendor) {
            $legacy = $this->input('device_template');
            if (is_string($legacy) && strpos($legacy, '/') !== false) {
                [$vPrefix] = explode('/', $legacy, 2);
                if ($vPrefix !== '') {
                    $vendor = strtolower($vPrefix);
                }
            }
        }
    
        // Normalize vendor aliases
        if ($vendor) {
            if ($vendor === 'poly') $vendor = 'polycom';
            $this->merge(['device_vendor' => $vendor]);
        }
    }
}
