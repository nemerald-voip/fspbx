<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CdrTimelineEnricher
{
    /** Resolve names once per timeline, without retaining stale data between requests. */
    public function enrich(Collection $rows, string $domainUuid): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $aliases = [];
        $extensionNumbers = [];
        foreach ($rows as $row) {
            $number = (string) $row['destination_number'];
            $aliases[$number] ??= $this->numberAliases($number);
            $extensionNumbers[] = $number;
            if (preg_match('/^\*97\d+\^(.+)$/', $number, $match)) {
                $extensionNumbers[] = $match[1];
            }
        }
        $contexts = $rows->pluck('context')->filter()->unique()->values()->all();
        $numbers = array_values(array_unique(array_merge(...array_values($aliases))));
        $dialplans = $contexts ? DB::table('v_dialplans')
            ->where(fn ($query) => $query->where('domain_uuid', $domainUuid)->orWhereNull('domain_uuid'))
            ->whereIn('dialplan_context', $contexts)
            ->whereIn('dialplan_number', $numbers)
            ->where('dialplan_enabled', 'true')
            ->orderByRaw('CASE WHEN domain_uuid IS NULL THEN 1 ELSE 0 END')
            ->orderBy('dialplan_order')->orderBy('dialplan_uuid')
            ->get(['dialplan_uuid', 'domain_uuid', 'dialplan_context', 'dialplan_number', 'dialplan_xml',
                'dialplan_name', 'dialplan_description']) : collect();
        $byContext = [];
        foreach ($dialplans as $dialplan) {
            $dialplan->application = self::detectApplication($dialplan->dialplan_xml ?? '');
            $byContext[$dialplan->dialplan_context][$dialplan->dialplan_number][] = $dialplan;
        }
        // Query only labels. Hydrating Extensions would eagerly load advSettings.
        $extensions = DB::table('v_extensions')->where('domain_uuid', $domainUuid)
            ->whereIn('extension', array_values(array_unique($extensionNumbers)))
            ->get(['extension', 'effective_caller_id_name', 'description'])->keyBy('extension');

        $resolved = $rows->map(function ($row) use ($aliases, $byContext, $extensions, $domainUuid) {
            $number = (string) $row['destination_number'];
            $candidates = [];
            foreach ($aliases[$number] as $alias) {
                foreach ($byContext[$row['context'] ?? ''][$alias] ?? [] as $candidate) {
                    $candidates[$candidate->dialplan_uuid] = $candidate;
                }
            }
            // Exact account definitions precede global definitions, including
            // when the two spell a phone number differently.
            $candidates = collect($candidates)->sortBy(fn ($candidate) => $candidate->domain_uuid === $domainUuid ? 0 : 1);
            $type = $row['_saved_type'] ?? null;
            $uuid = $row['_saved_uuid'] ?? null;
            $dialplan = $candidates->first(function ($candidate) use ($type, $uuid) {
                return ! $type || (($candidate->application['type'] ?? null) === $type
                    && (! $uuid || ($candidate->application['uuid'] ?? null) === $uuid));
            });
            $type ??= $dialplan->application['type'] ?? null;
            $uuid ??= $dialplan->application['uuid'] ?? null;

            if ($type) {
                $row['dialplan_app_type'] = $type;
                $row['dialplan_app'] = $this->label($type);
                $row['dialplan_name'] = $dialplan->dialplan_name ?? $number;
                $row['dialplan_description'] = $dialplan->dialplan_description ?? null;
                $row['_resolved_uuid'] = $uuid;
                return $row;
            }

            if (str_starts_with($number, 'park+')) {
                $type = 'park';
                $name = ltrim(substr($number, 5), '*');
            } elseif (str_starts_with($number, '*99')) {
                $type = 'voicemail';
                $name = substr($number, 3);
            } elseif (preg_match('/^\*97(\d+)\^(.+)$/', $number, $match)) {
                $type = 'call_intercept';
                $extension = $extensions->get($match[2]);
                $name = $extension ? $extension->effective_caller_id_name.' ('.$match[2].')' : $match[2];
                $row['dialplan_app'] = __('Call Intercept :extension', ['extension' => $match[1]]);
            } elseif ($extension = $extensions->get($number)) {
                $type = 'extension';
                $name = $extension->effective_caller_id_name;
                $row['dialplan_description'] = $extension->description;
            } else {
                $type = ($row['_direction'] ?? null) === 'outbound' || ($row['dialplan_app_type'] ?? null) === 'outbound_call'
                    ? 'outbound_call' : 'misc_destination';
                $name = $dialplan->dialplan_name ?? $number;
            }
            $row['dialplan_app_type'] = $type;
            if ($type !== 'call_intercept') {
                $row['dialplan_app'] = $this->label($type);
            }
            $row['dialplan_name'] = $name;
            $row['dialplan_description'] ??= null;
            return $row;
        });

        // Only attribute a CDR's final queue outcome when the corresponding
        // queue occurs once on that channel. Re-entry is ambiguous, not success.
        $queueCounts = [];
        foreach ($resolved as $row) {
            if ($row['dialplan_app_type'] === 'contact_center_queue') {
                $key = ($row['_cdr_uuid'] ?? '').':'.($row['_resolved_uuid'] ?? '');
                $queueCounts[$key] = ($queueCounts[$key] ?? 0) + 1;
            }
        }
        return $resolved->map(function ($row) use ($queueCounts) {
            if ($row['dialplan_app_type'] === 'contact_center_queue') {
                $uuid = $row['_resolved_uuid'] ?? null;
                $key = ($row['_cdr_uuid'] ?? '').':'.$uuid;
                $row['queue_result'] = $uuid && $uuid === ($row['_queue_uuid'] ?? null) && $queueCounts[$key] === 1
                    ? (($row['_queue_result'] ?? null) ?: __('Unknown')) : __('Unknown');
            }
            foreach (array_keys($row) as $key) {
                if (str_starts_with($key, '_')) {
                    unset($row[$key]);
                }
            }
            return $row;
        });
    }

    private function numberAliases(string $number): array
    {
        $formatted = (string) formatPhoneNumber($number, 'US', 0);
        if (str_starts_with($formatted, '+1')) {
            $bare = substr($formatted, 2);
            return array_values(array_unique([$number, $formatted, $bare, '1'.$bare]));
        }
        // Short extensions and non-NANP numbers do not have a US country-code alias.
        return array_values(array_unique([$number, $formatted]));
    }

    public static function savedApplication(array $profile): ?array
    {
        $applications = $profile['extension']['application'] ?? [];
        if (isset($applications['@attributes'])) {
            $applications = [$applications];
        }
        if (! is_array($applications)) {
            return null;
        }
        $data = '';
        foreach ($applications as $application) {
            $attributes = $application['@attributes'] ?? [];
            $name = $attributes['app_name'] ?? '';
            $value = $attributes['app_data'] ?? '';
            $data .= ' application="'.(is_scalar($name) ? $name : '').'" '.(is_scalar($value) ? $value : '')."\n";
        }
        return self::detectApplication($data);
    }

    private static function detectApplication(string $data): ?array
    {
        foreach (['ring_group_uuid' => 'ring_group', 'ivr_menu_uuid' => 'auto_receptionist',
            'call_center_queue_uuid' => 'contact_center_queue', 'call_flow_uuid' => 'call_flow'] as $key => $type) {
            if (preg_match('/\b'.$key.'=([a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12})\b/i', $data, $match)) {
                return ['type' => $type, 'uuid' => strtolower($match[1])];
            }
        }
        foreach (['/application="rxfax"/' => 'virtual_fax', '/\bcall_direction=inbound\b/' => 'inbound_call',
            '/\b(?:year|yday|mon|mday|week|mweek|wday|hour|minute|minute-of-day|time-of-day|date-time)=/' => 'schedule'] as $pattern => $type) {
            if (preg_match($pattern, $data)) {
                return ['type' => $type, 'uuid' => null];
            }
        }
        return null;
    }

    private function label(string $type): string
    {
        return match ($type) {
            'ring_group' => __('Ring Group'), 'auto_receptionist' => __('Auto Receptionist'),
            'contact_center_queue' => __('Contact Center Queue'), 'call_flow' => __('Call Flow'),
            'inbound_call' => __('Inbound Call'), 'outbound_call' => __('Outbound Call'),
            'schedule' => __('Schedule'), 'virtual_fax' => __('Virtual Fax'),
            'park' => __('Park'), 'voicemail' => __('Voicemail'), 'extension' => __('Extension'),
            default => __('Misc. Destination'),
        };
    }
}
