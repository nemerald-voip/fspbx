<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\DB;

class MessagingPageSettings
{
    public function props(?string $domainUuid = null): array
    {
        return [
            ...app(PhotoCompressionSettings::class)->settings($domainUuid),
            ...app(MessageOptOutSettings::class)->props($domainUuid),
            'available' => app(PhotoCompressionService::class)->available(),
            ...($domainUuid ? ['webhook' => app(MessageWebhookSettings::class)->props($domainUuid)] : []),
        ];
    }

    public function save(?string $domainUuid, array $values): array
    {
        DB::transaction(function () use ($domainUuid, $values) {
            app(PhotoCompressionSettings::class)->save($domainUuid, $values);
            if ($domainUuid && array_key_exists('endpoint_url', $values)) {
                app(MessageWebhookSettings::class)->save($domainUuid, $values['endpoint_url']);
            }
            if (isset($values['opt_out_scope'])) app(MessageOptOutSettings::class)->save($domainUuid, $values['opt_out_scope']);
            if (isset($values['opt_out_keywords'])) app(MessageOptOutSettings::class)->saveKeywords($domainUuid, $values['opt_out_keywords']);
        });
        return $this->props($domainUuid);
    }
}
