<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Foundation\Http\FormRequest;

class CreateMessageSettingRequest extends FormRequest
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
        // logger('validation');
        // logger(request()->all());
        return [
            'destination' => [
                'required',
            ],
            'carrier' => [
                'nullable',
            ],
            'chatplan_detail_data' => [
                'nullable',
            ],
            'email' => [
                'nullable',
                'email:rfc,dns'
            ],
            'description' => [
                'nullable',
                'string'
            ],
            'domain_uuid' => [
                'required',
            ],
            'enabled' => [
                'nullable',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            // 'device_profile_uuid.required' => 'Profile is required',
            // 'device_template.required' => 'Template is required'
        ];
    }

    protected function prepareForValidation()
    {
        $merge = [];

        if (!$this->has('domain_uuid')) {
            $merge['domain_uuid'] = session('domain_uuid');
        }

        if (!$this->has('enabled')) {
            $merge['enabled'] = "true";
        }

        if ($this->has('destination')) {
            $merge['destination'] = formatPhoneNumber($this->input('destination'),'US', PhoneNumberFormat::E164);
        }

        $this->merge($merge);
    }
}
