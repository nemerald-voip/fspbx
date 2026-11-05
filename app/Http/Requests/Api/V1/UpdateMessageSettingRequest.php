<?php

namespace App\Http\Requests\Api\V1;

use App\Exceptions\ApiException;
use App\Models\MessageSetting;
use App\Rules\PublicWebhookUrl;
use App\Services\Messaging\MessageSettingsService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateMessageSettingRequest extends StoreMessageSettingRequest
{
    private ?MessageSetting $setting = null;

    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }
        $uuid = (string) $this->route('sms_destination_uuid');
        if (! Str::isUuid($uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid message setting UUID.', 'invalid_request', 'sms_destination_uuid');
        }
        $this->setting = app(MessageSettingsService::class)->query((string) $this->route('domain_uuid'))->whereKey($uuid)->first();
        if (! $this->setting) {
            throw new ApiException(404, 'invalid_request_error', 'Message setting not found.', 'resource_missing', 'sms_destination_uuid');
        }

        return true;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        array_unshift($rules['destination'], 'sometimes');
        // PATCH validates the resulting route, including values omitted from this request.
        $rules['inbound_webhook_url'] = [
            'bail',
            Rule::requiredIf(fn () => $this->input('inbound_delivery', $this->setting?->inbound_delivery ?? 'inherit') === 'webhook'
                && ($this->has('inbound_webhook_url') || blank($this->setting?->inbound_webhook_url))),
            'nullable', 'string', 'max:2048', new PublicWebhookUrl(),
        ];

        return $rules;
    }
}
