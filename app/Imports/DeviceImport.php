<?php

namespace App\Imports;

use App\Models\DeviceKeyTemplate;
use App\Models\DeviceLines;
use App\Models\DeviceProfile;
use App\Models\Devices;
use App\Models\Extensions;
use App\Models\ProvisioningTemplate;
use App\Services\DeviceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class DevicesImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, SkipsOnError, SkipsOnFailure, WithValidation
{
    use Importable, SkipsErrors, SkipsFailures;

    protected string $domainUuid;
    protected string $domainName;

    public function __construct()
    {
        $this->domainUuid = session('domain_uuid');
        $this->domainName = session('domain_name');
    }

    public function rules(): array
    {
        return [
            '*.mac_address' => ['required', 'mac_address'],
            '*.mac_address_modified' => ['required', 'string', 'size:12'],
            '*.associated_extension' => [
                'nullable',
                Rule::exists('v_extensions', 'extension')->where('domain_uuid', $this->domainUuid),
            ],
            '*.template' => ['nullable', 'string'],
            '*.profile_or_key_template' => ['nullable', 'string'],
        ];
    }

    public function prepareForValidation($data, $index)
    {
        $mac = strtolower(trim((string) ($data['mac_address'] ?? $data['device_address'] ?? '')));
        $normalizedMac = strtolower(preg_replace('/[^0-9a-f]/i', '', $mac));

        $data['mac_address_modified'] = $normalizedMac;
        $data['mac_address'] = $normalizedMac !== ''
            ? strtolower(implode(':', str_split($normalizedMac, 2)))
            : null;

        $data['associated_extension'] = trim((string) ($data['associated_extension'] ?? $data['extension'] ?? ''));
        $data['associated_extension'] = $data['associated_extension'] === '' ? null : $data['associated_extension'];

        $data['template'] = trim((string) ($data['template'] ?? $data['device_template'] ?? ''));
        $data['template'] = $data['template'] === '' ? null : $data['template'];

        $assignment = $data['profile_or_key_template'] ?? $data['key_template'] ?? $data['profile'] ?? '';
        $data['profile_or_key_template'] = trim((string) $assignment);
        $data['profile_or_key_template'] = $data['profile_or_key_template'] === '' ? null : $data['profile_or_key_template'];

        return $data;
    }

    public function customValidationAttributes()
    {
        return [
            'mac_address_modified' => 'mac_address',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'associated_extension.exists' => 'The associated extension was not found in this domain.',
        ];
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            DB::transaction(function () use ($row) {
                $templateColumns = $this->resolveDeviceTemplateColumns($row['template'] ?? null);
                $assignmentColumns = $this->resolveAssignmentColumns($row['profile_or_key_template'] ?? null);

                $payload = [
                    'domain_uuid' => $this->domainUuid,
                    'device_address' => $row['mac_address'],
                    'device_address_modified' => $row['mac_address_modified'],
                    'device_enabled' => 'true',
                    'device_template' => $templateColumns['device_template'],
                    'device_template_uuid' => $templateColumns['device_template_uuid'],
                    'device_vendor' => $templateColumns['device_vendor'],
                    'device_profile_uuid' => $assignmentColumns['device_profile_uuid'],
                    'device_key_template_uuid' => $assignmentColumns['device_key_template_uuid'],
                ];

                $linePayload = ! empty($row['associated_extension'])
                    ? $this->linePayload($row['associated_extension'])
                    : null;

                if (! empty($row['associated_extension'])) {
                    $payload['device_label'] = $row['associated_extension'];
                }

                $device = Devices::where('domain_uuid', $this->domainUuid)
                    ->where('device_address', $row['mac_address_modified'])
                    ->first();

                if ($device) {
                    app(DeviceService::class)->update($device, $payload);
                    $this->syncLineOne($device->fresh(), $linePayload);
                    return;
                }

                if ($linePayload) {
                    $payload['device_lines'] = [$linePayload];
                }

                app(DeviceService::class)->create($payload);
            });
        }
    }

    protected function resolveDeviceTemplateColumns(?string $rawTemplate): array
    {
        $rawTemplate = trim((string) $rawTemplate);

        if ($rawTemplate === '') {
            return [
                'device_template' => null,
                'device_template_uuid' => null,
                'device_vendor' => null,
            ];
        }

        if (Str::isUuid($rawTemplate)) {
            $template = ProvisioningTemplate::query()
                ->where('template_uuid', $rawTemplate)
                ->first();

            return [
                'device_template' => null,
                'device_template_uuid' => $rawTemplate,
                'device_vendor' => $template?->vendor,
            ];
        }

        if (preg_match('/^(?<vendor>[^\/]+)\/(?<name>.+?)(?: \((?<suffix>[^)]+)\))?$/i', $rawTemplate, $matches)) {
            $vendor = strtolower(trim($matches['vendor']));
            $name = trim($matches['name']);
            $suffix = trim((string) ($matches['suffix'] ?? ''));

            $query = ProvisioningTemplate::query()
                ->whereRaw('LOWER(vendor) = ?', [$vendor])
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->where(function ($q) {
                    $q->where('type', 'default')
                        ->orWhere(function ($custom) {
                            $custom->where('type', 'custom')
                                ->where('domain_uuid', $this->domainUuid);
                        });
                });

            if ($suffix !== '') {
                foreach (array_map('trim', explode(',', $suffix)) as $part) {
                    if (preg_match('/^v(.+)$/i', $part, $version)) {
                        $query->where('version', $version[1]);
                    }
                    if (preg_match('/^r(\d+)$/i', $part, $revision)) {
                        $query->where('revision', (int) $revision[1]);
                    }
                }
            }

            $template = $query
                ->orderByRaw("case when type = 'custom' then 0 else 1 end")
                ->orderByDesc('updated_at')
                ->first();

            if ($template) {
                return [
                    'device_template' => null,
                    'device_template_uuid' => $template->template_uuid,
                    'device_vendor' => $template->vendor,
                ];
            }
        }

        $vendor = null;
        if (str_contains($rawTemplate, '/')) {
            [$vendor] = explode('/', $rawTemplate, 2);
            $vendor = strtolower($vendor);
            if ($vendor === 'poly') {
                $vendor = 'polycom';
            }
        }

        return [
            'device_template' => $rawTemplate,
            'device_template_uuid' => null,
            'device_vendor' => $vendor,
        ];
    }

    protected function resolveAssignmentColumns(?string $rawAssignment): array
    {
        $rawAssignment = trim((string) $rawAssignment);

        if ($rawAssignment === '') {
            return [
                'device_profile_uuid' => null,
                'device_key_template_uuid' => null,
            ];
        }

        if (Str::isUuid($rawAssignment)) {
            $keyTemplate = DeviceKeyTemplate::query()
                ->where('domain_uuid', $this->domainUuid)
                ->where('device_key_template_uuid', $rawAssignment)
                ->first();

            if ($keyTemplate) {
                return [
                    'device_profile_uuid' => null,
                    'device_key_template_uuid' => $keyTemplate->device_key_template_uuid,
                ];
            }

            $profile = DeviceProfile::query()
                ->where('domain_uuid', $this->domainUuid)
                ->where('device_profile_uuid', $rawAssignment)
                ->first();

            if (! $profile) {
                throw new \InvalidArgumentException("Profile or key template '{$rawAssignment}' was not found.");
            }

            return [
                'device_profile_uuid' => $profile->device_profile_uuid,
                'device_key_template_uuid' => null,
            ];
        }

        $keyTemplate = DeviceKeyTemplate::query()
            ->where('domain_uuid', $this->domainUuid)
            ->whereRaw('LOWER(name) = ?', [strtolower($rawAssignment)])
            ->first();

        if ($keyTemplate) {
            return [
                'device_profile_uuid' => null,
                'device_key_template_uuid' => $keyTemplate->device_key_template_uuid,
            ];
        }

        $profile = DeviceProfile::query()
            ->where('domain_uuid', $this->domainUuid)
            ->whereRaw('LOWER(device_profile_name) = ?', [strtolower($rawAssignment)])
            ->first();

        if (! $profile) {
            throw new \InvalidArgumentException("Profile or key template '{$rawAssignment}' was not found.");
        }

        return [
            'device_profile_uuid' => $profile->device_profile_uuid,
            'device_key_template_uuid' => null,
        ];
    }

    protected function linePayload(string $extensionNumber): array
    {
        $extension = Extensions::where('domain_uuid', $this->domainUuid)
            ->where('extension', $extensionNumber)
            ->firstOrFail();

        return [
            'line_type_id' => 'line',
            'line_number' => '1',
            'server_address' => $this->domainName,
            'server_address_primary' => get_domain_setting('server_address_primary', $this->domainUuid),
            'server_address_secondary' => get_domain_setting('server_address_secondary', $this->domainUuid),
            'outbound_proxy_primary' => get_domain_setting('outbound_proxy_primary', $this->domainUuid),
            'outbound_proxy_secondary' => get_domain_setting('outbound_proxy_secondary', $this->domainUuid),
            'display_name' => $extension->extension,
            'user_id' => $extension->extension,
            'auth_id' => $extension->extension,
            'label' => $extension->extension,
            'password' => $extension->password,
            'sip_port' => get_domain_setting('line_sip_port', $this->domainUuid),
            'sip_transport' => get_domain_setting('line_sip_transport', $this->domainUuid),
            'register_expires' => get_domain_setting('line_register_expires', $this->domainUuid),
            'domain_uuid' => $this->domainUuid,
        ];
    }

    protected function syncLineOne(Devices $device, ?array $linePayload): void
    {
        $existingLine = DeviceLines::where('domain_uuid', $this->domainUuid)
            ->where('device_uuid', $device->device_uuid)
            ->where('line_number', '1')
            ->first();

        if (! $linePayload) {
            $existingLine?->delete();
            return;
        }

        $linePayload['device_uuid'] = $device->device_uuid;
        $linePayload['device_line_uuid'] = $existingLine?->device_line_uuid;

        $line = $existingLine ?: new DeviceLines();
        $line->fill($linePayload);
        $line->save();
    }
}
