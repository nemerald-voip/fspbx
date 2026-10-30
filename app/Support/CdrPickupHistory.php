<?php

namespace App\Support;

/** Pure parser shared by the legacy importer and native CDR timeline. */
class CdrPickupHistory
{
    public static function events(array $variables, array $profiles): array
    {
        $get = static fn (string $key): string => is_scalar($variables[$key] ?? null)
            ? urldecode((string) $variables[$key]) : '';
        $raw = json_decode($get('fspbx_pickup_events'), true);
        if (!is_array($raw)) {
            return [];
        }
        $events = [];
        foreach (array_slice($raw, -32) as $event) {
            if (!is_array($event) || !self::valid($event, $get('domain_uuid'))
                || !in_array($get('uuid'), [$event['target_uuid'], $event['picker_uuid']], true)) {
                continue;
            }
            $event = array_intersect_key($event, array_flip(['domain_uuid', 'target_uuid', 'picker_uuid',
                'ringing_extension', 'pickup_extension', 'code', 'at', 'ringing_started']));
            if (isset($event['ringing_started'])) {
                if (!is_numeric($event['ringing_started']) || $event['ringing_started'] > $event['at']) {
                    unset($event['ringing_started']);
                } else {
                    $event['ringing_started'] = max(0, (int) $event['ringing_started']);
                }
            }
            $at = (int) $event['at'];
            if ($at < (int) $get('start_uepoch') || $at > (int) $get('end_uepoch')) {
                continue;
            }
            $partner = $get('uuid') === $event['target_uuid'] ? $event['picker_uuid'] : $event['target_uuid'];
            $connected = null;
            foreach ($profiles as $profile) {
                if (!is_array($profile) || !is_array($profile['caller_profile'] ?? null)
                    || !self::hasPartner($profile['caller_profile'], $partner)) {
                    continue;
                }
                $time = (int) ($profile['times']['profile_created_time'] ?? 0);
                if ($time < $at) {
                    $time = (int) ($profile['times']['bridged_time'] ?? 0);
                }
                if ($time >= $at && $time <= (int) $get('end_uepoch')) {
                    $connected = $connected === null ? $time : min($connected, $time);
                }
            }
            // A saved bridge UUID and bridge timestamp also survive a failed
            // later transfer; answer time alone does not prove interception.
            $bridgeTime = (int) $get('bridge_uepoch');
            if ($connected === null && $bridgeTime >= $at && $bridgeTime <= (int) $get('end_uepoch')
                && in_array($partner, [$get('bridge_uuid'), $get('last_bridge_to')], true)) {
                $connected = $bridgeTime;
            }
            if ($connected !== null) {
                $event['connected_at'] = $connected;
                $events[$event['picker_uuid'].':'.$at] = $event;
            }
        }
        usort($events, fn ($a, $b) => $a['connected_at'] <=> $b['connected_at']);
        return $events;
    }

    public static function valid(array $event, string $domain): bool
    {
        foreach (['domain_uuid', 'target_uuid', 'picker_uuid'] as $key) {
            if (!is_string($event[$key] ?? null) || !preg_match('/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i', $event[$key])) {
                return false;
            }
        }
        return $event['domain_uuid'] === $domain && $event['target_uuid'] !== $event['picker_uuid']
            && is_string($event['code'] ?? null) && preg_match('/^(?:\*\*\d*|\*97\d+|\*8)$/D', $event['code'])
            && is_string($event['ringing_extension'] ?? null) && preg_match('/^\d{1,32}$/D', $event['ringing_extension'])
            && is_string($event['pickup_extension'] ?? null) && preg_match('/^\d{1,32}$/D', $event['pickup_extension'])
            && is_numeric($event['at'] ?? null) && $event['at'] > 0;
    }

    public static function hasPartner(array $profile, string $uuid): bool
    {
        foreach (['originator' => 'originator_caller_profile', 'originatee' => 'originatee_caller_profile'] as $key => $child) {
            $partners = $profile[$key][$child] ?? [];
            if (!is_array($partners)) {
                continue;
            }
            if (isset($partners['uuid'])) {
                $partners = [$partners];
            }
            foreach ($partners as $partner) {
                if (is_array($partner) && ($partner['uuid'] ?? null) === $uuid) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function parent(string $uuid, array $events): string
    {
        foreach ($events as $event) {
            if ($event['picker_uuid'] === $uuid) {
                return $event['target_uuid'];
            }
        }
        return '';
    }

    /** Intercept reverses caller profiles, but the channel's original variables survive. */
    public static function originalCaller(array $variables, array $events): array
    {
        $uuid = urldecode((string) ($variables['uuid'] ?? ''));
        $domain = urldecode((string) ($variables['domain_uuid'] ?? ''));
        foreach ($events as $event) {
            if (!self::valid($event, $domain) || $event['target_uuid'] !== $uuid
                || !is_numeric($event['connected_at'] ?? null) || $event['connected_at'] < $event['at']) {
                continue;
            }
            $caller = [];
            foreach (['caller_id_name', 'caller_id_number'] as $key) {
                if (is_scalar($variables[$key] ?? null) && (string) $variables[$key] !== '') {
                    $caller[$key] = urldecode((string) $variables[$key]);
                }
            }
            return $caller;
        }
        return [];
    }
}
