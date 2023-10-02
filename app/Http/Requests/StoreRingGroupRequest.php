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
            'ring_group_name' => [
                'required'
            ],
            'ring_group_extension' => [
                'required',
                'RingGroupUnique:'.Session::get('domain_uuid')
            ],
            'ring_group_greeting' => [
                'nullable',
                'string',
                Rule::exists('App\Models\Recordings', 'recording_filename')
                    ->where('domain_uuid', Session::get('domain_uuid')),
            ],
            'ring_group_destinations.*.type' => [
                'in:external,internal'
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
                'nullable',
                'string'
            ],
            'ring_group_cid_name_prefix' => [
                'nullable',
                'string'
            ],
            'ring_group_cid_number_prefix' => [
                'nullable',
                'string'
            ],
            'ring_group_description' => [
                'nullable',
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
                'required_if:ring_group_forward_enabled,==,true,ring_group_forward.all.type,==,internal',
                'nullable',
                'numeric',
                'ExtensionExists:'.Session::get('domain_uuid')
            ],
            'timeout_category' => [
                'in:disabled,ringgroup,dialplans,extensions,timeconditions,voicemails,others'
            ],
            'timeout_action_ringgroup' => [
                'required_if:timeout_category,==,ringgroup',
                'string'
            ],
            'timeout_action_dialplans' => [
                'required_if:timeout_category,==,dialplans',
                'string'
            ],
            'timeout_action_extensions' => [
                'required_if:timeout_category,==,extensions',
                'string'
            ],
            'timeout_action_voicemails' => [
                'required_if:timeout_category,==,voicemails',
                'string'
            ],
            'timeout_action_others' => [
                'required_if:timeout_category,==,others',
                'string'
            ],
            'ring_group_timeout_data' => [
                'nullable',
                'string'
            ],
            'ring_group_strategy' => [
                'in:simultaneous,sequence,random,enterprise,rollover'
            ],
            'ring_group_caller_id_name' => [
                'nullable',
                'string'
            ],
            'ring_group_caller_id_number' => [
                'nullable',
                'string',
                'phone:US'
            ],
            'ring_group_distinctive_ring' => [
                'nullable',
                'string'
            ],
            'ring_group_ringback' => [
                'nullable',
                'string'
            ],
            'ring_group_call_forward_enabled' => 'in:true,false',
            'ring_group_follow_me_enabled' => 'in:true,false',
            'ring_group_missed_call_category' => [
                'in:disabled,email'
            ],
            'ring_group_missed_call_data' => [
                'required_if:ring_group_missed_call_category,==,email',
                'nullable',
                'string',
                'email'
            ],
            'ring_group_forward_toll_allow' => [
                'nullable',
                'string'
            ],
            'ring_group_context' => [
                'required',
                'string',
                Rule::exists('App\Models\Domain', 'domain_name'),
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

    public function prepareForValidation()
    {
        if($this->get('ring_group_greeting') == 'disabled') {
            $this->merge([
                'ring_group_greeting' => null
            ]);
        }
        if($this->get('timeout_category') == 'disabled') {
            $this->merge([
                'ring_group_timeout_data' => null
            ]);
        } else {
            $this->merge([
                'ring_group_timeout_data' => $this->get('timeout_action_'.$this->get('timeout_category'))
            ]);
        }
    }
}
