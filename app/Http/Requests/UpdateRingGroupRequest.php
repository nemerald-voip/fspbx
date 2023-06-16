<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UpdateRingGroupRequest extends FormRequest
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
            /*'ring_group_extension' => [
                'required',
                'RingGroupExists:'.Session::get('domain_uuid')
            ],
            'ring_group_greeting' => [

            ],
            'ring_group_strategy' => [
                'in:simultaneous,sequence,random,enterprise,rollover'
            ]*/
        ];
    }

    public function messages(): array
    {
        return [
            'RingGroupExists' => 'This number is already used',
        ];
    }
}
