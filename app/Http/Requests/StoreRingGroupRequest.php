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

    /*
     * ring_group_extension: TestGroup
ring_group_extension: 345
ring_group_greeting:
ring_group_destinations[newrowcd12db79009e5][type]: external
ring_group_destinations[newrowcd12db79009e5][target_external]: 5642346578
ring_group_destinations[newrowcd12db79009e5][target_internal]: 0
ring_group_destinations[newrowcd12db79009e5][delay]: 0
ring_group_destinations[newrowcd12db79009e5][timeout]: 25
ring_group_destinations[newrowcd12db79009e5][prompt]: false
ring_group_call_timeout: 34
ring_group_strategy: dialplans
ring_group_timeout_data: simultaneous
ring_group_cid_name_prefix: cidname
ring_group_cid_number_prefix: cidnumber
ring_group_extension: description
ring_group_enabled: false
enabled: on
ring_group_forward_enabled: false
ring_group_forward_enabled: true
ring_group_forward[all][type]: external
ring_group_forward[all][target_external]: 8762349821
ring_group_forward[all][target_internal]: 0
ring_group_strategy: enterprise
ring_group_greeting:
ring_group_caller_id_name: callername
ring_group_caller_id_number: callernumber
ring_group_distinctive_ring: distinctinve
ring_group_ringback: ${us-ring}
ring_group_call_forward_enabled: false
ring_group_call_forward_enabled: on
ring_group_follow_me_enabled: false
ring_group_follow_me_enabled: on
ring_group_strategy: email
ring_group_missed_call_data: simultaneous
ring_group_forward_toll_allow: Forward Toll Allow
ring_group_forward_context: api.us.nemerald.net
     */

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
            'ring_group_forward.all.target_external' => 'Should be valid US phone number'
        ];
    }
}
