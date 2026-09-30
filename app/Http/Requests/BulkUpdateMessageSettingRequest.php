<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\Localization\ValidationMessages;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BulkUpdateMessageSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return \App\Services\Messaging\MessageSettingsAccess::canManage();
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
            'items' => [
                'required',
                'array'
            ],
            'items.*' => ['uuid', 'distinct', Rule::exists('v_sms_destinations', 'sms_destination_uuid')
                ->whereIn('domain_uuid', \App\Services\Messaging\MessageSettingsAccess::domains())],
            'allowed_extension_uuids' => ['sometimes', 'array'],
            'allowed_extension_uuids.*' => ['uuid', 'distinct', Rule::exists('v_extensions', 'extension_uuid')->where('domain_uuid', session('domain_uuid'))],
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
                'nullable',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            ...ValidationMessages::common(),
            'items.required' => __('No items selected to update'),
        ];
    }

    protected function prepareForValidation()
    {
        $merge = [];

        // if (!$this->has('domain_uuid')) {
        //     $merge['domain_uuid'] = session('domain_uuid');
        // }

        $this->merge($merge);
    }

    public function attributes(): array
    {
        return [
            'items' => __('Selected items'),
            'carrier' => __('Message Provider'),
            'chatplan_detail_data' => __('Extension'),
            'email' => __('Email'),
            'description' => __('Description'),
            'domain_uuid' => __('Account'),
        ];
    }
}
