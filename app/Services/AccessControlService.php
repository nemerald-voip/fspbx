<?php

namespace App\Services;

use App\Models\AccessControl;
use App\Models\AccessControlNode;
use App\Models\FusionCache;
use App\Models\Gateways;
use Illuminate\Support\Collection;

class AccessControlService
{
    public const PROVIDERS_LIST = 'providers';

    public function saveAccessControl(AccessControl $accessControl, array $validated): AccessControl
    {
        $accessControl->forceFill([
            'access_control_name' => $validated['access_control_name'],
            'access_control_default' => $this->normalizeDefault($validated['access_control_default'] ?? 'deny'),
            'access_control_description' => $validated['access_control_description'] ?? null,
        ])->save();

        $this->replaceNodes($accessControl, $validated['nodes'] ?? []);
        $this->mirrorManagedGatewayList($accessControl);

        return $accessControl;
    }

    public function replaceNodes(AccessControl $accessControl, array $nodes): void
    {
        $accessControl->nodes()->delete();

        foreach ($nodes as $node) {
            $cidr = $this->normalizeCidr($node['node_cidr'] ?? null);

            if (!$cidr) {
                continue;
            }

            $accessControl->nodes()->create([
                'node_type' => in_array($node['node_type'] ?? 'allow', ['allow', 'deny'], true) ? $node['node_type'] : 'allow',
                'node_cidr' => $cidr,
                'node_description' => $node['node_description'] ?? null,
            ]);
        }
    }

    public function syncGatewayProviderIps(Gateways $gateway, mixed $value): void
    {
        $cidrs = $this->normalizeCidrs($value);
        $description = $this->gatewayNodeDescription($gateway);
        $gatewayListName = $this->gatewayListName($gateway);
        $managedLists = $this->managedGatewayLists($gateway);

        $this->deleteGatewayProviderNodes($gateway);
        $this->deleteOldGatewayLists($managedLists, $gatewayListName);

        if ($cidrs->isEmpty()) {
            $this->deleteGatewayLists($managedLists->where('access_control_name', $gatewayListName));

            return;
        }

        $gatewayList = AccessControl::query()
            ->firstOrNew(['access_control_name' => $gatewayListName]);

        $gatewayList->forceFill([
            'access_control_default' => 'deny',
            'access_control_description' => 'Provider IPs for ' . ($gateway->gateway ?: $gateway->gateway_uuid),
        ])->save();

        $this->replaceNodes($gatewayList, $cidrs->map(fn ($cidr) => [
            'node_type' => 'allow',
            'node_cidr' => $cidr,
            'node_description' => $description,
        ])->all());

        $providers = AccessControl::query()
            ->firstOrNew(['access_control_name' => self::PROVIDERS_LIST]);

        $providers->forceFill([
            'access_control_default' => 'deny',
            'access_control_description' => $providers->access_control_description ?: 'Provider IP access control list.',
        ])->save();

        foreach ($cidrs as $cidr) {
            $providers->nodes()->create([
                'node_type' => 'allow',
                'node_cidr' => $cidr,
                'node_description' => $description,
            ]);
        }
    }

    public function removeGatewayProviderIps(Gateways $gateway): void
    {
        $managedLists = $this->managedGatewayLists($gateway);

        $this->deleteGatewayProviderNodes($gateway);
        $this->deleteGatewayLists($managedLists);
    }

    public function removeGatewayProviderIpsForListName(string $listName): void
    {
        $accessControl = AccessControl::query()
            ->with('nodes')
            ->where('access_control_name', $listName)
            ->first();

        if (!$accessControl) {
            return;
        }

        $this->gatewayUuidsForAccessControl($accessControl)
            ->each(function (string $gatewayUuid) {
                AccessControlNode::query()
                    ->where('node_description', $this->gatewayNodeDescriptionFromUuid($gatewayUuid))
                    ->delete();
            });
    }

    public function mirrorManagedGatewayList(AccessControl $accessControl): void
    {
        $gatewayUuid = $this->gatewayUuidFromListName((string) $accessControl->access_control_name)
            ?? $this->gatewayUuidsForAccessControl($accessControl)->first();

        if (!$gatewayUuid) {
            return;
        }

        $this->deleteProviderMirrorNodes($gatewayUuid);

        $cidrs = $accessControl->nodes()
            ->where('node_type', 'allow')
            ->pluck('node_cidr')
            ->filter()
            ->values();

        if ($cidrs->isEmpty()) {
            return;
        }

        $providers = AccessControl::query()
            ->firstOrNew(['access_control_name' => self::PROVIDERS_LIST]);

        $providers->forceFill([
            'access_control_default' => 'deny',
            'access_control_description' => $providers->access_control_description ?: 'Provider IP access control list.',
        ])->save();

        foreach ($cidrs as $cidr) {
            $providers->nodes()->create([
                'node_type' => 'allow',
                'node_cidr' => $cidr,
                'node_description' => $this->gatewayNodeDescriptionFromUuid($gatewayUuid),
            ]);
        }
    }

