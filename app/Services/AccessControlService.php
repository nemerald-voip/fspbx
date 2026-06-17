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
        $managedLists = $this->managedGatewayLists($gateway);

        $this->deleteProviderMirrorNodes((string) $gateway->gateway_uuid);
        $this->deleteGatewayLists($managedLists);

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
                'node_description' => $description,
            ]);
        }
    }

    public function removeGatewayProviderIps(Gateways $gateway): void
    {
        $managedLists = $this->managedGatewayLists($gateway);

        $this->deleteProviderMirrorNodes((string) $gateway->gateway_uuid);
        $this->deleteGatewayLists($managedLists);
    }

    public function removeGatewayProviderIpsForListName(string $listName): void
    {
        if (strtolower(trim($listName)) === self::PROVIDERS_LIST) {
            return;
        }

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
                    ->where('node_description', 'like', 'Managed gateway:%')
                    ->where('node_description', 'like', '%' . strtolower($gatewayUuid) . '%')
                    ->whereHas('accessControl', function ($query) {
                        $query->where('access_control_name', '!=', self::PROVIDERS_LIST);
                    })
                    ->delete();
            });
    }

    public function preserveProviderIpsForList(AccessControl $accessControl): void
    {
        if (strtolower(trim((string) $accessControl->access_control_name)) === self::PROVIDERS_LIST) {
            return;
        }

        $nodesByGateway = $accessControl->nodes
            ->filter(fn ($node) => $node->node_type === 'allow' && filled($node->node_cidr))
            ->mapToGroups(function ($node) {
                $gatewayUuid = $this->gatewayUuidFromDescription($node->node_description);

                return $gatewayUuid
                    ? [$gatewayUuid => $node]
                    : [];
            });

        if ($nodesByGateway->isEmpty()) {
            return;
        }

        $providers = AccessControl::query()
            ->firstOrNew(['access_control_name' => self::PROVIDERS_LIST]);

        $providers->forceFill([
            'access_control_default' => 'deny',
            'access_control_description' => $providers->access_control_description ?: 'Provider IP access control list.',
        ])->save();

        $nodesByGateway->each(function (Collection $nodes, string $gatewayUuid) use ($accessControl, $providers) {
            $description = $this->gatewayNodeDescriptionForList($accessControl, $gatewayUuid);

            if ($providers->nodes()
                ->where('node_description', 'like', 'Managed gateway:%')
                ->where('node_description', 'like', '%' . strtolower($gatewayUuid) . '%')
                ->exists()
            ) {
                return;
            }

            $nodes->each(function ($node) use ($providers, $description) {
                $providers->nodes()->create([
                    'node_type' => 'allow',
                    'node_cidr' => $node->node_cidr,
                    'node_description' => $description,
                ]);
            });
        });
    }

    public function mirrorManagedGatewayList(AccessControl $accessControl): void
    {
        if (strtolower(trim((string) $accessControl->access_control_name)) === self::PROVIDERS_LIST) {
            return;
        }

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
                'node_description' => $this->gatewayNodeDescriptionForList($accessControl, $gatewayUuid),
            ]);
        }
    }

    public function gatewayCidrs(Gateways $gateway): Collection
    {
        $providerCidrs = AccessControlNode::query()
            ->where('node_description', 'like', 'Managed gateway:%')
            ->where('node_description', 'like', '%' . strtolower((string) $gateway->gateway_uuid) . '%')
            ->where('node_type', 'allow')
            ->whereHas('accessControl', function ($query) {
                $query->where('access_control_name', self::PROVIDERS_LIST);
            })
            ->pluck('node_cidr')
            ->filter()
            ->values();

        if ($providerCidrs->isNotEmpty()) {
            return $providerCidrs;
        }

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
            ->where('node_description', 'like', 'Managed gateway:%')
            ->where('node_description', 'like', '%' . strtolower((string) $gateway->gateway_uuid) . '%')
            ->where('node_type', 'allow')
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

    private function deleteProviderMirrorNodes(string $gatewayUuid): void
    {
        AccessControlNode::query()
            ->where('node_description', 'like', 'Managed gateway:%')
            ->where('node_description', 'like', '%' . strtolower($gatewayUuid) . '%')
            ->whereHas('accessControl', function ($query) {
                $query->where('access_control_name', self::PROVIDERS_LIST);
            })
            ->delete();
    }

    private function gatewayNodeDescription(Gateways $gateway): string
    {
        $gatewayUuid = strtolower((string) $gateway->gateway_uuid);
        $name = $this->normalizeGatewayName($gateway->gateway ?: $gateway->description);

        if (!$name) {
            return $this->gatewayNodeDescriptionFromUuid($gatewayUuid);
        }

        return $this->gatewayNodeDescriptionWithName($name, $gatewayUuid);
    }

    private function gatewayNodeDescriptionFromUuid(string $gatewayUuid): string
    {
        return 'Managed gateway:' . strtolower($gatewayUuid);
    }

    private function gatewayNodeDescriptionForList(AccessControl $accessControl, string $gatewayUuid): string
    {
        $name = $this->normalizeGatewayName((string) $accessControl->access_control_name);

        if ($name && str_ends_with(strtolower($name), ' provider ips')) {
            $name = trim(substr($name, 0, -strlen(' Provider IPs')));
        }

        return $name
            ? $this->gatewayNodeDescriptionWithName($name, $gatewayUuid)
            : $this->gatewayNodeDescriptionFromUuid($gatewayUuid);
    }

    private function gatewayNodeDescriptionWithName(string $name, string $gatewayUuid): string
    {
        $prefix = 'Managed gateway: ';
        $gatewayUuid = strtolower($gatewayUuid);
        $suffix = ' (' . $gatewayUuid . ')';
        $maxNameLength = max(1, 255 - strlen($prefix) - strlen($suffix));
        $name = substr($this->normalizeGatewayName($name) ?: '', 0, $maxNameLength);

        return $prefix . $name . $suffix;
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
        $gatewayUuid = strtolower((string) $gateway->gateway_uuid);
        $legacyName = 'gateway_' . strtolower((string) $gateway->gateway_uuid);
        $currentName = $this->gatewayListName($gateway);

        return AccessControl::query()
            ->where('access_control_name', '!=', self::PROVIDERS_LIST)
            ->where(function ($query) use ($gatewayUuid, $legacyName, $currentName) {
                $query->where('access_control_name', $legacyName)
                    ->orWhere('access_control_name', $currentName)
                    ->orWhereHas('nodes', function ($query) use ($gatewayUuid) {
                        $query->where('node_description', 'like', 'Managed gateway:%')
                            ->where('node_description', 'like', '%' . $gatewayUuid . '%');
                    });
            })
            ->get();
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
            ->map(fn ($description) => $this->gatewayUuidFromDescription($description))
            ->filter()
            ->unique()
            ->values();
    }

    private function gatewayUuidFromDescription(mixed $description): ?string
    {
        if (!is_string($description)) {
            return null;
        }

        if (!str_starts_with(strtolower(trim($description)), 'managed gateway:')) {
            return null;
        }

        return preg_match('/\b([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\b/i', $description, $matches)
            ? strtolower($matches[1])
            : null;
    }

    private function normalizeGatewayName(mixed $name): ?string
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));

        return $name !== '' ? $name : null;
    }

    private function normalizeDefault(string $default): string
    {
        return in_array($default, ['allow', 'deny'], true) ? $default : 'deny';
    }
}
