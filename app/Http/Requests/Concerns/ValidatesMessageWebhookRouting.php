<?php

namespace App\Http\Requests\Concerns;

use App\Rules\PublicWebhookUrl;
use App\Services\Messaging\MessageWebhookSettings;

trait ValidatesMessageWebhookRouting
{
    protected function webhookRules(): array
    {
        return [
            'inbound_delivery' => ['sometimes', 'required', 'in:inherit,members,webhook'],
            'inbound_webhook_url' => ['required_if:inbound_delivery,webhook', 'nullable', 'string', 'max:2048', new PublicWebhookUrl()],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->hasAny(['inbound_delivery', 'inbound_webhook_url'])
                && ! app(MessageWebhookSettings::class)->available()) {
                $validator->errors()->add('inbound_delivery', __('Messaging webhook setup is unavailable. Contact your administrator.'));
            }
        });
    }
}
