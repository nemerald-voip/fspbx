<?php

namespace App\Services;

use App\Models\DeviceProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeviceProfileService
{
    public function save(
        array $data,
        ?DeviceProfile $profile = null,
        array $childPermissions = []
    ): DeviceProfile {
        return DB::transaction(function () use ($data, $profile, $childPermissions) {
            $isNew = ! $profile;
            $originalDomainUuid = $profile?->domain_uuid;
            $profile ??= new DeviceProfile([
                'device_profile_uuid' => (string) Str::uuid(),
            ]);

            $profile->fill([
                'device_profile_name' => $data['device_profile_name'],
                'device_profile_enabled' => $data['device_profile_enabled'],
                'device_profile_description' => $data['device_profile_description'] ?? null,
            ]);

            if (array_key_exists('domain_uuid', $data)) {
                $profile->domain_uuid = $data['domain_uuid'];
            }

            $this->setAuditFields($profile, $isNew);
            $profile->save();

            if (! $isNew && $originalDomainUuid !== $profile->domain_uuid) {
                $auditFields = [
                    'domain_uuid' => $profile->domain_uuid,
                    'update_date' => now(),
                    'update_user' => session('user_uuid'),
                ];

                $profile->keys()->update($auditFields);
                $profile->settings()->update($auditFields);
            }

            $this->syncChildren(
                $profile,
                'keys',
                $data['keys'] ?? [],
                'device_profile_key_uuid',
                [
                    'profile_key_category',
                    'profile_key_id',
                    'profile_key_vendor',
                    'profile_key_type',
                    'profile_key_subtype',
                    'profile_key_line',
                    'profile_key_value',
                    'profile_key_extension',
                    'profile_key_protected',
                    'profile_key_label',
                    'profile_key_icon',
                ],
                $childPermissions['keys'] ?? []
            );

            $this->syncChildren(
                $profile,
                'settings',
                $data['settings'] ?? [],
                'device_profile_setting_uuid',
                [
                    'profile_setting_name',
                    'profile_setting_value',
                    'profile_setting_enabled',
                    'profile_setting_description',
                ],
                $childPermissions['settings'] ?? []
            );

            return $profile->fresh(['keys', 'settings']);
        });
    }

    public function copy(iterable $profiles): int
    {
        $count = 0;

        DB::transaction(function () use ($profiles, &$count) {
            foreach ($profiles as $profile) {
                $copy = $profile->replicate();
                $copy->device_profile_uuid = (string) Str::uuid();
                $copy->device_profile_description = $this->copyDescription($profile->device_profile_description);
                $this->setAuditFields($copy, true);
                $copy->save();

                foreach ($profile->keys as $key) {
                    $keyCopy = $key->replicate();
                    $keyCopy->device_profile_key_uuid = (string) Str::uuid();
                    $keyCopy->device_profile_uuid = $copy->device_profile_uuid;
                    $this->setAuditFields($keyCopy, true);
                    $keyCopy->save();
                }

                foreach ($profile->settings as $setting) {
                    $settingCopy = $setting->replicate();
                    $settingCopy->device_profile_setting_uuid = (string) Str::uuid();
                    $settingCopy->device_profile_uuid = $copy->device_profile_uuid;
                    $this->setAuditFields($settingCopy, true);
                    $settingCopy->save();
                }

                $count++;
            }
        });

        return $count;
    }

    public function toggle(iterable $profiles): int
    {
        $count = 0;

        DB::transaction(function () use ($profiles, &$count) {
            foreach ($profiles as $profile) {
                $profile->device_profile_enabled = $profile->device_profile_enabled === 'true'
                    ? 'false'
                    : 'true';
                $this->setAuditFields($profile);
                $profile->save();
                $count++;
            }
        });

        return $count;
    }

    public function delete(iterable $profiles): int
    {
        $count = 0;

        DB::transaction(function () use ($profiles, &$count) {
            foreach ($profiles as $profile) {
                $profile->keys()->delete();
                $profile->settings()->delete();
                $profile->delete();
                $count++;
            }
        });

        return $count;
    }

    private function copyDescription(?string $description): string
    {
        $description = trim((string) $description);

        return $description === '' ? __('Copy') : $description . ' (' . __('Copy') . ')';
    }

    private function setAuditFields(object $model, bool $isNew = false): void
    {
        if ($isNew) {
            $model->insert_date = now();
            $model->insert_user = session('user_uuid');
        }

        $model->update_date = now();
        $model->update_user = session('user_uuid');
    }

    private function syncChildren(
        DeviceProfile $profile,
        string $relation,
        array $submitted,
        string $uuidColumn,
        array $fillable,
        array $permissions
    ): void {
        if (! ($permissions['view'] ?? false)) {
            return;
        }

        /** @var Collection $existing */
        $existing = $profile->{$relation}()->get()->keyBy($uuidColumn);
        $submittedUuids = collect($submitted)
            ->pluck($uuidColumn)
            ->filter()
            ->all();

        foreach ($submitted as $row) {
            $uuid = $row[$uuidColumn] ?? null;
            $child = $uuid ? $existing->get($uuid) : null;

            if ($child) {
                if (! ($permissions['update'] ?? false)) {
                    continue;
                }

                $child->fill(collect($row)->only($fillable)->all());
                $child->domain_uuid = $profile->domain_uuid;
                $this->setAuditFields($child);
                $child->save();

                continue;
            }

            if (! ($permissions['create'] ?? false)) {
                continue;
            }

            $child = $profile->{$relation}()->make(collect($row)->only($fillable)->all());
            $child->{$uuidColumn} = (string) Str::uuid();
            $child->domain_uuid = $profile->domain_uuid;
            $this->setAuditFields($child, true);
            $child->save();
        }

        if ($permissions['destroy'] ?? false) {
            $existing
                ->reject(fn ($child) => in_array($child->{$uuidColumn}, $submittedUuids, true))
                ->each->delete();
        }
    }
}
