<?php

namespace App\Services;

use App\Models\DialplanDetails;
use App\Models\Dialplans;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OutboundRouteService
{
    private const APP_UUID = '8c914ec3-9fc0-8ab5-4cda-6c9288bdc9a3';

    public function __construct(private DialplanService $dialplanService)
    {
    }

    public function create(array $validated): array
    {
        return DB::transaction(function () use ($validated) {
            $created = [];
            $expressions = $this->expressions($validated['dialplan_expression']);
            $domainUuid = $this->blankToNull($validated['domain_uuid'] ?? null);
            $context = $validated['dialplan_context'];
            $customName = $this->blankToNull($validated['dialplan_name'] ?? null);

            foreach ($expressions as $expression) {
                $route = $this->expressionRoute($expression);
                $primary = $this->destination($validated['gateway']);
                $fallbacks = collect([
                    $this->destination($validated['gateway_2'] ?? null),
                    $this->destination($validated['gateway_3'] ?? null),
                ])->filter()->values()->all();

                $created[] = $this->persistDialplan([
                    'dialplan_name' => $this->routeName($primary, $route['abbrv'], $customName),
                    'dialplan_order' => (string) $validated['dialplan_order'],
                    'dialplan_continue' => 'false',
                    'dialplan_enabled' => $validated['dialplan_enabled'],
                    'dialplan_description' => $this->blankToNull($validated['dialplan_description'] ?? null),
                    'domain_uuid' => $domainUuid,
                    'dialplan_context' => $context,
                ], $this->outboundRouteDetails($validated, $expression, $route, $primary, $fallbacks));
            }

            $this->dialplanService->clearDialplanCache($context);

            return [
                'count' => count($created),
                'dialplan_uuids' => collect($created)->pluck('dialplan_uuid')->values()->all(),
            ];
        });
    }

    private function expressions(string $value): array
    {
        return collect(preg_split('/\R/', $value))
            ->map(fn ($expression) => trim((string) $expression))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function expressionRoute(string $expression): array
    {
        return match ($expression) {
            '^(\\d{7})$' => ['label' => '7 Digit', 'abbrv' => '7d'],
            '^(\\d{8})$' => ['label' => '8 Digit', 'abbrv' => '8d'],
            '^(\\d{9})$' => ['label' => '9 Digit', 'abbrv' => '9d'],
            '^(\\d{10})$' => ['label' => '10 Digit', 'abbrv' => '10d'],
            '^\\+?(\\d{11})$' => ['label' => '11 Digit', 'abbrv' => '11d'],
            '^(?:\\+?1)?([2-9]\\d{2}[2-9]\\d{2}\\d{4})$' => ['label' => 'North America', 'abbrv' => 'NANP'],
            '^9999(?:\\+?1)?([2-9]\\d{2}[2-9]\\d{2}\\d{4})$' => ['label' => 'FS PBX Fax North America (Prefix 9999)', 'abbrv' => 'fax-NANP'],
            '^(?:\\+1|1)?([2-9]\\d{2}[2-9]\\d{2}\\d{4})$' => ['label' => 'North America', 'abbrv' => '10-11-NANP'],
            '^\\+([1-9]\\d{7,14})$' => ['label' => 'E.164 International', 'abbrv' => 'E164'],
            '^011([1-9]\\d{7,14})$' => ['label' => '011 International', 'abbrv' => '011-intl'],
            '^(011\\d{9,17})$' => ['label' => 'International', 'abbrv' => '011.9-17d'],
            '^00([1-9]\\d{7,14})$' => ['label' => '00 International', 'abbrv' => '00-intl'],
            '^([1-9]\\d{7,14})$' => ['label' => 'International Digits', 'abbrv' => 'intl-digits'],
            '^(?:\\+1|1)((?:264|268|242|246|441|284|345|767|809|829|849|473|658|876|664|787|939|869|758|784|721|868|649|340|684|671|670|808)\\d{7})$' => ['label' => 'North America Islands', 'abbrv' => '011.9-17d'],
            '^(\\d{12,20})$' => ['label' => 'International', 'abbrv' => 'intl'],
            '^(311)$' => ['label' => '311', 'abbrv' => '311'],
            '^(411)$' => ['label' => '411', 'abbrv' => '411'],
            '^(711)$' => ['label' => '711', 'abbrv' => '711'],
            '^(933|911)\\.?$' => ['label' => '911 / 933', 'abbrv' => '911'],
            '^(988)$' => ['label' => '988', 'abbrv' => '988'],
            '^9(\\d{3})$' => ['label' => '9 + 3 Digit', 'abbrv' => '9.3d'],
            '^9(\\d{4})$' => ['label' => '9 + 4 Digit', 'abbrv' => '9.4d'],
            '^9(\\d{7})$' => ['label' => '9 + 7 Digit', 'abbrv' => '9.7d'],
            '^9(\\d{10})$' => ['label' => '9 + 10 Digit', 'abbrv' => '9.10d'],
            '^9(\\d{11})$' => ['label' => '9 + 11 Digit', 'abbrv' => '9.11d'],
            '^9(\\d{12,20})$' => ['label' => '9 + International', 'abbrv' => '9.12-20'],
            '^(?:\\+1|1)?(8(?:00|33|44|55|66|77|88)[2-9]\\d{6})$' => ['label' => 'Toll Free', 'abbrv' => '800'],
            '^0118835100\\d{8}$' => ['label' => 'iNum', 'abbrv' => 'inum'],
            default => ['label' => $expression, 'abbrv' => $this->filenameSafe($expression)],
        };
    }

    private function destination(?string $value): ?array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with(strtolower($value), 'bridge:')) {
            return ['type' => 'bridge', 'uuid' => null, 'name' => 'bridge', 'data' => substr($value, 7)];
        }

        if (str_starts_with(strtolower($value), 'enum')) {
            return ['type' => 'enum', 'uuid' => null, 'name' => 'enum', 'data' => $value];
        }

        if (str_starts_with(strtolower($value), 'freetdm')) {
            return ['type' => 'freetdm', 'uuid' => null, 'name' => 'freetdm', 'data' => $value];
        }

        if (str_starts_with(strtolower($value), 'transfer:')) {
            return ['type' => 'transfer', 'uuid' => null, 'name' => 'transfer', 'data' => substr($value, 9)];
        }

        if (str_starts_with(strtolower($value), 'xmpp')) {
            return ['type' => 'xmpp', 'uuid' => null, 'name' => 'xmpp', 'data' => $value];
        }

        [$uuid, $name] = array_pad(explode(':', $value, 2), 2, null);

        return [
            'type' => 'gateway',
            'uuid' => $uuid,
            'name' => $name ?: $uuid,
            'data' => $value,
        ];
    }

    private function routeName(array $destination, string $abbrv, ?string $customName = null): string
    {
        if (filled($customName)) {
            return $customName . ' - ' . $abbrv;
        }

        return match ($destination['type']) {
            'gateway' => $destination['name'] . '.' . $abbrv,
            'freetdm' => 'freetdm.' . $abbrv,
            'xmpp' => 'xmpp.' . $abbrv,
            'bridge' => 'bridge.' . $abbrv,
            'enum' => 'enum.' . $abbrv,
            'transfer' => 'transfer.' . $abbrv,
            default => $abbrv,
        };
    }

    private function bridgeData(array $destination, string $prefixNumber, string $abbrv): string
    {
        return match ($destination['type']) {
            'gateway' => 'sofia/gateway/' . $destination['uuid'] . '/' . ($abbrv === '988' ? $prefixNumber . '18002738255' : $prefixNumber . '$1'),
            'freetdm' => $destination['data'] . '/1/a/' . $prefixNumber . '$1',
            'xmpp' => 'dingaling/gtalk/+' . $prefixNumber . '$1@voice.google.com',
            'bridge', 'transfer' => $destination['data'],
            'enum' => '${enum_auto_route}',
            default => $destination['data'],
        };
    }

    private function outboundRouteDetails(array $validated, string $expression, array $route, array $primary, array $fallbacks): array
    {
        $details = [];
        $order = 10;
        $prefixNumber = (string) ($validated['prefix_number'] ?? '');
        $isEmergency = in_array($expression, ['^(933|911)\\.?$', '(^911$|^933$)'], true);

        $details[] = $this->detail('condition', '${user_exists}', 'false', $order);
        $order += 10;

        if (filled($validated['toll_allow'] ?? null)) {
            $details[] = $this->detail('condition', '${toll_allow}', $validated['toll_allow'], $order);
            $order += 10;
        }

        $details[] = $this->detail('condition', 'destination_number', $expression, $order);
        $order += 10;

        if ($primary['type'] !== 'transfer') {
            $accountcode = filled($validated['accountcode'] ?? null)
                ? 'sip_h_X-accountcode=' . $validated['accountcode']
                : 'sip_h_X-accountcode=${accountcode}';
            $details[] = $this->detail('action', 'set', $accountcode, $order);
            $order += 10;
        }

        $details[] = $this->detail('action', 'export', 'call_direction=outbound', $order);
        $order += 10;
        $details[] = $this->detail('action', 'unset', 'call_timeout', $order);
        $order += 10;
        $details[] = $this->detail('action', 'export', 'rtp_secure_media_outbound=forbidden', $order);
        $order += 10;

        if ($primary['type'] !== 'transfer') {
            $details[] = $this->detail('action', 'set', 'hangup_after_bridge=true', $order);
            $order += 10;
            $details[] = $this->detail('action', 'set', 'effective_caller_id_name=' . ($isEmergency ? '${emergency_caller_id_name}' : '${outbound_caller_id_name}'), $order);
            $order += 10;
            $details[] = $this->detail('action', 'set', 'effective_caller_id_number=' . ($isEmergency ? '${emergency_caller_id_number}' : '${outbound_caller_id_number}'), $order);
            $order += 10;

            if ($isEmergency) {
                $details[] = $this->detail('action', 'lua', "email.lua \${email_to} \${email_from} '' 'Emergency Call' '\${sip_from_user}@\${domain_name} has called 911 emergency'", $order, enabled: 'false');
                $order += 10;
            }

            foreach ([
                'inherit_codec=true',
                'ignore_display_updates=true',
                'callee_id_number=$1',
                'continue_on_fail=true',
                'originate_continue_on_timeout=true',
            ] as $data) {
                $details[] = $this->detail('action', 'set', $data, $order);
                $order += 10;
            }
        }

        if ($primary['type'] === 'enum' || collect($fallbacks)->contains(fn ($destination) => $destination['type'] === 'enum')) {
            $details[] = $this->detail('action', 'enum', $prefixNumber . '$1 e164.org', $order);
            $order += 10;
        }

        if (filled($validated['limit'] ?? null)) {
            $details[] = $this->detail('action', 'limit', 'hash ${domain_name} outbound ' . $validated['limit'] . ' !USER_BUSY', $order);
            $order += 10;
        }

        $outboundPrefix = $this->outboundPrefix($expression);
        if ($outboundPrefix !== '') {
            $details[] = $this->detail('action', 'set', 'outbound_prefix=' . $outboundPrefix, $order);
            $order += 10;
        }

        if (($validated['pin_numbers_enabled'] ?? 'false') === 'true') {
            $details[] = $this->detail('action', 'set', 'pin_number=database', $order);
            $order += 10;
            $details[] = $this->detail('action', 'lua', 'pin_number.lua', $order);
            $order += 10;
        }

        if (strlen($prefixNumber) > 2) {
            $details[] = $this->detail('action', 'set', 'provider_prefix=' . $prefixNumber, $order);
            $order += 10;
        }

        $details[] = $this->detail(
            'action',
            $primary['type'] === 'transfer' ? 'transfer' : 'bridge',
            $this->bridgeData($primary, $prefixNumber, $route['abbrv']),
            $order
        );
        $order += 10;

        foreach ($fallbacks as $fallback) {
            $details[] = $this->detail('action', 'bridge', $this->bridgeData($fallback, $prefixNumber, $route['abbrv']), $order);
            $order += 10;
        }

        return $details;
    }

    private function persistDialplan(array $attributes, array $details): Dialplans
    {
        $dialplan = new Dialplans();
        $dialplan->forceFill([
            'domain_uuid' => $attributes['domain_uuid'],
            'dialplan_uuid' => (string) Str::uuid(),
            'app_uuid' => self::APP_UUID,
            'dialplan_name' => $attributes['dialplan_name'],
            'dialplan_number' => null,
            'dialplan_destination' => 'false',
            'dialplan_context' => $attributes['dialplan_context'],
            'dialplan_continue' => $attributes['dialplan_continue'],
            'dialplan_order' => $attributes['dialplan_order'],
            'dialplan_enabled' => $attributes['dialplan_enabled'],
            'dialplan_description' => $attributes['dialplan_description'],
            'insert_date' => now(),
            'insert_user' => session('user_uuid'),
        ]);
        $dialplan->dialplan_xml = $this->dialplanService->buildXml($dialplan, $details);
        $dialplan->save();

        foreach ($details as $detail) {
            DialplanDetails::create([
                ...$detail,
                'domain_uuid' => $attributes['domain_uuid'],
                'dialplan_uuid' => $dialplan->dialplan_uuid,
                'dialplan_detail_uuid' => (string) Str::uuid(),
                'insert_date' => now(),
                'insert_user' => session('user_uuid'),
            ]);
        }

        return $dialplan;
    }

    private function detail(string $tag, ?string $type, ?string $data, int $order, ?string $inline = null, string $enabled = 'true'): array
    {
        return [
            'dialplan_detail_tag' => $tag,
            'dialplan_detail_type' => $type,
            'dialplan_detail_data' => $data,
            'dialplan_detail_break' => null,
            'dialplan_detail_inline' => $inline,
            'dialplan_detail_group' => 0,
            'dialplan_detail_order' => $order,
            'dialplan_detail_enabled' => $enabled,
        ];
    }

    private function outboundPrefix(string $expression): string
    {
        if (preg_match('/^\^(\d+)\(.*/', $expression, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function filenameSafe(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $value);
        $safe = trim((string) $safe, '_-.');

        return $safe !== '' ? mb_substr($safe, 0, 80) : 'route';
    }

    private function blankToNull($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
