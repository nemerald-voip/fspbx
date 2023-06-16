<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class StoreRingGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'ring_group_extension' => [
                'required',
                'RingGroupUnique:'.Session::get('domain_uuid')
            ],
            /*'ring_group_greeting' => [

            ],*/
            'ring_group_strategy' => [
                'in:simultaneous,sequence,random,enterprise,rollover'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'ring_group_extension.required' => 'RingGroup number is required',
            'ring_group_extension.RingGroupExists' => 'This number is already used',
        ];
    }
}
