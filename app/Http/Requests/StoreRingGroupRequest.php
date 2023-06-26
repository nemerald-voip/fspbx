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
     *

ring_group_timeout_category:

ring_group_missed_call_category:
ring_group_missed_call_data:
ring_group_forward_toll_allow: Forwardtollallow
ring_group_forward_context: api.us.nemerald.net
     */

    public function rules(): array
    {
        return [
            'ring_group_name' => [
                'required'
            ],
            'ring_group_extension' => [
                'required',
                'RingGroupUnique:'.Session::get('domain_uuid')
            ],
            'ring_group_greeting' => [
                'required',
                'numeric',
                Rule::exists('App\Models\Recordings', 'recording_name')
                    ->where('domain_uuid', Session::get('domain_uuid')),
            ],

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
            'ring_group_destinations.*.prompt' => 'in:true,false',
            'ring_group_call_timeout' => [
                'required',
                'numeric'
            ],
            'ring_group_timeout_action' => [
                'string'
            ],
            'ring_group_cid_name_prefix' => [
                'string'
            ],
            'ring_group_cid_number_prefix' => [
                'string'
            ],
            'ring_group_description' => [
                'string'
            ],
            'ring_group_enabled' => 'in:true,false',
            'ring_group_forward_enabled' => 'in:true,false',
            'ring_group_forward.all.type' => [
                'required_if:ring_group_forward_all_enabled,==,true',
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
            'ring_group_timeout_category' => [
                'in:ringgroup,dialplans,extensions,timeconditions,voicemails,others'
            ],
            'ring_group_strategy' => [
                'in:simultaneous,sequence,random,enterprise,rollover'
            ],
            'ring_group_caller_id_name' => [
                'string'
            ],
            'ring_group_caller_id_number' => [
                'string'
            ],
            'ring_group_distinctive_ring' => [
                'string'
            ],
            /*'ring_group_ringback' => [

            ],*/
            'ring_group_call_forward_enabled' => 'in:true,false',
            'ring_group_follow_me_enabled' => 'in:true,false',
            'ring_group_missed_call_category' => [
                'in:email'
            ],
            'ring_group_missed_call_data' => [
                'string'
            ],
            'ring_group_forward_toll_allow' => [
                'string'
            ],
            'ring_group_forward_context' => [
                'required',
                'string',
                Rule::exists('App\Models\Domains', 'domain_name'),
            ],
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
