<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;

class UpdateBulkDeviceRequest extends FormRequest
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'device_profile_uuid' => [
                'nullable',
                Rule::exists('App\Models\DeviceProfile', 'device_profile_uuid')
                    ->where('domain_uuid', Session::get('domain_uuid'))
            ],
            'device_template' => [
                'nullable',
                'string',
            ],
            'devices' => [
                'required',
                'array',
            ],
            'devices.*' => [
                Rule::exists('App\Models\Devices', 'device_uuid')
                    ->where('domain_uuid', Session::get('domain_uuid'))
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'device_profile_uuid.required' => 'Profile is required',
            'devices.required' => 'Devices are required'
        ];
    }
}
