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
