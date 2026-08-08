<?php

namespace App\Services;

use App\Models\FusionCache;
use Illuminate\Support\Collection;
use SimpleXMLElement;

class SofiaProfileRuntimeService
{
    public function __construct(
        protected FreeswitchEslService $eslService,
    ) {}

    /**
     * Refresh Sofia's generated configuration and synchronize local profile state.
     *
     * Each transition contains nullable before/after profile state arrays with
     * name, hostname, and enabled keys.
     */
    public function synchronize(Collection $transitions, Collection $hostnames): bool
    {
        $connected = $this->eslService->isConnected();
        $switchName = $connected
            ? trim((string) $this->eslService->executeCommand('switchname', false))
            : null;

        $targets = $hostnames
            ->map(fn ($hostname) => is_string($hostname) ? trim($hostname) : $hostname)
            ->filter()
            ->unique()
            ->values();

        $hasGlobalProfile = $hostnames->contains(
            fn ($hostname) => ! is_string($hostname) || trim($hostname) === ''
        );

        if (($hasGlobalProfile || $targets->isEmpty()) && filled($switchName)) {
            $targets->push($switchName);
        }

        $cacheCleared = $targets
            ->unique()
            ->map(fn (string $hostname) => $this->clearCacheKey(
                'configuration:sofia.conf:' . $hostname
            ))
            ->every(fn (bool $cleared) => $cleared);

        if (! $connected || blank($switchName)) {
            logger('SofiaProfileRuntimeService: unable to connect to FreeSWITCH; profile runtime synchronization was deferred.');
            session(['reload_xml' => true]);

            return false;
        }

        $success = $cacheCleared;

        try {
            if ($targets->contains($switchName)) {
                $response = $this->eslService->executeCommand(
                    'xml_locate configuration configuration name sofia.conf',
                    false
                );

                if (! $this->commandSucceeded($response)) {
                    logger('SofiaProfileRuntimeService: unable to regenerate the Sofia configuration cache.');
                    $success = false;
                }
            }

            foreach ($this->runtimeActions($transitions, $switchName) as $action) {
                $profile = "'" . addcslashes($action['profile'], "\\'") . "'";
                $response = $this->eslService->executeCommand(
                    "sofia profile {$profile} {$action['action']}",
                    false
                );

                if (! $this->commandSucceeded($response)) {
                    logger(
                        'SofiaProfileRuntimeService: FreeSWITCH failed to '
                        . $action['action'] . ' SIP profile ' . $action['profile'] . '.'
                    );
                    $success = false;
                }
            }
        } finally {
            $this->eslService->disconnect();
        }

        session(['reload_xml' => ! $success]);

        return $success;
    }

    protected function clearCacheKey(string $key): bool
    {
        return FusionCache::clear($key);
    }

    protected function runtimeActions(Collection $transitions, string $switchName): Collection
    {
        return $transitions
            ->flatMap(function (array $transition) use ($switchName) {
                $before = $transition['before'] ?? null;
                $after = $transition['after'] ?? null;
                $beforeActive = $this->isActiveOnSwitch($before, $switchName);
                $afterActive = $this->isActiveOnSwitch($after, $switchName);

                if ($beforeActive && ! $afterActive) {
                    return [[
                        'action' => 'stop',
                        'profile' => $before['name'],
                    ]];
                }

                if (! $beforeActive && $afterActive) {
                    return [[
                        'action' => 'start',
                        'profile' => $after['name'],
                    ]];
                }

                if ($beforeActive && $afterActive) {
                    if ($before['name'] !== $after['name']) {
                        return [
                            ['action' => 'stop', 'profile' => $before['name']],
                            ['action' => 'start', 'profile' => $after['name']],
                        ];
                    }

                    return [[
                        'action' => 'rescan',
                        'profile' => $after['name'],
                    ]];
                }

                return [];
            })
            ->values();
    }

    protected function isActiveOnSwitch(?array $state, string $switchName): bool
    {
        if (! $state || ($state['enabled'] ?? null) !== 'true' || blank($state['name'] ?? null)) {
            return false;
        }

        $hostname = trim((string) ($state['hostname'] ?? ''));

        return $hostname === '' || $hostname === $switchName;
    }

    protected function commandSucceeded(mixed $response): bool
    {
        if ($response instanceof SimpleXMLElement) {
            return true;
        }

        if (! is_string($response) || trim($response) === '') {
            return false;
        }

        return ! preg_match(
            '/(?:^-ERR\b|\bFailure\b|Invalid Profile|cannot find config|No Such Profile)/im',
            $response
        );
    }
}
