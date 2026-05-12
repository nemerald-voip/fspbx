<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBasicQueueAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('call_center_agent_add');
    }

    public function rules(): array
    {
        return [
            'agent_name' => ['required', 'string', 'max:255'],
            'agent_type' => ['required', 'in:callback,uuid-standby'],
            'agent_call_timeout' => ['required', 'integer', 'min:1'],
            'agent_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('v_call_center_agents', 'agent_id')
                    ->where('domain_uuid', session('domain_uuid'))
                    ->ignore($this->agentUuid(), 'call_center_agent_uuid'),
            ],
            'agent_password' => ['nullable', 'string', 'max:255'],
            'agent_contact' => ['required', 'string', 'max:1024'],
            'agent_status' => ['nullable', 'in:Logged Out,Available,Available (On Demand),On Break'],
            'agent_no_answer_delay_time' => ['nullable', 'integer', 'min:0'],
            'agent_max_no_answer' => ['nullable', 'integer', 'min:0'],
            'agent_wrap_up_time' => ['nullable', 'integer', 'min:0'],
            'agent_reject_delay_time' => ['nullable', 'integer', 'min:0'],
            'agent_busy_delay_time' => ['nullable', 'integer', 'min:1'],
            'agent_record' => ['required', 'in:true,false'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'agent_type' => $this->input('agent_type', 'callback'),
            'agent_call_timeout' => $this->input('agent_call_timeout', 20),
            'agent_status' => blank($this->input('agent_status')) ? 'Logged Out' : $this->input('agent_status'),
            'agent_no_answer_delay_time' => $this->input('agent_no_answer_delay_time', 30),
            'agent_max_no_answer' => $this->input('agent_max_no_answer', 0),
            'agent_wrap_up_time' => $this->input('agent_wrap_up_time', 10),
            'agent_reject_delay_time' => $this->input('agent_reject_delay_time', 90),
            'agent_busy_delay_time' => $this->input('agent_busy_delay_time', 90),
            'agent_record' => $this->input('agent_record', 'true'),
        ]);
    }

    protected function agentUuid(): ?string
    {
        return null;
    }
}
