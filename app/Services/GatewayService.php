<?php

namespace App\Services;

use App\Models\FusionCache;
use App\Models\Gateways;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GatewayService
{
    public function saveData(array $validated, ?Gateways $gateway = null): array
    {
        $domainUuid = userCheckPermission('gateway_domain')
            ? (array_key_exists('domain_uuid', $validated) ? $validated['domain_uuid'] : $gateway?->domain_uuid)
            : ($gateway?->domain_uuid ?? session('domain_uuid'));

        return [
            'gateway_uuid' => $gateway?->gateway_uuid ?? (string) Str::uuid(),
            'domain_uuid' => $domainUuid,
            'gateway' => $this->nullable($validated['gateway'] ?? null),
            'username' => $this->nullable($validated['username'] ?? null),
            'password' => $this->nullable($validated['password'] ?? null),
            'distinct_to' => $this->nullable($validated['distinct_to'] ?? null),
            'auth_username' => $this->nullable($validated['auth_username'] ?? null),
            'realm' => $this->nullable($validated['realm'] ?? null),
            'from_user' => $this->nullable($validated['from_user'] ?? null),
            'from_domain' => $this->nullable($validated['from_domain'] ?? null),
            'proxy' => $this->nullable($validated['proxy'] ?? null),
            'register_proxy' => $this->nullable($validated['register_proxy'] ?? null),
            'outbound_proxy' => $this->nullable($validated['outbound_proxy'] ?? null),
            'expire_seconds' => (string) ($validated['expire_seconds'] ?? 800),
            'register' => $validated['register'] ?? 'true',
            'register_transport' => $this->nullable($validated['register_transport'] ?? null),
            'contact_params' => $this->nullable($validated['contact_params'] ?? null),
            'retry_seconds' => (string) ($validated['retry_seconds'] ?? 30),
            'extension' => $this->nullable($validated['extension'] ?? null),
            'ping' => $this->nullable($validated['ping'] ?? null),
            'ping_min' => $this->nullable($validated['ping_min'] ?? null),
            'ping_max' => $this->nullable($validated['ping_max'] ?? null),
            'contact_in_ping' => $this->nullable($validated['contact_in_ping'] ?? null),
            'channels' => userCheckPermission('gateway_channels')
                ? ($validated['channels'] ?? 0)
                : ($gateway?->channels ?? 0),
            'caller_id_in_from' => $this->nullable($validated['caller_id_in_from'] ?? null),
            'supress_cng' => $this->nullable($validated['supress_cng'] ?? null),
            'sip_cid_type' => $this->nullable($validated['sip_cid_type'] ?? null),
            'codec_prefs' => $this->nullable($validated['codec_prefs'] ?? null),
            'extension_in_contact' => $this->nullable($validated['extension_in_contact'] ?? null),
            'context' => $this->nullable($validated['context'] ?? 'public'),
            'profile' => $this->nullable($validated['profile'] ?? 'external'),
            'hostname' => $this->nullable($validated['hostname'] ?? null),
            'enabled' => $validated['enabled'] ?? 'true',
            'description' => $this->nullable($validated['description'] ?? null),
        ];
    }

    /** Fields emitted into the generated Sofia gateway configuration. */
    private const RUNTIME_FIELDS = [
        'username',
        'distinct_to',
        'auth_username',
        'password',
        'realm',
        'from_user',
        'from_domain',
        'proxy',
        'register_proxy',
        'outbound_proxy',
        'expire_seconds',
        'register',
        'register_transport',
        'contact_params',
        'retry_seconds',
        'extension',
        'ping',
        'ping_min',
        'ping_max',
        'contact_in_ping',
        'context',
        'caller_id_in_from',
        'supress_cng',
        'extension_in_contact',
        'sip_cid_type',
    ];

    /** Fields that control whether and where the gateway is loaded. */
    private const RUNTIME_LOCATION_FIELDS = [
        'profile',
        'hostname',
        'enabled',
    ];

    public function runtimeState(Gateways $gateway): array
    {
        return [
            'profile' => $gateway->profile ?: 'external',
            'hostname' => $gateway->hostname,
            'enabled' => $gateway->enabled,
        ];
    }

    /**
     * Kills the gateway so the following rescan recreates it with the new data.
     *
     * Only worth doing when the edit changes generated gateway configuration:
     * killgw unregisters the trunk briefly, which is too much to pay for a
     * description-only change.
     */
    public function reloadRegistration(Gateways $gateway, array $changed, array $before): bool
    {
        $changedFields = array_keys($changed);
        $runtimeChanged = array_intersect($changedFields, self::RUNTIME_FIELDS) !== [];
        $locationChanged = array_intersect($changedFields, self::RUNTIME_LOCATION_FIELDS) !== [];

        if (! $runtimeChanged && ! $locationChanged) {
            return true;
        }

        $service = $this->makeEslService();

        if (! $service->isConnected()) {
            logger('GatewayService: unable to connect to FreeSWITCH; gateway reload was deferred.');

            return false;
        }

        try {
            $switchName = trim((string) $service->executeCommand('switchname', false));

            if (! $this->commandSucceeded($switchName)) {
                logger('GatewayService: unable to determine the local FreeSWITCH switchname.');

                return false;
            }

            $after = $this->runtimeState($gateway);
            $beforeActive = $this->isActiveOnSwitch($before, $switchName);
            $afterActive = $this->isActiveOnSwitch($after, $switchName);
            $profileChanged = $before['profile'] !== $after['profile'];

            if (! $beforeActive || (! $runtimeChanged && $afterActive && ! $profileChanged)) {
                return true;
            }

            $profile = "'" . addcslashes($before['profile'], "\\'") . "'";
            $response = $service->executeCommand(sprintf(
                'sofia profile %s killgw %s',
                $profile,
                $gateway->gateway_uuid
            ), false);

            if (! $this->commandSucceeded($response)) {
                logger('GatewayService: FreeSWITCH failed to remove gateway ' . $gateway->gateway_uuid . ' before rescan.');

                return false;
            }

            return true;
        } finally {
            $service->disconnect();
        }
    }

    public function sync(Collection|array|null $profiles = null): bool
    {
        $profiles = collect($profiles)
            ->filter()
            ->unique()
            ->values();

        if ($profiles->isEmpty()) {
            $profiles = Gateways::query()
                ->where(function ($query) {
                    $query->where('domain_uuid', session('domain_uuid'))
                        ->orWhereNull('domain_uuid');
                })
                ->whereNotNull('profile')
                ->distinct()
                ->pluck('profile');
        }

        $service = $this->makeEslService();

        if (! $service->isConnected()) {
            logger('GatewayService: unable to connect to FreeSWITCH; gateway runtime synchronization failed.');

            return false;
        }

        $success = true;

        try {
            $switchName = trim((string) $service->executeCommand('switchname', false));

            if (! $this->commandSucceeded($switchName)) {
                logger('GatewayService: unable to determine the local FreeSWITCH switchname.');
                $success = false;
            } elseif (! $this->clearCacheKey('configuration:sofia.conf:' . $switchName)) {
                logger('GatewayService: unable to clear the generated Sofia configuration cache.');
                $success = false;
            }

            foreach ($profiles as $profile) {
                $quotedProfile = "'" . addcslashes($profile, "\\'") . "'";
                $response = $service->executeCommand(
                    "sofia profile {$quotedProfile} rescan",
                    false
                );

                if (! $this->commandSucceeded($response)) {
                    logger('GatewayService: FreeSWITCH failed to rescan SIP profile ' . $profile . '.');
                    $success = false;
                }
            }
        } finally {
            $service->disconnect();
        }

        return $success;
    }

    public function executeGatewayCommand(string $action, Gateways $gateway): ?string
    {
        if ($gateway->enabled !== 'true') {
            return 'Skipped: gateway is disabled.';
        }

        $command = match ($action) {
            'start' => sprintf('sofia profile %s startgw %s', $gateway->profile ?: 'external', $gateway->gateway_uuid),
            'stop' => sprintf('sofia profile %s killgw %s', $gateway->profile ?: 'external', $gateway->gateway_uuid),
            default => null,
        };

        if (!$command) {
            return null;
        }

        $service = $this->makeEslService();

        if (!$service->isConnected()) {
            return '-ERR Could not connect to FreeSWITCH event socket.';
        }

        return (string) $service->executeCommand($command);
    }

    protected function makeEslService(): FreeswitchEslService
    {
        return new FreeswitchEslService();
    }

    protected function clearCacheKey(string $key): bool
    {
        return FusionCache::clear($key);
    }

    protected function isActiveOnSwitch(array $state, string $switchName): bool
    {
        if (($state['enabled'] ?? null) !== 'true' || blank($state['profile'] ?? null)) {
            return false;
        }

        $hostname = trim((string) ($state['hostname'] ?? ''));

        return $hostname === '' || $hostname === $switchName;
    }

    protected function commandSucceeded(mixed $response): bool
    {
        if (! is_string($response) || trim($response) === '') {
            return false;
        }

        return ! preg_match(
            '/(?:^-ERR\b|\bFailure\b|Invalid Profile|cannot find config|No Such Profile)/im',
            $response
        );
    }

    private function nullable($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return filled($value) ? (string) $value : null;
    }
}
