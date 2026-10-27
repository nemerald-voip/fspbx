<?php

namespace App\Http\Requests\Api\V1;

use App\Exceptions\ApiException;
use App\Services\Messaging\MessageSettingsService;
use Illuminate\Support\Str;

class UpdateMessageSettingRequest extends StoreMessageSettingRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }
        $uuid = (string) $this->route('sms_destination_uuid');
        if (! Str::isUuid($uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid message setting UUID.', 'invalid_request', 'sms_destination_uuid');
        }
        if (! app(MessageSettingsService::class)->query((string) $this->route('domain_uuid'))->whereKey($uuid)->exists()) {
            throw new ApiException(404, 'invalid_request_error', 'Message setting not found.', 'resource_missing', 'sms_destination_uuid');
        }

        return true;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        array_unshift($rules['destination'], 'sometimes');

        return $rules;
    }
}
