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

class DeviceService
{
    public function create(array $validated): Devices
    {
        return DB::transaction(function () use ($validated) {
            $inputs = $validated;
            $inputs['device_enabled'] = $inputs['device_enabled'] ?? 'true';
            $inputs['device_enabled'] = $this->normalizeEnabledValue($inputs['device_enabled']);
            $inputs['device_address'] = $inputs['device_address_modified'];

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

            $domainUuid = (string) ($inputs['domain_uuid'] ?? $device->domain_uuid);

            $device->update($inputs);

            if (array_key_exists('device_lines', $inputs)) {
                $this->syncDeviceLines($device, $inputs['device_lines'], $domainUuid);
            }

            if (array_key_exists('device_settings', $inputs)) {
                $this->syncDeviceSettings($device, $inputs['device_settings']);
            }

            if (array_key_exists('device_keys', $inputs)) {
                $this->syncDeviceKeys($device, $inputs['device_keys']);
            }

            return $device->fresh();
        });
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
            'shared_line' => $line['shared_line'] ?? null,
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

        $rows = [];
        foreach ($deviceKeys as $key) {
            $rows[] = [
                'device_uuid' => $device->device_uuid,
                'key_area' => $key['key_area'] ?? 'main',
                'key_index' => $key['key_index'],
                'key_type' => $key['key_type'] ?? null,
                'key_value' => $key['key_value'] ?? null,
                'key_label' => $key['key_label'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DeviceKey::insert($rows);
    }
}
