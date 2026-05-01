<?php

namespace App\Services;

use Illuminate\Support\Collection;

class RegistrationService
{
    private array $searchable = [
        'lan_ip',
        'wan_ip',
        'port',
        'agent',
        'transport',
        'sip_profile_name',
        'sip_auth_user',
        'sip_auth_realm',
    ];

    private array $allowedSortFields = [
        'sip_auth_user',
        'sip_auth_realm',
        'agent',
        'lan_ip',
        'wan_ip',
        'port',
        'status',
        'expsecs',
        'ping_time',
        'sip_profile_name',
    ];

    public function getRegistrations(FreeswitchEslService $eslService, array $options = []): Collection
    {
        $sortField = $this->normalizeSortField($options['sortField'] ?? 'sip_auth_user');
        $sortOrder = $this->normalizeSortOrder($options['sortOrder'] ?? 'asc');
        $search = trim((string) ($options['search'] ?? ''));
        $showGlobal = (bool) ($options['showGlobal'] ?? false);
        $domainName = $options['domain_name'] ?? null;

        $data = $eslService->getAllSipRegistrations();

        $data = $sortOrder === 'asc'
            ? $data->sortBy($sortField)
            : $data->sortByDesc($sortField);

        if (! $showGlobal && $domainName !== null) {
            $data = $data->filter(function ($item) use ($domainName) {
                return ($item['sip_auth_realm'] ?? null) === $domainName;
            });
        }

        if ($search !== '') {
            $data = $data->filter(function ($item) use ($search) {
                foreach ($this->searchable as $field) {
                    if (stripos((string) ($item[$field] ?? ''), $search) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }

        return $data->values();
    }

    public function findRegistrationByCallId(FreeswitchEslService $eslService, string $callId, array $options = []): ?array
    {
        return $this->getRegistrations($eslService, $options)
            ->firstWhere('call_id', $callId);
    }

    public function unregister(
        array $registration,
        FreeswitchEslService $eslService,
        DeviceActionService $deviceActionService
    ): void {
        $profile = (string) ($registration['sip_profile_name'] ?? '');
        $user = (string) ($registration['sip_auth_user'] ?? '');
        $realm = (string) ($registration['sip_auth_realm'] ?? '');
        $target = ($user && $realm) ? "{$user}@{$realm}" : '';

        if (! $eslService->isConnected()) {
            $eslService->reconnect();
        }

        $success = false;
        if ($target !== '' && $profile !== '') {
            $commandsToTry = [
                "sofia profile {$profile} flush_inbound_reg {$target} reboot",
                "sofia profile {$profile} flush_inbound_reg {$target} all reboot",
                "sofia profile {$profile} unregister {$user} {$realm}",
            ];

            foreach ($commandsToTry as $cmd) {
                try {
                    $result = $eslService->executeCommand($cmd, false);

                    if (is_string($result) && preg_match('/\+?OK/i', $result)) {
                        $success = true;
                        break;
                    }
                } catch (\Throwable $e) {
                    // Fall through to next command / fallback logic.
                }
            }
        }

        if (! $success) {
            $deviceActionService->handleDeviceAction($registration, 'unregister');
        }

        $eslService->disconnect();
    }

    public function reboot(array $registration, DeviceActionService $deviceActionService): void
    {
        $deviceActionService->handleDeviceAction($registration, 'reboot');
    }

    public function sync(array $registration, DeviceActionService $deviceActionService): void
    {
        $deviceActionService->handleDeviceAction($registration, 'provision');
    }

    private function normalizeSortField(string $sortField): string
    {
        return in_array($sortField, $this->allowedSortFields, true)
            ? $sortField
            : 'sip_auth_user';
    }

    private function normalizeSortOrder(string $sortOrder): string
    {
        return in_array($sortOrder, ['asc', 'desc'], true)
            ? $sortOrder
            : 'asc';
    }
}
