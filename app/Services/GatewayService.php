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

    public function sync(Collection|array|null $profiles = null): void
    {
        $profiles = collect($profiles)
            ->filter()
            ->unique()
            ->values();

        $this->clearSofiaCache();
        $this->rescanProfiles($profiles);

        session(['reload_xml' => false]);
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

        $service = new FreeswitchEslService();

        if (!$service->isConnected()) {
            return '-ERR Could not connect to FreeSWITCH event socket.';
        }

        return (string) $service->executeCommand($command);
    }

    private function rescanProfiles(Collection $profiles): void
    {
        $profiles = $profiles->isNotEmpty()
            ? $profiles
            : Gateways::query()
                ->where(function ($query) {
                    $query->where('domain_uuid', session('domain_uuid'))
                        ->orWhereNull('domain_uuid');
                })
                ->whereNotNull('profile')
                ->distinct()
                ->pluck('profile');

        $service = new FreeswitchEslService();

        if (!$service->isConnected()) {
            return;
        }

        $profiles->filter()->unique()->values()->each(function (string $profile) use ($service) {
            $service->executeCommand("sofia profile {$profile} rescan", false);
        });

        $service->disconnect();
    }

    private function clearSofiaCache(): void
    {
        $service = new FreeswitchEslService();
        $hostname = $service->isConnected()
            ? trim((string) $service->executeCommand('switchname'))
            : null;

        if (filled($hostname)) {
            FusionCache::clear('configuration:sofia.conf:' . $hostname);
        }
    }

    private function nullable($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return filled($value) ? (string) $value : null;
    }
}
