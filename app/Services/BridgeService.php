<?php

namespace App\Services;

use App\Models\Bridge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BridgeService
{
    public function save(array $validated, ?Bridge $bridge = null): Bridge
    {
        return DB::transaction(function () use ($validated, $bridge) {
            $bridge ??= new Bridge();
            $isNew = ! $bridge->exists;
            $bridgeUuid = $bridge->bridge_uuid ?: (string) Str::uuid();

            $bridge->forceFill([
                'domain_uuid' => session('domain_uuid'),
                'bridge_uuid' => $bridgeUuid,
                'bridge_name' => $validated['bridge_name'],
                'bridge_destination' => $this->bridgeDestination($validated),
                'bridge_enabled' => $validated['bridge_enabled'],
                'bridge_description' => $this->blankToNull($validated['bridge_description'] ?? null),
                $isNew ? 'insert_date' : 'update_date' => now(),
                $isNew ? 'insert_user' : 'update_user' => session('user_uuid'),
            ])->save();

            $this->clearDestinations();

            return $bridge;
        });
    }

    public function toggle(Collection $bridges): void
    {
        DB::transaction(function () use ($bridges) {
            foreach ($bridges as $bridge) {
                $bridge->forceFill([
                    'bridge_enabled' => $bridge->bridge_enabled === 'true' ? 'false' : 'true',
                    'update_date' => now(),
                    'update_user' => session('user_uuid'),
                ])->save();
            }
        });

        $this->clearDestinations();
    }

    public function delete(Collection $bridges): int
    {
        return DB::transaction(function () use ($bridges) {
            $deleted = Bridge::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('bridge_uuid', $bridges->pluck('bridge_uuid'))
                ->delete();

            $this->clearDestinations();

            return $deleted;
        });
    }

    public function copy(Collection $bridges): int
    {
        return DB::transaction(function () use ($bridges) {
            $count = 0;

            foreach ($bridges as $bridge) {
                $copy = $bridge->replicate();
                $copy->bridge_uuid = (string) Str::uuid();
                $copy->bridge_description = trim((string) $bridge->bridge_description . ' (copy)') ?: null;
                $copy->insert_date = now();
                $copy->insert_user = session('user_uuid');
                $copy->update_date = null;
                $copy->update_user = null;
                $copy->save();
                $count++;
            }

            $this->clearDestinations();

            return $count;
        });
    }

    public function bridgeDestination(array $validated): ?string
    {
        $action = $validated['bridge_action'] ?? null;
        $destinationNumber = trim((string) ($validated['destination_number'] ?? ''));
        $destination = $this->blankToNull($validated['bridge_destination'] ?? null);

        if (in_array($action, ['user', 'loopback'], true)) {
            $destination = $destinationNumber === '' ? null : $action . '/' . $destinationNumber;
        }

        if ($action === 'gateway') {
            $bridges = collect([
                $validated['bridge_gateway_1'] ?? null,
                $validated['bridge_gateway_2'] ?? null,
                $validated['bridge_gateway_3'] ?? null,
            ])
                ->filter()
                ->map(function (string $gateway) use ($destinationNumber) {
                    $gatewayUuid = explode(':', $gateway, 2)[0];

                    return $destinationNumber === '' ? null : 'sofia/gateway/' . $gatewayUuid . '/' . $destinationNumber;
                })
                ->filter()
                ->implode(',');

            $destination = $bridges === '' ? $destination : $bridges;
        }

        if ($action === 'profile') {
            $profile = trim((string) ($validated['bridge_profile'] ?? ''));
            $destination = $profile === '' || $destinationNumber === ''
                ? $destination
                : 'sofia/' . $profile . '/' . $destinationNumber;
        }

        if (in_array($action, ['gateway', 'profile'], true)) {
            $variables = $this->bridgeVariables($validated['bridge_variables'] ?? []);
            if ($variables !== null && $destination !== null) {
                $destination = '{' . $variables . '}' . $destination;
            }
        }

        return $destination;
    }

    public function parseDestination(?string $destination): array
    {
        $result = [
            'bridge_action' => null,
            'bridge_profile' => null,
            'bridge_gateways' => [],
            'destination_number' => null,
            'bridge_destination' => $destination,
            'bridge_variables' => [],
        ];

        $destination = trim((string) $destination);
        if ($destination === '') {
            return $result;
        }

        if (preg_match('/^\{([^}]+)\}/', $destination, $matches)) {
            foreach (explode(',', $matches[1]) as $pair) {
                [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
                if ($name !== '') {
                    $result['bridge_variables'][$name] = $value;
                }
            }
            $destination = substr($destination, strlen($matches[0]));
        }

        $actions = explode(',', $destination);
        $first = $actions[0] ?? '';
        $parts = explode('/', $first);

        if (($parts[0] ?? null) === 'sofia' && ($parts[1] ?? null) === 'gateway') {
            $result['bridge_action'] = 'gateway';
            $result['bridge_gateways'] = collect($actions)
                ->map(fn ($action) => explode('/', $action)[2] ?? null)
                ->filter()
                ->values()
                ->all();
            $result['destination_number'] = $parts[3] ?? null;
        } elseif (($parts[0] ?? null) === 'sofia') {
            $result['bridge_action'] = 'profile';
            $result['bridge_profile'] = $parts[1] ?? null;
            $result['destination_number'] = $parts[2] ?? null;
        } elseif (in_array($parts[0] ?? null, ['user', 'loopback'], true)) {
            $result['bridge_action'] = $parts[0];
            $result['destination_number'] = $parts[1] ?? null;
        }

        return $result;
    }

    private function bridgeVariables(array $variables): ?string
    {
        $value = collect($variables)
            ->map(fn ($value, $key) => trim((string) $key) === '' || trim((string) $value) === ''
                ? null
                : trim((string) $key) . '=' . trim((string) $value))
            ->filter()
            ->implode(',');

        return $value === '' ? null : $value;
    }

    private function clearDestinations(): void
    {
        session()->forget('destinations.array');
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
