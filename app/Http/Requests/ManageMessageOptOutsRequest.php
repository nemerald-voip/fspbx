<?php

namespace App\Http\Requests;

use App\Services\Messaging\MessageOptOutService;
use App\Services\Messaging\MessageSettingsAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ManageMessageOptOutsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('message_opt_out_manage')
            && in_array($this->route('setting')?->domain_uuid, MessageSettingsAccess::domains(), true);
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
        ];
        if ($this->isMethod('DELETE')) return [];
        return ['action' => ['required', 'in:add,remove'], 'numbers' => ['required', 'string', 'max:30000']];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $service = app(MessageOptOutService::class);
            if (!$service->available() || !$service->eligible($this->route('setting'))) {
                $validator->errors()->add('numbers', __('Opt-out handling is disabled or unavailable for this account.'));
            }
        });
    }
}
