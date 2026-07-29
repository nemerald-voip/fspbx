<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DeviceKey;
use App\Models\Devices;
use App\Models\Extensions;
use App\Models\DeviceLines;
use App\Services\DeviceCloudProvisioningService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class DeviceService
{
    public function create(array $validated): Devices
    {
        return DB::transaction(function () use ($validated) {
            $inputs = $validated;
            $inputs['device_enabled'] = $inputs['device_enabled'] ?? 'true';
            $inputs['device_enabled'] = $this->normalizeEnabledValue($inputs['device_enabled']);
            $inputs['device_address'] = $inputs['device_address_modified'];
            $this->normalizeKeyTemplateValue($inputs);

            $domainUuid = (string) ($inputs['domain_uuid'] ?? '');
            $domainName = $this->resolveDomainName($domainUuid);

            $device = new Devices();
            $device->fill($inputs);
            $device->save();

            $deviceLines = $inputs['device_lines'] ?? null;
            if (is_array($deviceLines) && ! empty($deviceLines)) {
                foreach ($deviceLines as $line) {
                    $this->createDeviceLine($device, $line, $domainUuid, $domainName);
                }
            }

            if (array_key_exists('phonebook_mode', $inputs) || array_key_exists('phonebook_uuids', $inputs)) {
                $this->syncDevicePhonebooks($device, $inputs);
            }

            return $device->fresh();
        });
    }

    public function update(Devices $device, array $validated): Devices
    {
        return DB::transaction(function () use ($device, $validated) {
            $inputs = $validated;

            if (array_key_exists('device_enabled', $inputs)) {
                $inputs['device_enabled'] = $this->normalizeEnabledValue($inputs['device_enabled']);
            }

            if (array_key_exists('device_address_modified', $inputs)) {
                $inputs['device_address'] = $inputs['device_address_modified'];
            }

            $this->normalizeKeyTemplateValue($inputs);

            $domainUuid = (string) ($inputs['domain_uuid'] ?? $device->domain_uuid);
            $previousDomainUuid = (string) $device->domain_uuid;

            $device->update($inputs);

            if (array_key_exists('device_lines', $inputs)) {
                $this->syncDeviceLines($device, $inputs['device_lines'], $domainUuid);
            }

            // The edit form round-trips whatever line values it loaded, so a domain change
            // has to re-resolve them afterwards - including when no lines were submitted.
            if ($domainUuid !== '' && $domainUuid !== $previousDomainUuid) {
                $this->cascadeDomainToLines($device, $domainUuid);
            }

            if (array_key_exists('device_settings', $inputs)) {
                $this->syncDeviceSettings($device, $inputs['device_settings']);
            }

            if (array_key_exists('device_keys', $inputs)) {
                $this->syncDeviceKeys($device, $inputs['device_keys']);
            }

            if (array_key_exists('phonebook_mode', $inputs) || array_key_exists('phonebook_uuids', $inputs)) {
                $this->syncDevicePhonebooks($device, $inputs);
            }

            return $device->fresh();
        });
    }

    /**
     * Sync a device's phonebook assignments.
     *
     * mode 'default' (or empty custom list) clears assignments so the device
     * inherits the domain default; mode 'custom' stores the ordered UUID list,
     * with the array order becoming the phone slot / priority.
     */
    private function syncDevicePhonebooks(Devices $device, array $inputs): void
    {
        $mode = $inputs['phonebook_mode'] ?? 'custom';

        DB::table('device_phonebook')->where('device_uuid', $device->device_uuid)->delete();

        if ($mode !== 'custom') {
            return;
        }

        $uuids = array_values(array_filter((array) ($inputs['phonebook_uuids'] ?? [])));
        if (empty($uuids)) {
            return;
        }

        // Only keep phonebooks that belong to the device's domain.
        $valid = \App\Models\Phonebook::query()
            ->where('domain_uuid', $device->domain_uuid)
            ->whereIn('phonebook_uuid', $uuids)
            ->pluck('phonebook_uuid')
            ->all();

        $now = now();
        $slot = 1;
        $rows = [];
        foreach ($uuids as $uuid) {
            if (! in_array($uuid, $valid, true)) {
                continue;
            }
            $rows[] = [
                'device_uuid'    => $device->device_uuid,
                'phonebook_uuid' => $uuid,
                'slot'           => $slot++,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        if (! empty($rows)) {
            DB::table('device_phonebook')->insert($rows);
        }
    }

    public function delete(Devices $device): void
    {
        DB::transaction(function () use ($device) {
            $device = $this->loadDeleteSnapshot($device);

            if ($device->cloudProvisioning) {
                $params = [
                    'device_uuid' => $device->device_uuid,
                    'domain_uuid' => $device->domain_uuid,
                    'device_vendor' => $device->device_vendor,
                    'device_address' => $device->device_address,
                ];

                $deregisterJob = app(DeviceCloudProvisioningService::class)->deregister($params);
                $resetJob = app(DeviceCloudProvisioningService::class)->reset($params);

                if ($deregisterJob) {
                    dispatch($deregisterJob->chain([$resetJob]));
                } else {
                    dispatch($resetJob);
                }
            }

            if ($device->lines()) {
                $device->lines()->delete();
            }

            if ($device->settings()) {
                $device->settings()->delete();
            }

            if ($device->keys()) {
                $device->keys()->delete();
            }

            if ($device->legacy_keys()) {
                $device->legacy_keys()->delete();
            }

            $device->delete();
        });
    }

    private function resolveDomainName(string $domainUuid): ?string
    {
        if ($domainUuid === '') {
            return session('domain_name');
        }

        return Domain::query()
            ->where('domain_uuid', $domainUuid)
            ->value('domain_name') ?? session('domain_name');
    }

    private function normalizeKeyTemplateValue(array &$inputs): void
    {
        if (array_key_exists('device_key_template_uuid', $inputs) && $inputs['device_key_template_uuid'] === 'NULL') {
            $inputs['device_key_template_uuid'] = null;
        }
    }

    private function loadDeleteSnapshot(Devices $device): Devices
    {
        $needsReload = empty($device->domain_uuid)
            || $device->device_vendor === null
            || $device->device_address === null;

        if ($needsReload) {
            $reloaded = Devices::query()
                ->where('device_uuid', $device->device_uuid)
                ->first([
                    'device_uuid',
                    'domain_uuid',
                    'device_vendor',
                    'device_address',
                ]);

            if ($reloaded) {
                $device = $reloaded;
            }
        }

        $device->loadMissing('cloudProvisioning');

        return $device;
    }

    private function normalizeEnabledValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /**
     * The line columns that describe how a line reaches the PBX. They are derived from
     * the owning domain, so they have to be re-resolved whenever a device changes domain.
     */
    public const DOMAIN_DERIVED_LINE_SETTINGS = [
        'server_address_primary' => 'server_address_primary',
        'server_address_secondary' => 'server_address_secondary',
        'outbound_proxy_primary' => 'outbound_proxy_primary',
        'outbound_proxy_secondary' => 'outbound_proxy_secondary',
        'sip_port' => 'line_sip_port',
        'sip_transport' => 'line_sip_transport',
        'register_expires' => 'line_register_expires',
    ];

    /**
     * Re-point a device's lines at the domain the device now belongs to.
     *
     * Every line follows the device's domain_uuid. Connectivity settings and the SIP
     * domain are additionally re-resolved from the new domain's settings, except on
     * external lines: those register to a third party, so their server address, proxy,
     * port and transport belong to that provider and must survive the move.
     *
     * @return int number of lines re-pointed
     */
    public function cascadeDomainToLines(Devices $device, ?string $domainUuid = null): int
    {
        $domainUuid = (string) ($domainUuid ?: $device->domain_uuid);

        if ($domainUuid === '') {
            return 0;
        }

        $domainName = $this->resolveDomainName($domainUuid);

        $derived = ['domain_uuid' => $domainUuid, 'server_address' => $domainName];

        foreach (self::DOMAIN_DERIVED_LINE_SETTINGS as $column => $setting) {
            $derived[$column] = get_domain_setting($setting, $domainUuid);
        }

        $isExternal = function ($query) {
            $query->where('external_line', true);
        };

        // External lines: only the owning domain moves, their connection details stay put.
        $externalCount = DeviceLines::query()
            ->where('device_uuid', $device->device_uuid)
            ->where($isExternal)
            ->update([
                'domain_uuid' => $domainUuid,
                'update_date' => date('Y-m-d H:i:s'),
            ]);

        $internalCount = DeviceLines::query()
            ->where('device_uuid', $device->device_uuid)
            ->where(function ($query) {
                $query->whereNull('external_line')->orWhere('external_line', false);
            })
            ->update($derived + ['update_date' => date('Y-m-d H:i:s')]);

        return $externalCount + $internalCount;
    }

    /**
     * Apply connectivity settings (SIP port, transport, ...) to the lines of many devices at once.
     *
     * $scope controls which lines of each device are touched:
     *   - mode: 'all' | 'first' | 'list'
     *   - line_numbers: string[] used when mode is 'list'
     *   - include_external: whether lines registered to a third party provider are included
     *
     * @param  string[]  $deviceUuids
     * @param  array<string, mixed>  $attributes  keyed by v_device_lines column
     * @return array{lines_updated:int, devices_affected:int, devices_skipped:int, device_uuids:string[]}
     */
    public function bulkUpdateLineSettings(array $deviceUuids, array $attributes, array $scope = []): array
    {
        $selectedCount = count(array_unique($deviceUuids));

        $empty = [
            'lines_updated' => 0,
            'devices_affected' => 0,
            'devices_skipped' => $selectedCount,
            'device_uuids' => [],
        ];

        if (empty($deviceUuids) || empty($attributes)) {
            return $empty;
        }

        $mode = $scope['mode'] ?? 'all';
        $lineNumbers = $scope['line_numbers'] ?? [];
        $includeExternal = (bool) ($scope['include_external'] ?? false);

        if ($mode === 'list' && empty($lineNumbers)) {
            return $empty;
        }

        // Rebuilt per use: the same constraints back both the lookup and the update.
        $matching = function () use ($deviceUuids, $mode, $lineNumbers, $includeExternal) {
            return DeviceLines::query()
                ->whereIn('device_uuid', $deviceUuids)
                ->unless($includeExternal, function ($query) {
                    // External lines register to a third party server - their port and
                    // transport belong to that provider, not to this PBX.
                    $query->where(function ($inner) {
                        $inner->whereNull('external_line')
                            ->orWhere('external_line', false);
                    });
                })
                ->when($mode === 'first', fn ($query) => $query->where('line_number', '1'))
                ->when($mode === 'list', fn ($query) => $query->whereIn('line_number', $lineNumbers));
        };

        $affectedDevices = $matching()
            ->distinct()
            ->pluck('device_uuid')
            ->map(fn ($uuid) => (string) $uuid)
            ->all();

        if (empty($affectedDevices)) {
            return $empty;
        }

        $payload = $attributes;
        $payload['update_date'] = date('Y-m-d H:i:s');

        if ($updateUser = Session::get('user_uuid')) {
            $payload['update_user'] = $updateUser;
        }

        $linesUpdated = $matching()->update($payload);

        return [
            'lines_updated' => $linesUpdated,
            'devices_affected' => count($affectedDevices),
            'devices_skipped' => max(0, $selectedCount - count($affectedDevices)),
            'device_uuids' => $affectedDevices,
        ];
    }

    private function createDeviceLine(Devices $device, array $line, string $domainUuid, ?string $domainName): void
    {
        $extension = Extensions::where('extension', $line['auth_id'])
            ->where('domain_uuid', $domainUuid)
            ->first();

        if (! $extension) {
            return;
        }

        $deviceLine = new DeviceLines();
        $deviceLine->fill([
            'device_uuid' => $device->device_uuid,
            'line_number' => $line['line_number'],
            'line_type_id' => $line['line_type_id'] ?? 'line',
            'server_address' => $domainName,
            'server_address_primary' => $line['server_address_primary'] ?? get_domain_setting('server_address_primary', $domainUuid),
            'server_address_secondary' => $line['server_address_secondary'] ?? get_domain_setting('server_address_secondary', $domainUuid),
            'outbound_proxy_primary' => $line['outbound_proxy_primary'] ?? get_domain_setting('outbound_proxy_primary', $domainUuid),
            'outbound_proxy_secondary' => $line['outbound_proxy_secondary'] ?? get_domain_setting('outbound_proxy_secondary', $domainUuid),
            'display_name' => $line['display_name'] ?? null,
            'user_id' => $extension->extension,
            'auth_id' => $extension->extension,
            'label' => $line['display_name'] ?? null,
            'password' => $extension->password,
            'sip_port' => $line['sip_port'] ?? get_domain_setting('line_sip_port', $domainUuid),
            'sip_transport' => $line['sip_transport'] ?? get_domain_setting('line_sip_transport', $domainUuid),
            'register_expires' => $line['register_expires'] ?? get_domain_setting('register_expires', $domainUuid),
            'shared_line' => ($line['line_type_id'] ?? null) === 'sharedline' ? '1' : ($line['shared_line'] ?? null),
            'device_line_uuid' => (string) Str::uuid(),
            'domain_uuid' => $device->domain_uuid,
            'enabled' => 'true',
        ]);

        $deviceLine->save();
    }

    private function syncDeviceLines(Devices $device, mixed $deviceLines, string $domainUuid): void
    {
        if (empty($deviceLines) || ! is_array($deviceLines)) {
            $device->lines()->delete();
            return;
        }

        $device->lines()->delete();

        foreach ($deviceLines as $line) {
            $isExternalLine = ($line['line_type_id'] ?? null) === 'externalline';

            $extension = null;
            if (! $isExternalLine && ! empty($line['auth_id'])) {
                $extension = Extensions::where('extension', $line['auth_id'])
                    ->where('domain_uuid', $domainUuid)
                    ->first();
            }

            $deviceLineData = [
                'device_uuid' => $device->device_uuid,
                'line_number' => $line['line_number'],
                'server_address' => $line['server_address'] ?? null,
                'server_address_primary' => $line['server_address_primary'] ?? null,
                'server_address_secondary' => $line['server_address_secondary'] ?? null,
                'outbound_proxy_primary' => $line['outbound_proxy_primary'] ?? null,
                'outbound_proxy_secondary' => $line['outbound_proxy_secondary'] ?? null,
                'display_name' => $line['display_name'] ?? null,
                'user_id' => $isExternalLine
                    ? ($line['user_id'] ?? null)
                    : ($extension->extension ?? ($line['user_id'] ?? null)),
                'auth_id' => $isExternalLine
                    ? ($line['auth_id'] ?? null)
                    : ($extension->extension ?? ($line['auth_id'] ?? null)),
                'password' => $isExternalLine
                    ? ($line['password'] ?? null)
                    : ($extension->password ?? null),
                'label' => $line['display_name'] ?? null,
                'sip_port' => $line['sip_port'] ?? null,
                'sip_transport' => $line['sip_transport'] ?? null,
                'register_expires' => $line['register_expires'] ?? null,
                'shared_line' => ($line['line_type_id'] ?? null) === 'sharedline' ? '1' : '',
                'external_line' => $isExternalLine,
                'device_line_uuid' => $line['device_line_uuid'] ?? null,
                'domain_uuid' => $device->domain_uuid,
                'enabled' => 'true',
            ];

            $deviceLine = new DeviceLines();
            $deviceLine->fill($deviceLineData);
            $deviceLine->save();
        }
    }

    private function syncDeviceSettings(Devices $device, mixed $deviceSettings): void
    {
        if (empty($deviceSettings) || ! is_array($deviceSettings)) {
            $device->settings()->delete();
            return;
        }

        $device->settings()->delete();

        foreach ($deviceSettings as $item) {
            $payload = [
                'device_uuid' => $device->device_uuid,
                'domain_uuid' => $device->domain_uuid,
                'device_setting_category' => $item['device_setting_category'] ?? null,
                'device_setting_subcategory' => $item['device_setting_subcategory'] ?? null,
                'device_setting_name' => $item['device_setting_name'] ?? null,
                'device_setting_value' => $item['device_setting_value'] ?? null,
                'device_setting_enabled' => $item['device_setting_enabled'] ?? 'false',
                'device_setting_description' => $item['device_setting_description'] ?? null,
            ];

            $device->settings()->create($payload);
        }
    }

    private function syncDeviceKeys(Devices $device, mixed $deviceKeys): void
    {
        if (empty($deviceKeys) || ! is_array($deviceKeys)) {
            $device->keys()->delete();
            return;
        }

        $device->keys()->delete();

        foreach ($deviceKeys as $key) {
            $deviceKey = new DeviceKey();
            $deviceKey->device_uuid = $device->device_uuid;
            $deviceKey->key_area = $key['key_area'] ?? 'main';
            $deviceKey->key_index = $key['key_index'];
            $deviceKey->key_type = $key['key_type'] ?? null;
            $deviceKey->key_value = $key['key_value'] ?? null;
            $deviceKey->key_label = $key['key_label'] ?? null;
            $deviceKey->save();
        }
    }
}
