<?php

namespace App\Services;

use App\Models\DeviceKeyTemplate;
use App\Models\DeviceKeyTemplateKey;
use Illuminate\Support\Facades\DB;

class DeviceKeyTemplateService
{
    public function save(array $data, ?DeviceKeyTemplate $template = null): DeviceKeyTemplate
    {
        return DB::transaction(function () use ($data, $template) {
            $template ??= new DeviceKeyTemplate();

            $template->fill([
                'domain_uuid' => session('domain_uuid'),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'enabled' => $data['enabled'] ?? 'true',
            ]);
            $template->save();

            $this->syncKeys($template, $data['keys'] ?? []);

            return $template->fresh(['keys']);
        });
    }

    public function delete(iterable $templates): int
    {
        $count = 0;

        DB::transaction(function () use ($templates, &$count) {
            foreach ($templates as $template) {
                $template->keys()->delete();
                $template->delete();
                $count++;
            }
        });

        return $count;
    }

    public function createFromDeviceKeys(string $name, ?string $description, iterable $keys): DeviceKeyTemplate
    {
        return DB::transaction(function () use ($name, $description, $keys) {
            $template = DeviceKeyTemplate::create([
                'domain_uuid' => session('domain_uuid'),
                'name' => $name,
                'description' => $description,
                'enabled' => 'true',
            ]);

            $this->syncKeys($template, collect($keys)->map(fn ($key) => [
                'key_area' => $key->key_area ?? 'main',
                'key_index' => $key->key_index,
                'key_type' => $key->key_type,
                'key_value' => $key->key_value,
                'key_label' => $key->key_label,
            ])->all());

            return $template->fresh(['keys']);
        });
    }

    private function syncKeys(DeviceKeyTemplate $template, mixed $keys): void
    {
        $template->keys()->delete();

        if (empty($keys) || ! is_array($keys)) {
            return;
        }

        foreach ($keys as $key) {
            DeviceKeyTemplateKey::create([
                'device_key_template_uuid' => $template->device_key_template_uuid,
                'key_area' => $key['key_area'] ?? 'main',
                'key_index' => $key['key_index'],
                'key_type' => $key['key_type'] ?? null,
                'key_value' => $key['key_value'] ?? null,
                'key_label' => $key['key_label'] ?? null,
            ]);
        }
    }
}
