<?php

namespace App\Http\Requests;

use App\Models\CallWebhookSubscription;
use App\Services\CallWebhooks\PublicWebhookUrlValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class SaveCallWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $exists = CallWebhookSubscription::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->exists();

        return userCheckPermission($exists ? 'call_webhook_update' : 'call_webhook_create');
    }

    public function rules(): array
    {
        return [
            'endpoint_url' => ['required', 'url', 'max:2048'],
            'enabled' => ['required', 'boolean'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', Rule::in(CallWebhookSubscription::EVENTS)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('endpoint_url')) {
                return;
            }

            try {
                app(PublicWebhookUrlValidator::class)
                    ->validateAndResolve((string) $this->input('endpoint_url'));
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('endpoint_url', $exception->getMessage());
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => filter_var($this->input('enabled', true), FILTER_VALIDATE_BOOL),
            'events' => array_values(array_unique($this->input('events', []))),
        ]);
    }
}
