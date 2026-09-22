<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
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
            'items.required' => 'No items selected to update',
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
}
