<?php

namespace App\Http\Requests;

use App\Services\DialplanService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDialplanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('dialplan_add');
    }

    public function rules(): array
    {
        return [
            'domain_uuid' => ['nullable', 'uuid'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'dialplan_name' => ['required', 'string', 'max:255'],
            'dialplan_number' => ['nullable', 'string', 'max:255'],
            'dialplan_destination' => ['nullable', 'in:true,false'],
            'dialplan_context' => ['required', 'string', 'max:255'],
            'dialplan_continue' => ['required', 'in:true,false'],
            'dialplan_order' => ['required', 'integer', 'min:0', 'max:999'],
            'dialplan_enabled' => ['required', 'in:true,false'],
            'dialplan_description' => ['nullable', 'string', 'max:255'],
            'editor_mode' => ['nullable', 'in:builder,xml'],
            'dialplan_xml' => ['nullable', 'string'],
            'dialplan_details' => ['nullable', 'array'],
            'dialplan_details.*.dialplan_detail_uuid' => ['nullable', 'uuid'],
            'dialplan_details.*.dialplan_detail_tag' => ['nullable', 'in:condition,regex,action,anti-action'],
            'dialplan_details.*.dialplan_detail_type' => ['nullable', 'string', 'max:255'],
            'dialplan_details.*.dialplan_detail_data' => ['nullable', 'string', 'max:4096'],
            'dialplan_details.*.dialplan_detail_break' => ['nullable', 'in:on-true,on-false,always,never'],
            'dialplan_details.*.dialplan_detail_inline' => ['nullable', 'in:true,false'],
            'dialplan_details.*.dialplan_detail_group' => ['nullable', 'integer', 'min:0', 'max:999'],
            'dialplan_details.*.dialplan_detail_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'dialplan_details.*.dialplan_detail_enabled' => ['nullable', 'in:true,false'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $service = app(DialplanService::class);

            if ($this->input('editor_mode') === 'xml') {
                foreach ($service->validateXml((string) $this->input('dialplan_xml')) as $message) {
                    $validator->errors()->add('dialplan_xml', $message);
                }

                return;
            }

            $details = $service->normalizedDetails($this->input('dialplan_details', []));

            if (empty($details)) {
                $validator->errors()->add('dialplan_details', 'Add at least one dialplan condition or action.');
                return;
            }

            foreach ($details as $index => $detail) {
                $tag = $detail['dialplan_detail_tag'];
                $isBlankCondition = in_array($tag, ['condition', 'regex'], true)
                    && blank($detail['dialplan_detail_type'])
                    && blank($detail['dialplan_detail_data']);

                if (!$isBlankCondition && blank($detail['dialplan_detail_type'])) {
                    $validator->errors()->add("dialplan_details.{$index}.dialplan_detail_type", 'Type is required.');
                }

                if (!$isBlankCondition && blank($detail['dialplan_detail_data']) && $tag !== 'action' && $tag !== 'anti-action') {
                    $validator->errors()->add("dialplan_details.{$index}.dialplan_detail_data", 'Data is required.');
                }

                if (in_array($tag, ['action', 'anti-action'], true)
                    && ($service->containsDangerousApplication($detail['dialplan_detail_type'])
                        || $service->containsDangerousApplication($detail['dialplan_detail_data']))) {
                    $validator->errors()->add("dialplan_details.{$index}.dialplan_detail_type", 'This FreeSWITCH application is not allowed.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'domain_uuid' => blank($this->input('domain_uuid')) ? null : $this->input('domain_uuid'),
            'dialplan_destination' => $this->input('dialplan_destination', 'false') ?: 'false',
            'dialplan_continue' => $this->input('dialplan_continue', 'false') ?: 'false',
            'dialplan_enabled' => $this->input('dialplan_enabled', 'true') ?: 'true',
            'editor_mode' => $this->input('editor_mode', 'builder') ?: 'builder',
        ]);
    }
}
