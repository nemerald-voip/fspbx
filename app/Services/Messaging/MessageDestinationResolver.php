<?php

namespace App\Services\Messaging;

use App\Models\DomainSettings;
use App\Models\Extensions;
use App\Models\SmsDestinations;
use App\Services\Messaging\Data\MessageRouteData;
use libphonenumber\PhoneNumberFormat;

class MessageDestinationResolver
{
    public function resolve(string $destination): MessageRouteData
    {
        messaging_webhook_debug('MessageDestinationResolver resolve()', [
            'destination' => $destination,
        ]);

        $lookupValues = $this->buildPhoneLookupValues($destination);

        messaging_webhook_debug('MessageDestinationResolver lookup values', [
            'lookup_values' => $lookupValues,
        ]);

        $config = SmsDestinations::whereIn('destination', $lookupValues)
            ->where('enabled', 'true')
            ->first();

        if (!$config) {
            messaging_webhook_debug('SmsDestination not found', [
                'destination' => $destination,
                'lookup_values' => $lookupValues,
            ]);

            throw new \RuntimeException('No SmsDestination found for destination: ' . $destination);
        }

        messaging_webhook_debug('SmsDestination found', [
            'matched_destination' => $config->destination,
            'domain_uuid' => $config->domain_uuid,
            'chatplan_detail_data' => $config->chatplan_detail_data,
            'email' => $config->email,
        ]);

        $extension = null;

        if (!empty($config->chatplan_detail_data)) {
            $extension = Extensions::with('mobile_app')
                ->where('domain_uuid', $config->domain_uuid)
                ->where('extension', $config->chatplan_detail_data)
                ->first();

            messaging_webhook_debug('Extension lookup complete', [
                'extension_found' => (bool) $extension,
                'extension_uuid' => $extension?->extension_uuid,
                'extension' => $extension?->extension,
                'has_mobile_app' => $extension?->mobile_app !== null,
            ]);
        }

        $orgId = DomainSettings::where('domain_uuid', $config->domain_uuid)
            ->where('domain_setting_category', 'app shell')
            ->where('domain_setting_subcategory', 'org_id')
            ->value('domain_setting_value');

        messaging_webhook_debug('Org ID lookup complete', [
            'org_id' => $orgId,
        ]);

        return MessageRouteData::from([
            'domainUuid' => $config->domain_uuid,
            'destination' => $config->destination,
            'extensionUuid' => $extension?->extension_uuid,
            'extension' => $extension?->extension,
            'hasMobileApp' => $extension?->mobile_app !== null,
            'email' => $config->email ?: null,
            'orgId' => $orgId,
        ]);
    }

    protected function buildPhoneLookupValues(string $number): array
    {
        $countryCode = get_domain_setting('country', null) ?? 'US';

        $e164 = formatPhoneNumber($number, $countryCode, PhoneNumberFormat::E164);
        $digitsOnly = preg_replace('/\D+/', '', $number ?? '');

        return array_values(array_unique(array_filter([
            $number,
            ltrim($number, '+'),
            '+' . ltrim($number, '+'),
            $digitsOnly,
            '+' . $digitsOnly,
            $e164,
        ])));
    }
}