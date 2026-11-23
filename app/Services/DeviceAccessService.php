<?php

namespace App\Services;

use App\Models\{DeviceKeyTemplate, DeviceLines, DeviceProfile, Devices, Domain, Extensions, Phonebook, ProvisioningTemplate};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Authorization for cookie-authenticated device management, not phone provisioning or API v1. */
class DeviceAccessService
{
    public function currentDomain(): string
    {
        $domain = session('domain_uuid');
        abort_unless(is_string($domain) && Str::isUuid($domain), 403);

        return $domain;
    }

    public function domainQuery(): Builder
    {
        $current = $this->currentDomain();

        return Domain::query()->when(! userCheckPermission('device_all'),
            fn ($query) => $query->where('domain_uuid', $current));
    }

    public function query(): Builder
    {
        $current = $this->currentDomain();

        return Devices::query()->whereNotNull('domain_uuid')
            ->when(! userCheckPermission('device_all'), fn ($query) => $query->where('domain_uuid', $current));
    }

    public function listingQuery(mixed $showGlobal): Builder
    {
        $global = filter_var($showGlobal, FILTER_VALIDATE_BOOLEAN) && userCheckPermission('device_all');

        return $this->query()->when(! $global, fn ($query) => $query->where('domain_uuid', $this->currentDomain()));
    }

    public function selection(array $ids): Collection
    {
        $devices = $this->query()->whereIn('device_uuid', $ids)->orderBy('device_uuid')->get();
        abort_unless($devices->count() === count($ids), 404);

        return $devices;
    }

    public function canAccessDomain(?string $owner): bool
    {
        $current = $this->currentDomain();

        return filled($owner) && ($owner === $current || userCheckPermission('device_all'));
    }

    public function targetDomain(?string $requested, string $source): string
    {
        abort_unless($this->canAccessDomain($source), 404);
        if ($requested === null || $requested === $source) {
            return $source;
        }

        abort_unless(userCheckPermission('device_all') && userCheckPermission('device_domain'), 403);
        return $requested;
    }

    /** Validate every reference before any member of a batch is changed. */
    public function validateWrite(array $data, ?Devices $device = null): array
    {
        $source = $device ? (string) $device->domain_uuid : $this->currentDomain();
        $domain = $this->targetDomain($data['domain_uuid'] ?? null, $source);
        $moving = $device && $domain !== $source;
        $data['domain_uuid'] = $domain;

        foreach ([
            'device_profile_uuid' => [DeviceProfile::class, true],
            'device_key_template_uuid' => [DeviceKeyTemplate::class, false],
            'device_template_uuid' => [ProvisioningTemplate::class, true],
        ] as $field => [$model, $globalAllowed]) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            if (in_array($data[$field], [null, '', 'NULL'], true)) {
                $data[$field] = null;
                continue;
            }
            $row = $model::find($data[$field]);
            if ($row && ($row->domain_uuid === $domain || ($globalAllowed && $row->domain_uuid === null))) {
                continue;
            }
            // Full edit forms round-trip existing assignments. Transfer cleanup removes
            // old-account assignments, but never accepts a newly supplied foreign ID.
            if ($moving && $data[$field] === $device->{$field}) {
                continue;
            }
            $this->invalid($field);
        }

        foreach ($data['phonebook_uuids'] ?? [] as $index => $uuid) {
            if (Phonebook::where('domain_uuid', $domain)->whereKey($uuid)->exists()) {
                continue;
            }
            if ($moving && DB::table('device_phonebook')->where('device_uuid', $device->device_uuid)
                ->where('phonebook_uuid', $uuid)->exists()) {
                continue;
            }
            $this->invalid('phonebook_uuids.'.$index);
        }

        foreach ($data['device_lines'] ?? [] as $index => $line) {
            if (! empty($line['device_line_uuid']) && (! $device || ! DeviceLines::where('device_uuid', $device->device_uuid)
                ->whereKey($line['device_line_uuid'])->exists())) {
                $this->invalid('device_lines.'.$index.'.device_line_uuid');
            }
            if (($line['line_type_id'] ?? null) !== 'externalline') {
                foreach (['auth_id', 'user_id'] as $field) {
                    $extension = $line[$field] ?? null;
                    if ($extension !== null && $extension !== ''
                        && ! Extensions::where('domain_uuid', $domain)->where('extension', $extension)->exists()) {
                        $this->invalid('device_lines.'.$index.'.'.$field);
                    }
                }
            }
        }

        return $data;
    }

    private function invalid(string $field): never
    {
        throw ValidationException::withMessages([$field => [__('The selected value is invalid.')]]);
    }
}
