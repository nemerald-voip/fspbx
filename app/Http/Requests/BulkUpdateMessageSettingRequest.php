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
            'items' => [
                'required',
                'array'
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
