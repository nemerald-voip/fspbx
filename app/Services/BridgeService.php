<?php

namespace App\Services;

use App\Models\Bridge;
use App\Models\Gateways;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BridgeService
{
    public const IMPORT_MAX_ROWS = 500;

    public function save(array $validated, ?Bridge $bridge = null): Bridge
    {
        return DB::transaction(function () use ($validated, $bridge) {
            $bridge ??= new Bridge();
            $isNew = ! $bridge->exists;
            $bridgeUuid = $bridge->bridge_uuid ?: (string) Str::uuid();

            if (! $isNew && ! is_array($validated['bridge_variables'] ?? null)) {
                $validated['bridge_variables'] = $this->parseDestination(
                    $bridge->bridge_destination
                )['bridge_variables'];
            }

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

            if (array_key_exists('bridge_headers', $validated)) {
                $this->syncHeaders($bridgeUuid, $validated['bridge_headers'] ?? []);
            }

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

    }

    public function delete(Collection $bridges): int
    {
        return DB::transaction(function () use ($bridges) {
            if (Schema::hasTable('bridge_headers')) {
                DB::table('bridge_headers')
                    ->where('domain_uuid', session('domain_uuid'))
                    ->whereIn('bridge_uuid', $bridges->pluck('bridge_uuid'))
                    ->delete();
            }

            $deleted = Bridge::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('bridge_uuid', $bridges->pluck('bridge_uuid'))
                ->delete();

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

                if (Schema::hasTable('bridge_headers')) {
                    $headers = DB::table('bridge_headers')
                        ->where('bridge_uuid', $bridge->bridge_uuid)
                        ->where('domain_uuid', $bridge->domain_uuid)
                        ->orderBy('sort_order')
                        ->get(['header_name', 'header_value']);

                    if ($headers->isNotEmpty()) {
                        DB::table('bridge_headers')->insert(
                            $headers->values()->map(fn ($header, $index) => [
                                'bridge_header_uuid' => (string) Str::uuid(),
                                'bridge_uuid' => $copy->bridge_uuid,
                                'domain_uuid' => $copy->domain_uuid,
                                'header_name' => $header->header_name,
                                'header_value' => $header->header_value,
                                'sort_order' => $index,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ])->all()
                        );
                    }
                }

                $count++;
            }

            return $count;
        });
    }

    /**
     * UUIDs of the gateways the current user can pick in the bridge form.
     */
    public function selectableGatewayUuids(): array
    {
        return Gateways::query()
            ->where('enabled', 'true')
            ->when(! userCheckPermission('outbound_route_any_gateway'), function ($query) {
                $query->where('domain_uuid', session('domain_uuid'));
            })
            ->pluck('gateway_uuid')
            ->map(fn ($uuid) => strtolower((string) $uuid))
            ->all();
    }

    /**
     * Validate CSV rows without touching the database. The heading row is line 1.
     *
     * @return array{rows: array<int, array<string, string|null>>, errors: array<int, string>}
     */
    public function validateImportRows(iterable $rows, array $allowedGatewayUuids): array
    {
        $rows = collect($rows)->map(fn ($row) => collect($row)->all())->values();

        if ($rows->isEmpty()) {
            return ['rows' => [], 'errors' => [__('The file has no data rows.')]];
        }

        $missing = collect(['bridge_name', 'bridge_destination'])
            ->reject(fn ($column) => array_key_exists($column, $rows->first()))
            ->all();

        if ($missing !== []) {
            return ['rows' => [], 'errors' => [__('Missing required column(s): :columns', ['columns' => implode(', ', $missing)])]];
        }

        if ($rows->count() > self::IMPORT_MAX_ROWS) {
            return ['rows' => [], 'errors' => [__('A file can contain at most :max bridges.', ['max' => self::IMPORT_MAX_ROWS])]];
        }

        $allowedGateways = array_flip(array_map('strtolower', $allowedGatewayUuids));
        $clean = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $name = $this->cell($row, 'bridge_name');
            $destination = $this->cell($row, 'bridge_destination');
            $description = $this->cell($row, 'bridge_description');
            $enabled = $this->normalizeEnabled($this->cell($row, 'bridge_enabled'));

            $validator = Validator::make(
                ['bridge_name' => $name, 'bridge_destination' => $destination, 'bridge_description' => $description],
                [
                    'bridge_name' => ['required', 'string', 'max:255'],
                    'bridge_destination' => ['required', 'string'],
                    'bridge_description' => ['nullable', 'string', 'max:255'],
                ]
            );

            $problems = $validator->errors()->all();

            if ($enabled === null) {
                $problems[] = __('bridge_enabled must be true or false.');
            }

            if ($destination !== '' && ($problem = $this->destinationProblem($destination, $allowedGateways)) !== null) {
                $problems[] = $problem;
            }

            foreach ($problems as $problem) {
                $errors[] = __('Row :line: :message', ['line' => $line, 'message' => $problem]);
            }

            if ($problems === []) {
                $clean[] = [
                    'bridge_name' => $name,
                    'bridge_destination' => $destination,
                    'bridge_enabled' => $enabled,
                    'bridge_description' => $description === '' ? null : $description,
                ];
            }
        }

        return ['rows' => $errors === [] ? $clean : [], 'errors' => $errors];
    }

    /**
     * Save validated import rows. All rows are saved or none.
     */
    public function importRows(array $rows): int
    {
        return DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $this->save($row);
            }

            return count($rows);
        });
    }

    private function cell(array $row, string $column): string
    {
        $value = $row[$column] ?? null;

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function normalizeEnabled(string $value): ?string
    {
        $value = strtolower($value);

        return match (true) {
            $value === '', in_array($value, ['true', '1', 'yes', 'y'], true) => 'true',
            in_array($value, ['false', '0', 'no', 'n'], true) => 'false',
            default => null,
        };
    }

    /**
     * An imported destination must be something the bridge form could have produced:
     * known leg formats, gateways the user may use, and only configured or SIP header variables.
     */
    private function destinationProblem(string $destination, array $allowedGateways): ?string
    {
        if (preg_match('/[\x00-\x1F\x7F<>"]/', $destination)) {
            return __('bridge_destination contains characters that are not allowed.');
        }

        $legs = $destination;

        if (str_starts_with($destination, '{')) {
            $block = $this->extractLeadingVariableBlock($destination);

            if ($block === null) {
                return __('bridge_destination has an unclosed variable block.');
            }

            $allowedVariables = collect(session('bridge.variable', []))
                ->map(fn ($variable) => explode('=', (string) $variable, 2)[0])
                ->filter()
                ->all();

            foreach (array_keys($this->parseDestination($destination)['bridge_variables']) as $name) {
                if (! $this->isSipHeaderVariable((string) $name) && ! in_array($name, $allowedVariables, true)) {
                    return __('Variable :name is not allowed in a bridge destination.', ['name' => $name]);
                }
            }

            $legs = substr($destination, strlen($block['raw']));
        }

        foreach (explode(',', $legs) as $leg) {
            if (! preg_match('#^(sofia/gateway/[^/\s]+/\S+|sofia/[^/\s]+/\S+|user/\S+|loopback/\S+)$#', $leg)) {
                return __('bridge_destination must use sofia/gateway/<gateway-uuid>/<number>, sofia/<profile>/<number>, user/<number> or loopback/<number>.');
            }

            if (preg_match('#^sofia/gateway/([^/]+)/#', $leg, $match)
                && (! Str::isUuid($match[1]) || ! isset($allowedGateways[strtolower($match[1])]))) {
                return __('Gateway :gateway is not available to you.', ['gateway' => $match[1]]);
            }
        }

        return null;
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
            $bridgeVariables = $validated['bridge_variables'] ?? [];

            if (array_key_exists('bridge_headers', $validated)) {
                $bridgeVariables = collect($bridgeVariables)
                    ->reject(fn ($value, $key) => $this->isSipHeaderVariable((string) $key))
                    ->all();
            }

            $variables = $this->bridgeVariables($bridgeVariables);

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
            'bridge_headers' => [],
        ];

        $destination = trim((string) $destination);
        if ($destination === '') {
            return $result;
        }

        $variableBlock = $this->extractLeadingVariableBlock($destination);
        if ($variableBlock !== null) {
            foreach (explode(',', $variableBlock['variables']) as $pair) {
                [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
                if ($name !== '') {
                    $result['bridge_variables'][$name] = $value;

                    if ($this->isSipHeaderVariable($name)) {
                        $result['bridge_headers'][] = [
                            'name' => preg_replace('/^sip_h_/i', '', $name),
                            'value' => $value,
                        ];
                    }
                }
            }
            $destination = substr($destination, strlen($variableBlock['raw']));
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

    public function headers(Bridge $bridge, array $fallback = []): array
    {
        if (! $bridge->exists || ! Schema::hasTable('bridge_headers')) {
            return $fallback;
        }

        $headers = DB::table('bridge_headers')
            ->where('bridge_uuid', $bridge->bridge_uuid)
            ->where('domain_uuid', $bridge->domain_uuid)
            ->orderBy('sort_order')
            ->get(['header_name', 'header_value'])
            ->map(fn ($header) => [
                'name' => $header->header_name,
                'value' => $header->header_value,
            ])
            ->all();

        return $headers === [] ? $fallback : $headers;
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

    private function syncHeaders(string $bridgeUuid, array $headers): void
    {
        if (! Schema::hasTable('bridge_headers')) {
            if ($headers !== []) {
                throw new \RuntimeException('The bridge_headers table is missing. Run the database migrations.');
            }

            return;
        }

        DB::table('bridge_headers')
            ->where('bridge_uuid', $bridgeUuid)
            ->where('domain_uuid', session('domain_uuid'))
            ->delete();

        $rows = collect($headers)
            ->map(function ($header, $index) use ($bridgeUuid) {
                if (! is_array($header)) {
                    return null;
                }

                $name = preg_replace('/^sip_h_/i', '', trim((string) ($header['name'] ?? '')));
                $value = trim((string) ($header['value'] ?? ''));

                if ($name === '' || $value === '') {
                    return null;
                }

                return [
                    'bridge_header_uuid' => (string) Str::uuid(),
                    'bridge_uuid' => $bridgeUuid,
                    'domain_uuid' => session('domain_uuid'),
                    'header_name' => $name,
                    'header_value' => $value,
                    'sort_order' => $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($rows !== []) {
            DB::table('bridge_headers')->insert($rows);
        }
    }

    private function isSipHeaderVariable(string $name): bool
    {
        return str_starts_with(strtolower(trim($name)), 'sip_h_');
    }

    private function extractLeadingVariableBlock(string $destination): ?array
    {
        if (! str_starts_with($destination, '{')) {
            return null;
        }

        $depth = 0;
        $length = strlen($destination);

        for ($index = 0; $index < $length; $index++) {
            if ($destination[$index] === '{') {
                $depth++;
            } elseif ($destination[$index] === '}') {
                $depth--;

                if ($depth === 0) {
                    $raw = substr($destination, 0, $index + 1);

                    return [
                        'raw' => $raw,
                        'variables' => substr($raw, 1, -1),
                    ];
                }
            }
        }

        return null;
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
