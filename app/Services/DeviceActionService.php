<?php

namespace App\Services;

use App\Jobs\SendEventNotify;
use Illuminate\Support\Collection;

class DeviceActionService
{
    private const BULK_ACTIONS_PER_MINUTE = 50;

    private const BULK_ACTIONS_PER_SOURCE_PER_MINUTE = 5;

    public function handleDeviceAction($reg, $action, int $delaySeconds = 0): bool
    {
        $prepared = $this->prepareDeviceAction($reg, $action);

        if ($prepared === null) {
            return false;
        }

        logger($prepared['command']);

        $dispatch = SendEventNotify::dispatch($prepared['command'])
            ->onQueue('default');

        if ($delaySeconds > 0) {
            $dispatch->delay($delaySeconds);
        }

        return true;
    }

    /**
     * Schedule a bulk phone action without creating a burst from one customer NAT IP.
     *
     * Registrations from different source addresses are interleaved. The whole fleet
     * is paced at 50 actions per minute, while any one source address is paced at five
     * actions per minute. This lets small sites start immediately without allowing a
     * large site to send a provisioning surge from one public address.
     */
    public function scheduleDeviceActions(iterable $registrations, string $action): int
    {
        $groups = collect($registrations)
            ->values()
            ->map(function ($registration, int $index) use ($action) {
                $prepared = $this->prepareDeviceAction($registration, $action);

                if ($prepared === null) {
                    return null;
                }

                return [
                    'command' => $prepared['command'],
                    'source' => $this->sourceKey($registration, $index),
                ];
            })
            ->filter()
            ->groupBy('source')
            ->map(fn (Collection $group) => $group->values());

        if ($groups->isEmpty()) {
            return 0;
        }

        $globalSpacing = 60 / self::BULK_ACTIONS_PER_MINUTE;
        $sourceSpacing = 60 / self::BULK_ACTIONS_PER_SOURCE_PER_MINUTE;
        $globalAvailableAt = 0.0;
        $sourceAvailableAt = [];
        $scheduled = 0;
        $lastDelay = 0;

        // Round-robin across source addresses so a 40-phone site cannot make a
        // two-phone site wait for all of its jobs to be queued first.
        while ($groups->isNotEmpty()) {
            foreach ($groups->keys() as $source) {
                /** @var Collection<int, array{command: string, source: string}> $group */
                $group = $groups->get($source);
                $prepared = $group->shift();

                if ($group->isEmpty()) {
                    $groups->forget($source);
                } else {
                    $groups->put($source, $group);
                }

                $availableAt = max(
                    $globalAvailableAt,
                    $sourceAvailableAt[$source] ?? 0.0
                );
                $delaySeconds = (int) ceil($availableAt);

                $dispatch = SendEventNotify::dispatch($prepared['command'])
                    ->onQueue('default');

                if ($delaySeconds > 0) {
                    $dispatch->delay($delaySeconds);
                }

                logger($prepared['command']);

                $scheduled++;
                $lastDelay = max($lastDelay, $delaySeconds);
                $globalAvailableAt = $availableAt + $globalSpacing;
                $sourceAvailableAt[$source] = $availableAt + $sourceSpacing;
            }
        }

        logger()->info('Bulk device action schedule created', [
            'action' => $action,
            'scheduled' => $scheduled,
            'source_count' => count($sourceAvailableAt),
            'last_delay_seconds' => $lastDelay,
        ]);

        return $scheduled;
    }

    /**
     * @return array{command: string}|null
     */
    private function prepareDeviceAction(array $reg, string $action): ?array
    {
        $agent = $this->determineAgent((string) ($reg['agent'] ?? ''));

        if (! $agent) {
            return null;
        }

        // Cisco SPA phones use their provisioning action for both sync and restart.
        if ($agent === 'cisco-spa') {
            $action = 'provision';
        }

        $command = $this->generateCommand($reg, $action, $agent);

        return $command !== null ? ['command' => $command] : null;
    }

    private function sourceKey(array $registration, int $index): string
    {
        $source = trim((string) (
            $registration['wan_ip']
            ?? $registration['network_ip']
            ?? $registration['network-ip']
            ?? ''
        ));

        if (filter_var($source, FILTER_VALIDATE_IP) !== false) {
            return 'ip:'.strtolower($source);
        }

        // Do not put every registration with incomplete address data into one very
        // slow bucket. The global schedule still protects FreeSWITCH and provisioning.
        $identity = (string) (
            $registration['call_id']
            ?? $registration['call-id']
            ?? $registration['user']
            ?? $index
        );

        return 'unknown:'.hash('sha256', $identity.'|'.$index);
    }

    protected function determineAgent($agentString)
    {
        if (preg_match('/Bria|Push|Ringotel/i', $agentString)) {
            return null;
        } elseif (preg_match('/polycom|polyedge/i', $agentString)) {
            return "polycom";
        } elseif (preg_match("/yealink/i", $agentString)) {
            return "yealink";
        } elseif (preg_match("/grandstream/i", $agentString)) {
            return "grandstream";
        } elseif (preg_match("/Cisco/i", $agentString)) {
            return "cisco-spa";
        } elseif (preg_match("/Algo/i", $agentString)) {
            return "polycom";
        } elseif (preg_match("/snom/i", $agentString)) {
                return "yealink"; //This is correct, Snom and Yealink are the same
        } elseif (preg_match("/sangoma/i", $agentString)) {
            return "sangoma";
        } elseif (preg_match("/htek/i", $agentString)) {
            return "htek";
        } elseif (preg_match("/Obihai/i", $agentString)) {
            return "obihai";
        } elseif (preg_match("/panasonic/i", $agentString)) {
            return "panasonic";
        }

        // Unknown hardphone vendors: treat like Yealink for event_notify templates
        return "yealink";
    }

    protected function generateCommand($reg, $action, $vendor)
    {
        switch ($action) {
            case "unregister":
                return "fs_cli -x 'sofia profile " . $reg['sip_profile_name'] . " flush_inbound_reg " . $reg['user'] . " reboot'";
            
            case "provision":
                return "fs_cli -x 'luarun app.lua event_notify " . $reg['sip_profile_name'] . " check_sync " . $reg['user'] . " " . $vendor . "'";

            case "reboot":
                return "fs_cli -x 'luarun app.lua event_notify " . $reg['sip_profile_name'] . " reboot " . $reg['user'] . " " . $vendor . "'";

            default:
                return null; // No valid action, return null
        }
    }
}
