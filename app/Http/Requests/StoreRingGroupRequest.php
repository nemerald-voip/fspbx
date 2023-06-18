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
            ],
            'ring_group_ringback' => [

            ],
            'ring_group_forward_enabled' => 'in:true,false',
            'ring_group_forward.all.type' => [
                'required_if:ring_group_forward_enabled,==,true',
                'in:external,internal'
            ],
            'ring_group_forward.all.target_external' => [
                'required_if:ring_group_forward.all.type,==,external',
                'nullable',
                'phone:US',
            ],
            'ring_group_forward.all.target_internal' => [
                'required_if:ring_group_forward.all.type,==,internal',
                'nullable',
                'numeric',
                'ExtensionExists:'.Session::get('domain_uuid')
            ],
            'ring_group_follow_me_enabled' => 'in:true,false',
            'ring_group_ring_my_phone_timeout' => 'nullable|numeric',
            'ring_group_destinations' => 'nullable|array',
            'ring_group_destinations.*.target_external' => [
                'required_if:ring_group_destinations.*.type,==,external',
                'nullable',
                'phone:US',
            ],
            'ring_group_destinations.*.target_internal' => [
                'required_if:ring_group_destinations.*.type,==,internal',
                'nullable',
                'numeric',
                'ExtensionExists:'.Session::get('domain_uuid')
            ],
            'ring_group_destinations.*.delay' => 'numeric',
            'ring_group_destinations.*.timeout' => 'numeric',
            'ring_group_destinations.*.prompt' => 'in:true,false'
        ];
    }

    public function messages(): array
    {
        return [
            'ring_group_extension.required' => 'RingGroup number is required',
            'ring_group_extension.RingGroupExists' => 'This number is already used',
            'ring_group_destinations.*.target_external.phone' => 'Should be valid US phone number or extension id',
            'ring_group_destinations.*.target_external.required_if' => 'This is the required field',
            'ring_group_destinations.*.target_internal.ExtensionExists' => 'Should be valid destination',
        ];
    }
}
