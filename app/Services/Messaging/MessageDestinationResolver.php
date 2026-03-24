<?php

namespace App\Services\Messaging;

use App\Models\DomainSettings;
use App\Models\Extensions;
use App\Models\SmsDestinations;
use App\Services\Messaging\DTO\MessageRoute;

class MessageDestinationResolver
{
    public function resolve(string $destination): MessageRoute
    {
        $config = SmsDestinations::where('destination', $destination)
            ->where('enabled', 'true')
            ->firstOrFail();

        $extension = null;

        if (!empty($config->chatplan_detail_data)) {
            $extension = Extensions::with('mobile_app')
                ->where('domain_uuid', $config->domain_uuid)
                ->where('extension', $config->chatplan_detail_data)
                ->first();
        }

        $orgId = DomainSettings::where('domain_uuid', $config->domain_uuid)
            ->where('domain_setting_category', 'app shell')
            ->where('domain_setting_subcategory', 'org_id')
            ->value('domain_setting_value');

        return new MessageRoute(
            domainUuid: $config->domain_uuid,
            destination: $destination,
            extensionUuid: $extension?->extension_uuid,
            extension: $extension?->extension,
            hasMobileApp: $extension?->mobile_app !== null,
            email: $config->email ?: null,
            orgId: $orgId
        );
    }
}