    public function gatewayCidrs(Gateways $gateway): Collection
    {
        $gatewayList = AccessControl::query()
            ->where('access_control_name', $this->gatewayListName($gateway))
            ->first();

        if ($gatewayList) {
            return $gatewayList->nodes()
                ->where('node_type', 'allow')
                ->pluck('node_cidr')
                ->filter()
                ->values();
        }

        return AccessControlNode::query()
            ->where('node_description', $this->gatewayNodeDescription($gateway))
            ->pluck('node_cidr')
            ->filter()
            ->values();
    }

    public function sync(): ?string
    {
        FusionCache::clear('configuration:acl.conf');

        $service = new FreeswitchEslService();

        if (!$service->isConnected()) {
            return '-ERR Could not connect to FreeSWITCH event socket.';
        }

        return (string) $service->executeCommand('reloadacl');
    }

    public function normalizeCidrs(mixed $value): Collection
    {
        $items = is_array($value)
            ? $value
            : preg_split('/[\r\n,]+/', (string) $value);

        return collect($items)
            ->map(fn ($item) => is_array($item) ? ($item['node_cidr'] ?? null) : $item)
            ->map(fn ($item) => $this->normalizeCidr($item))
            ->filter()
            ->unique()
            ->values();
    }

    public function normalizeCidr(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        if (blank($value)) {
            return null;
        }

        $parts = explode('/', str_replace('\\', '/', (string) $value), 2);
        $ip = $parts[0] ?? null;

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $prefix = $parts[1] ?? 32;

            return is_numeric($prefix) && $prefix >= 0 && $prefix <= 32
                ? "{$ip}/{$prefix}"
                : null;
        }

        $prefix = $parts[1] ?? null;

        if ($prefix === null) {
            return $ip;
        }

        return is_numeric($prefix) && $prefix >= 0 && $prefix <= 128
            ? "{$ip}/{$prefix}"
            : null;
    }

    public function gatewayListName(Gateways $gateway): string
    {
        $name = trim((string) ($gateway->gateway ?: $gateway->gateway_uuid));
        $name = preg_replace('/\s+/', ' ', $name);

        return substr($name . ' Provider IPs', 0, 255);
    }

    private function deleteGatewayProviderNodes(Gateways $gateway): void
    {
        AccessControlNode::query()
            ->where('node_description', $this->gatewayNodeDescription($gateway))
            ->delete();
    }

    private function deleteProviderMirrorNodes(string $gatewayUuid): void
    {
        AccessControlNode::query()
            ->where('node_description', $this->gatewayNodeDescriptionFromUuid($gatewayUuid))
            ->whereHas('accessControl', function ($query) {
                $query->where('access_control_name', self::PROVIDERS_LIST);
            })
            ->delete();
    }

    private function gatewayNodeDescription(Gateways $gateway): string
    {
        return $this->gatewayNodeDescriptionFromUuid((string) $gateway->gateway_uuid);
    }

    private function gatewayNodeDescriptionFromUuid(string $gatewayUuid): string
    {
        return 'Managed gateway:' . strtolower($gatewayUuid);
    }

    private function gatewayUuidFromListName(string $listName): ?string
    {
        if (!str_starts_with($listName, 'gateway_')) {
            return null;
        }

        $gatewayUuid = substr($listName, strlen('gateway_'));

        return preg_match('/^[0-9a-f-]{36}$/', $gatewayUuid) ? strtolower($gatewayUuid) : null;
    }

    private function managedGatewayLists(Gateways $gateway): Collection
    {
        $description = $this->gatewayNodeDescription($gateway);
        $legacyName = 'gateway_' . strtolower((string) $gateway->gateway_uuid);
        $currentName = $this->gatewayListName($gateway);

        return AccessControl::query()
            ->where(function ($query) use ($description, $legacyName, $currentName) {
                $query->where('access_control_name', $legacyName)
                    ->orWhere('access_control_name', $currentName)
                    ->orWhereHas('nodes', function ($query) use ($description) {
                        $query->where('node_description', $description);
                    });
            })
            ->get();
    }

    private function deleteOldGatewayLists(Collection $managedLists, string $currentName): void
    {
        $this->deleteGatewayLists(
            $managedLists->reject(fn (AccessControl $accessControl) => $accessControl->access_control_name === $currentName)
        );
    }

    private function deleteGatewayLists(Collection $accessControls): void
    {
        $accessControls->each(function (AccessControl $accessControl) {
            $accessControl->nodes()->delete();
            $accessControl->delete();
        });
    }

    private function gatewayUuidsForAccessControl(AccessControl $accessControl): Collection
    {
        return $accessControl->nodes
            ->pluck('node_description')
            ->map(function ($description) {
                if (!is_string($description)) {
                    return null;
                }

                return preg_match('/Managed gateway:([0-9a-f-]{36})/i', $description, $matches)
                    ? strtolower($matches[1])
                    : null;
            })
            ->filter()
            ->unique()
            ->values();
    }

    private function normalizeDefault(string $default): string
    {
        return in_array($default, ['allow', 'deny'], true) ? $default : 'deny';
    }
}
