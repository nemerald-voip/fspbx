<?php

namespace App\Http\Requests;

use App\Services\Messaging\MessageAutoReplyService;
use App\Services\Messaging\MessageSettingsAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ManageMessageAutoRepliesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('setting') && MessageSettingsAccess::canManage($this->route('setting')->domain_uuid);
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) return [];
        return [
            'enabled' => ['required', 'boolean'],
            'rules' => ['present', 'array', 'max:20'],
            'rules.*.trigger' => ['required', 'in:keyword,first,every'],
            'rules.*.enabled' => ['required', 'boolean'],
            'rules.*.keywords' => ['sometimes', 'nullable', 'array', 'max:30'],
            'rules.*.keywords.*' => ['required', 'string', 'max:100'],
            'rules.*.reply' => ['required', 'string', 'max:1600'],
        ];
    }

    public function attributes(): array
    {
        return [
            'enabled' => __('Enable auto-replies'),
            'rules' => __('Auto-replies'),
            'rules.*.trigger' => __('Reply when'),
            'rules.*.enabled' => __('Enabled'),
            'rules.*.keywords' => __('Keywords'),
            'rules.*.keywords.*' => __('Keyword'),
            'rules.*.reply' => __('Reply message'),
        ];
    }

    public function messages(): array
    {
        return [
            'rules.*.reply.required' => __('A reply message is required.'),
            'rules.*.reply.max' => __('The reply message must be 1,600 characters or fewer.'),
            'rules.*.trigger.required' => __('A reply condition is required.'),
            'rules.*.trigger.in' => __('Select a valid reply condition.'),
            'rules.*.keywords.*.required' => __('A keyword is required.'),
            'rules.*.keywords.*.max' => __('Each keyword must be 100 characters or fewer.'),
            'rules.*.keywords.max' => __('Use no more than 30 keywords per auto-reply.'),
            'rules.max' => __('Use no more than 20 auto-replies per phone number.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $service = app(MessageAutoReplyService::class);
            if (!$service->available() || !$service->eligible($this->route('setting'))) {
                $validator->errors()->add('enabled', __('Auto-replies are unavailable for this phone number.'));
            }
            if ($validator->errors()->isNotEmpty() || $this->isMethod('GET')) return;
            if ($this->boolean('enabled') && !collect($this->input('rules', []))->contains(fn ($rule) => (bool) $rule['enabled'])) {
                $validator->errors()->add('rules', __('Add and enable at least one auto-reply before enabling auto-replies.'));
            }
            $keywords = []; $triggers = [];
            foreach ($this->input('rules', []) as $index => $rule) {
                if ($rule['trigger'] === 'keyword') {
                    $values = array_values(array_unique(array_map(fn ($word) => mb_strtoupper(trim($word), 'UTF-8'), $rule['keywords'] ?? [])));
                    if (!$values || in_array('', $values, true)) {
                        $validator->errors()->add("rules.$index.keywords", __('Enter at least one keyword.'));
                    }
                    if ($rule['enabled'] && array_intersect($keywords, $values)) {
                        $validator->errors()->add("rules.$index.keywords", __('Each keyword can belong to only one enabled auto-reply.'));
                    }
                    if ($rule['enabled']) $keywords = array_merge($keywords, $values);
                } elseif ($rule['enabled']) {
                    if (in_array($rule['trigger'], $triggers, true)) {
                        $validator->errors()->add("rules.$index.trigger", __('Only one enabled auto-reply is allowed for this trigger.'));
                    }
                    $triggers[] = $rule['trigger'];
                }
            }
        });
    }
}
