<?php

namespace App\Services;

use App\Models\SipProfiles;
use App\Models\SwitchVariable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SipCaptureService
{
    public const DEFAULT_PORT = 9060;
    public const HEP_VERSION = 3;
    public const CAPTURE_ID_VARIABLE = 'hep_capture_id';
    public const MAX_CAPTURE_ID = 4294967295;
    public const CAPTURE_ID_ATTEMPTS = 32;
    public const LEGACY_DEFAULT_CAPTURE_ID = 100;
    public const LEGACY_MANAGED_DESCRIPTION = 'HEP capture identifier managed by SIP Capture settings.';
    public const MANAGED_DESCRIPTION = 'HEP capture identifier randomly assigned by SIP Capture settings.';

    public function settings(): array
    {
        $global = DB::table('v_sofia_global_settings')
            ->where('global_setting_name', 'capture-server')
            ->orderBy('sofia_global_setting_uuid')
            ->first();

        $parsed = $this->parseCaptureServerValue($global?->global_setting_value);
        $serverHostname = $this->switchVariableService()->currentHostname();
        $storedCaptureId = $this->storedCaptureId($serverHostname);
        $profiles = $this->profiles();
        $selected = $this->selectedProfileUuids($profiles);
        $configured = $global && $this->enabledValue($global->global_setting_enabled);

        // A fresh installation starts with every enabled profile selected so
        // the administrator only has to enter the collector address.
        if (! $configured && $selected->isEmpty()) {
            $selected = $profiles->pluck('sip_profile_uuid');
        }

        return [
            'enabled' => (bool) ($configured && $this->selectedProfileUuids($profiles)->isNotEmpty()),
            'transport' => $parsed['transport'] ?? 'udp',
            'collector_host' => $parsed['collector_host'] ?? '',
            'collector_port' => $parsed['collector_port'] ?? self::DEFAULT_PORT,
            'server_hostname' => $serverHostname,
            'capture_id' => $storedCaptureId,
            'capture_id_configured' => $storedCaptureId !== null,
            'hep_version' => self::HEP_VERSION,
            'profile_uuids' => $selected->values()->all(),
            'profiles' => $profiles->map(fn (SipProfiles $profile) => [
                'value' => $profile->sip_profile_uuid,
                'label' => $this->profileLabel($profile),
            ])->values()->all(),
        ];
    }

    /**
     * Save the generated Sofia settings and rescan active profiles. Rescan is
     * deliberate: these capture parameters are applied by mod_sofia without
     * stopping the profile or clearing its registrations.
     *
     * @return array{runtime_synchronized: bool, server_hostname: string, capture_id: int}
     */
    public function save(array $data): array
    {
        $enabled = (bool) ($data['enabled'] ?? false);
        $serverHostname = $this->switchVariableService()->currentHostname();
        $profiles = $this->profiles(includeDisabled: true);
        $enabledProfileIds = $profiles
            ->where('sip_profile_enabled', 'true')
            ->pluck('sip_profile_uuid')
            ->values();
        $selected = collect($data['profile_uuids'] ?? [])->unique()->values();

        if ($enabled) {
            $invalid = $selected->diff($enabledProfileIds);
            if ($selected->isEmpty() || $invalid->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'profile_uuids' => ['Select one or more enabled SIP profiles.'],
                ]);
            }
        } else {
            $selected = collect();
        }

        $captureServer = $enabled
            ? $this->captureServerValue(
                (string) $data['transport'],
                (string) $data['collector_host'],
                (int) $data['collector_port'],
            )
            : null;

        $captureId = null;

        DB::transaction(function () use (
            $enabled,
            $captureServer,
            $profiles,
            $selected,
            $serverHostname,
            &$captureId,
        ) {
            $captureId = $this->assignCaptureId($serverHostname);
            $this->saveGlobalSetting($enabled, $captureServer);
            $this->saveProfileSettings($profiles, $selected);
        });

        if (! $this->switchVariableService()->syncVarsXml()) {
            return [
                'runtime_synchronized' => false,
                'server_hostname' => $serverHostname,
                'capture_id' => $captureId,
            ];
        }

        $activeProfiles = $profiles->where('sip_profile_enabled', 'true')->values();

        $runtimeSynchronized = $this->runtimeService()->synchronize(
            $activeProfiles->map(fn (SipProfiles $profile) => [
                'before' => $this->runtimeState($profile),
                'after' => $this->runtimeState($profile),
            ]),
            $activeProfiles->pluck('sip_profile_hostname'),
            $activeProfiles->map(function (SipProfiles $profile) use ($selected) {
                return array_merge($this->runtimeState($profile), [
                    'capture' => $selected->contains($profile->sip_profile_uuid),
                ]);
            }),
            $enabled
                ? collect([self::CAPTURE_ID_VARIABLE => (string) $captureId])
                : null,
        );

        return [
            'runtime_synchronized' => $runtimeSynchronized,
            'server_hostname' => $serverHostname,
            'capture_id' => $captureId,
        ];
    }

    public function captureServerValue(string $transport, string $host, int $port): string
    {
        $host = trim($host);
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $host = '[' . trim($host, '[]') . ']';
        }

        return sprintf(
            '%s:%s:%d;hep=%d;capture_id=$${%s}',
            strtolower($transport),
            $host,
            $port,
            self::HEP_VERSION,
            self::CAPTURE_ID_VARIABLE,
        );
    }

    /**
     * Parse both the complete HEP value and the older udp:host:port default.
     *
     * @return array<string, int|string|null>|null
     */
    public function parseCaptureServerValue(?string $value): ?array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (! preg_match(
            '/^(udp|tcp):(\[[^\]]+\]|[^:;]+):(\d+)(?:;hep=(\d+);capture_id=(\d+|\$\$\{hep_capture_id\}))?$/i',
            $value,
            $matches
        )) {
            return null;
        }

        return [
            'transport' => strtolower($matches[1]),
            'collector_host' => trim($matches[2], '[]'),
            'collector_port' => (int) $matches[3],
            'capture_id' => isset($matches[5]) && ctype_digit($matches[5])
                ? (int) $matches[5]
                : null,
        ];
    }

    protected function storedCaptureId(string $hostname): ?int
    {
        $value = SwitchVariable::query()
            ->where('var_name', self::CAPTURE_ID_VARIABLE)
            ->where('var_hostname', $hostname)
            ->value('var_value');

        return $this->validCaptureId($value);
    }

    protected function assignCaptureId(string $hostname): int
    {
        $variables = SwitchVariable::query()
            ->where('var_name', self::CAPTURE_ID_VARIABLE)
            ->lockForUpdate()
            ->get();

        $variable = $variables->first(
            fn (SwitchVariable $item) => trim((string) $item->var_hostname) === $hostname
        );
        $usedCaptureIds = $variables->pluck('var_value')
            ->map(fn ($value) => $this->validCaptureId($value))
            ->filter(fn ($value) => $value !== null)
            ->values();
        $captureId = $this->validCaptureId($variable?->var_value);

        if ($captureId === null || $this->shouldReplaceLegacyCaptureId($variable, $captureId)) {
            $captureId = $this->randomCaptureId($usedCaptureIds);
        }

        $variable ??= new SwitchVariable(['var_uuid' => (string) Str::uuid()]);
        $variable->forceFill([
            'var_category' => 'SIP Capture',
            'var_name' => self::CAPTURE_ID_VARIABLE,
            'var_value' => (string) $captureId,
            'var_command' => 'set',
            'var_hostname' => $hostname,
            'var_enabled' => 'true',
            'var_order' => null,
            'var_description' => self::MANAGED_DESCRIPTION,
        ])->save();

        return $captureId;
    }

    protected function shouldReplaceLegacyCaptureId(?SwitchVariable $variable, ?int $captureId): bool
    {
        return $captureId === self::LEGACY_DEFAULT_CAPTURE_ID
            && trim((string) $variable?->var_description) === self::LEGACY_MANAGED_DESCRIPTION;
    }

    protected function randomCaptureId(Collection $usedCaptureIds): int
    {
        for ($attempt = 0; $attempt < self::CAPTURE_ID_ATTEMPTS; $attempt++) {
            $candidate = $this->generateCaptureIdCandidate();
            if (! $usedCaptureIds->contains($candidate)) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages([
            'capture_id' => ['Unable to allocate a unique HEP capture ID. Try saving again.'],
        ]);
    }

    protected function generateCaptureIdCandidate(): int
    {
        return random_int(1, self::MAX_CAPTURE_ID);
    }

    protected function validCaptureId(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value >= 1 && $value <= self::MAX_CAPTURE_ID ? $value : null;
    }

    protected function profiles(bool $includeDisabled = false): Collection
    {
        return SipProfiles::query()
            ->select([
                'sip_profile_uuid',
                'sip_profile_name',
                'sip_profile_hostname',
                'sip_profile_enabled',
            ])
            ->when(! $includeDisabled, fn ($query) => $query->where('sip_profile_enabled', 'true'))
            ->orderBy('sip_profile_name')
            ->get();
    }

    protected function runtimeService(): SofiaProfileRuntimeService
    {
        return app(SofiaProfileRuntimeService::class);
    }

    protected function switchVariableService(): SwitchVariableService
    {
        return app(SwitchVariableService::class);
    }

    protected function selectedProfileUuids(Collection $profiles): Collection
    {
        return DB::table('v_sip_profile_settings')
            ->whereIn('sip_profile_uuid', $profiles->pluck('sip_profile_uuid')->all())
            ->where('sip_profile_setting_name', 'sip-capture')
            ->where('sip_profile_setting_enabled', 'true')
            ->whereIn(DB::raw('lower(sip_profile_setting_value)'), ['true', 'yes', 'on', '1'])
            ->pluck('sip_profile_uuid')
            ->unique()
            ->values();
    }

    protected function saveGlobalSetting(bool $enabled, ?string $captureServer): void
    {
        $rows = DB::table('v_sofia_global_settings')
            ->where('global_setting_name', 'capture-server')
            ->get();

        if ($rows->isEmpty() && ! $enabled) {
            return;
        }

        $value = $captureServer ?: (string) ($rows->first()?->global_setting_value ?? '');
        $data = $this->withAudit([
            'global_setting_name' => 'capture-server',
            'global_setting_value' => $value,
            'global_setting_enabled' => $enabled ? 'true' : 'false',
            'global_setting_description' => 'HEP collector for SIP capture.',
        ], 'v_sofia_global_settings', $rows->isEmpty());

        if ($rows->isEmpty()) {
            DB::table('v_sofia_global_settings')->insert(array_merge($data, [
                'sofia_global_setting_uuid' => (string) Str::uuid(),
            ]));

            return;
        }

        DB::table('v_sofia_global_settings')
            ->whereIn('sofia_global_setting_uuid', $rows->pluck('sofia_global_setting_uuid')->all())
            ->update($data);
    }

    protected function saveProfileSettings(Collection $profiles, Collection $selected): void
    {
        foreach ($profiles as $profile) {
            $rows = DB::table('v_sip_profile_settings')
                ->where('sip_profile_uuid', $profile->sip_profile_uuid)
                ->where('sip_profile_setting_name', 'sip-capture')
                ->get();
            $value = $selected->contains($profile->sip_profile_uuid) ? 'yes' : 'no';
            $data = $this->withAudit([
                'sip_profile_uuid' => $profile->sip_profile_uuid,
                'sip_profile_setting_name' => 'sip-capture',
                'sip_profile_setting_value' => $value,
                'sip_profile_setting_enabled' => 'true',
                'sip_profile_setting_description' => 'Send SIP signaling to the configured HEP collector.',
            ], 'v_sip_profile_settings', $rows->isEmpty());

            if ($rows->isEmpty()) {
                DB::table('v_sip_profile_settings')->insert(array_merge($data, [
                    'sip_profile_setting_uuid' => (string) Str::uuid(),
                ]));
                continue;
            }

            DB::table('v_sip_profile_settings')
                ->whereIn('sip_profile_setting_uuid', $rows->pluck('sip_profile_setting_uuid')->all())
                ->update($data);
        }
    }

    protected function withAudit(array $data, string $table, bool $isNew): array
    {
        if ($isNew && Schema::hasColumn($table, 'insert_date')) {
            $data['insert_date'] = now();
        }
        if ($isNew && Schema::hasColumn($table, 'insert_user')) {
            $data['insert_user'] = session('user_uuid');
        }
        if (Schema::hasColumn($table, 'update_date')) {
            $data['update_date'] = now();
        }
        if (Schema::hasColumn($table, 'update_user')) {
            $data['update_user'] = session('user_uuid');
        }

        return $data;
    }

    protected function runtimeState(SipProfiles $profile): array
    {
        return [
            'name' => (string) $profile->sip_profile_name,
            'hostname' => filled($profile->sip_profile_hostname)
                ? (string) $profile->sip_profile_hostname
                : null,
            'enabled' => (string) $profile->sip_profile_enabled,
        ];
    }

    protected function profileLabel(SipProfiles $profile): string
    {
        if (blank($profile->sip_profile_hostname)) {
            return (string) $profile->sip_profile_name;
        }

        return $profile->sip_profile_name . ' (' . $profile->sip_profile_hostname . ')';
    }

    protected function enabledValue(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
    }
}
