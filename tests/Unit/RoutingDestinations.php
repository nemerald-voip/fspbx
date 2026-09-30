<?php

namespace Tests\Unit;

use App\Models;

class RoutingDestinations
{
    public const DOMAIN = '11111111-1111-4111-8111-111111111111';
    public const TARGET = '22222222-2222-4222-8222-222222222222';

    public static function cases(): array
    {
        return [
            'extensions' => [Models\Extensions::class, 'extension', '100', 'transfer', '100 XML example.test'],
            'voicemails' => [Models\Voicemails::class, 'voicemail_id', '100', 'transfer', '*99100 XML example.test'],
            'ring_groups' => [Models\RingGroups::class, 'ring_group_extension', '800', 'transfer', '800 XML example.test'],
            'ivrs' => [Models\IvrMenus::class, 'ivr_menu_extension', '9155', 'transfer', '9155 XML example.test'],
            'business_hours' => [Models\BusinessHour::class, 'extension', '9201', 'transfer', '9201 XML example.test'],
            'time_conditions' => [Models\Dialplans::class, 'dialplan_number', '9300', 'transfer', '9300 XML example.test'],
            'contact_centers' => [Models\CallCenterQueues::class, 'queue_extension', '99979', 'transfer', '99979 XML example.test'],
            'bridges' => [Models\Bridge::class, 'bridge_uuid', self::TARGET, 'lua', 'bridge.lua '.self::TARGET],
            'faxes' => [Models\Faxes::class, 'fax_extension', '700', 'transfer', '700 XML example.test'],
            'call_flows' => [Models\CallFlows::class, 'call_flow_extension', '9100', 'transfer', '9100 XML example.test'],
            'dynamic_routes' => [Models\DynamicRoute::class, 'extension', '9504', 'transfer', '9504 XML example.test'],
            'recordings' => [Models\Recordings::class, 'recording_filename', 'greeting.wav', 'lua', 'streamfile.lua greeting.wav'],
            'conferences' => [Models\Conferences::class, 'conference_extension', '8001', 'transfer', '8001 XML example.test'],
            'conference_centers' => [Models\ConferenceCenter::class, 'conference_center_extension', '8002', 'transfer', '8002 XML example.test'],
            'ai_agents' => [Models\AiAgent::class, 'extension', '6016', 'transfer', '6016 XML example.test'],
            'check_voicemail' => [null, null, null, 'transfer', '*98 XML example.test'],
            'company_directory' => [null, null, null, 'transfer', '*411 XML example.test'],
            'hangup' => [null, null, null, 'hangup', ''],
        ];
    }

    public static function target(?string $model, ?string $field, ?string $value): ?\Illuminate\Database\Eloquent\Model
    {
        if (! $model) return null;
        $target = new $model;
        $target->setRawAttributes([$target->getKeyName() => self::TARGET, 'domain_uuid' => self::DOMAIN, $field => $value]);

        return $target;
    }
}
