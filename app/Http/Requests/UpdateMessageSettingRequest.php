<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMessageSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return \App\Services\Messaging\MessageSettingsAccess::canManage($this->route('setting')?->domain_uuid);
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
                Rule::exists('v_extensions', 'extension')->where('domain_uuid', $this->route('setting')?->domain_uuid),
            ],
            'allowed_extension_uuids' => ['sometimes', 'array'],
            'allowed_extension_uuids.*' => ['uuid', 'distinct', Rule::exists('v_extensions', 'extension_uuid')->where('domain_uuid', $this->route('setting')?->domain_uuid)],
            'email' => [
                'nullable',
                'email:rfc,dns'
            ],
            'description' => [
                'nullable',
                'string'
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

        if ($this->has('destination')) {
            $merge['destination'] = formatPhoneNumber($this->input('destination'),'US', PhoneNumberFormat::E164);
        }

        $this->merge($merge);
    }
}
