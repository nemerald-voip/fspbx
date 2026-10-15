<?php

namespace App\Http\Requests;

use App\Services\Messaging\MessageSettingsAccess;
use App\Services\Messaging\PhotoCompressionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMessagingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (!userCheckPermission('account_settings_list_view')) return false;

        return $this->routeIs('system-settings.messaging.update')
            ? userCheckPermission('default_setting_edit')
            : ($this->routeIs('account-settings.messaging.update') && session('domain_uuid')
                && MessageSettingsAccess::canManage());
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'convert_photos' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) return;

            if (($this->boolean('enabled') || $this->boolean('convert_photos'))
                && !app(PhotoCompressionService::class)->available()) {
                $validator->errors()->add($this->boolean('enabled') ? 'enabled' : 'convert_photos',
                    __('Photo conversion and compression are unavailable on this server. Contact your administrator.'));
            }
        });
    }
}
