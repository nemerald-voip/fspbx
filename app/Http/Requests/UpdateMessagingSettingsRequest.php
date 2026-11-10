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
            'opt_out_scope' => ['sometimes', $this->routeIs('system-settings.messaging.update') ? 'in:disabled,number,account' : 'in:inherit,disabled,number,account'],
            'opt_out_keywords' => ['sometimes', 'array', 'max:30'],
            'opt_out_keywords.*' => ['required', 'string', 'max:64', 'regex:/\S/u', 'not_regex:/[\r\n]/'],
            'endpoint_url' => [$this->routeIs('system-settings.messaging.update') ? 'prohibited' : 'sometimes', 'nullable', 'string', 'max:2048', new \App\Rules\PublicWebhookUrl()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) return;

            $system = $this->routeIs('system-settings.messaging.update');
            if (!$system && $this->exists('endpoint_url') && !app(\App\Services\Messaging\MessageWebhookSettings::class)->available()) {
                $validator->errors()->add('endpoint_url', __('Messaging webhook setup is unavailable. Contact your administrator.'));
            }
            $url = $this->exists('endpoint_url') ? $this->input('endpoint_url')
                : ($system ? null : app(\App\Services\Messaging\MessageWebhookSettings::class)->account((string) session('domain_uuid'))?->endpoint_url);
            if (!$system && filled($url) && $this->hasAny(['opt_out_scope', 'opt_out_keywords'])) {
                $validator->errors()->add('opt_out_scope', __('Opt-out handling is unavailable for webhook messaging.'));
            }
            if (($this->boolean('enabled') || $this->boolean('convert_photos'))
                && !app(PhotoCompressionService::class)->available()) {
                $validator->errors()->add($this->boolean('enabled') ? 'enabled' : 'convert_photos',
                    __('Photo conversion and compression are unavailable on this server. Contact your administrator.'));
            }
        });
    }
}
