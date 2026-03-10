<?php

namespace App\Services;

use Aws\S3\S3Client;
use App\Models\DefaultSettings;
use App\Models\DomainSettings;
use Illuminate\Support\Facades\Storage;

class S3StorageConfigService
{
    /**
     * Required and optional setting keys for s3_storage.
     */
    protected array $required = [
        'access_key',
        'secret_key',
        'bucket_name',
    ];

    protected array $optional = [
        'region',
        'endpoint',
        'use_path_style_endpoint',
        'signature_version',
    ];

    /**
     * Return the effective storage settings for a domain.
     * Domain settings override defaults only when all required settings exist.
     */
    public function getSettingsForDomain(?string $domainUuid): ?array
    {
        if ($domainUuid) {
            $domainSettings = $this->getDomainSettings($domainUuid);

            if ($this->hasRequiredSettings($domainSettings)) {
                return $this->normalizeSettings($domainSettings, 'custom');
            }
        }

        $defaultSettings = $this->getDefaultSettings();

        if ($this->hasRequiredSettings($defaultSettings)) {
            return $this->normalizeSettings($defaultSettings, 'default');
        }

        return null;
    }

    /**
     * Return effective settings for many domains efficiently.
     * Returns:
     * [
     *   'default' => [...],
     *   'domains' => [
     *      domain_uuid => [...custom settings...]
     *   ]
     * ]
     */
    public function getSettingsMapForDomains(array $domainUuids): array
    {
        $defaultSettings = $this->getDefaultSettings();
        $default = $this->hasRequiredSettings($defaultSettings)
            ? $this->normalizeSettings($defaultSettings, 'default')
            : null;

        if (empty($domainUuids)) {
            return [
                'default' => $default,
                'domains' => [],
            ];
        }

        $rows = DomainSettings::query()
            ->whereIn('domain_uuid', $domainUuids)
            ->where('domain_setting_category', 's3_storage')
            ->whereIn('domain_setting_subcategory', array_merge($this->required, $this->optional))
            ->where('domain_setting_enabled', true)
            ->get([
                'domain_uuid',
                'domain_setting_subcategory',
                'domain_setting_value',
            ]);

        $domains = [];

        foreach ($rows->groupBy('domain_uuid') as $domainUuid => $groupedRows) {
            $flat = [];

            foreach ($groupedRows as $row) {
                if ($row->domain_setting_value !== null && $row->domain_setting_value !== '') {
                    $flat[$row->domain_setting_subcategory] = $row->domain_setting_value;
                }
            }

            if ($this->hasRequiredSettings($flat)) {
                $domains[$domainUuid] = $this->normalizeSettings($flat, 'custom');
            }
        }

        return [
            'default' => $default,
            'domains' => $domains,
        ];
    }

    /**
     * Build AWS SDK S3 client from effective settings for a domain.
     */
    public function buildClientForDomain(?string $domainUuid): ?S3Client
    {
        $settings = $this->getSettingsForDomain($domainUuid);

        if (!$settings) {
            return null;
        }

        return $this->buildClientFromSettings($settings);
    }

    /**
     * Build Laravel storage disk from effective settings for a domain.
     */
    public function buildDiskForDomain(?string $domainUuid)
    {
        $settings = $this->getSettingsForDomain($domainUuid);

        if (!$settings) {
            return null;
        }

        return $this->buildDiskFromSettings($settings);
    }

    /**
     * Build AWS SDK S3 client from normalized settings.
     */
    public function buildClientFromSettings(array $settings): S3Client
    {
        $config = [
            'region'      => $settings['region'] ?? 'us-east-1',
            'version'     => 'latest',
            'credentials' => [
                'key'    => $settings['key'],
                'secret' => $settings['secret'],
            ],
        ];

        if (!empty($settings['endpoint'])) {
            $config['endpoint'] = $settings['endpoint'];
        }

        if (array_key_exists('use_path_style_endpoint', $settings)) {
            $config['use_path_style_endpoint'] = (bool) $settings['use_path_style_endpoint'];
        }

        if (!empty($settings['signature_version'])) {
            $config['signature_version'] = $settings['signature_version'];
        }

        return new S3Client($config);
    }

    /**
     * Build Laravel Storage disk from normalized settings.
     */
    public function buildDiskFromSettings(array $settings)
    {
        $config = [
            'driver' => 's3',
            'key'    => $settings['key'],
            'secret' => $settings['secret'],
            'region' => $settings['region'] ?? 'us-east-1',
            'bucket' => $settings['bucket'],
        ];

        if (!empty($settings['endpoint'])) {
            $config['endpoint'] = $settings['endpoint'];
        }

        if (array_key_exists('use_path_style_endpoint', $settings)) {
            $config['use_path_style_endpoint'] = (bool) $settings['use_path_style_endpoint'];
        }

        if (!empty($settings['signature_version'])) {
            $config['signature_version'] = $settings['signature_version'];
        }

        return Storage::build($config);
    }

    /**
     * Return a stable hash for reusing clients.
     */
    public function getSettingsHash(array $settings): string
    {
        return md5(json_encode([
            'key'        => $settings['key'],
            'bucket'     => $settings['bucket'],
            'region'     => $settings['region'] ?? 'us-east-1',
            'endpoint'   => $settings['endpoint'] ?? '',
            'path_style' => $settings['use_path_style_endpoint'] ?? null,
            'sig'        => $settings['signature_version'] ?? '',
        ]));
    }

    protected function getDomainSettings(string $domainUuid): array
    {
        return DomainSettings::query()
            ->where('domain_uuid', $domainUuid)
            ->where('domain_setting_category', 's3_storage')
            ->whereIn('domain_setting_subcategory', array_merge($this->required, $this->optional))
            ->where('domain_setting_enabled', true)
            ->pluck('domain_setting_value', 'domain_setting_subcategory')
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->toArray();
    }

    protected function getDefaultSettings(): array
    {
        return DefaultSettings::query()
            ->where('default_setting_category', 's3_storage')
            ->whereIn('default_setting_subcategory', array_merge($this->required, $this->optional))
            ->where('default_setting_enabled', true)
            ->pluck('default_setting_value', 'default_setting_subcategory')
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->toArray();
    }

    protected function hasRequiredSettings(array $settings): bool
    {
        foreach ($this->required as $key) {
            if (!array_key_exists($key, $settings) || $settings[$key] === null || $settings[$key] === '') {
                return false;
            }
        }

        return true;
    }

    protected function normalizeSettings(array $settings, string $type): array
    {
        $normalized = [
            'key'    => $settings['access_key'],
            'secret' => $settings['secret_key'],
            'bucket' => $settings['bucket_name'],
            'region' => !empty($settings['region']) ? $settings['region'] : 'us-east-1',
            'type'   => $type,
        ];

        if (!empty($settings['endpoint'])) {
            $normalized['endpoint'] = $settings['endpoint'];
        }

        if (array_key_exists('use_path_style_endpoint', $settings) && $settings['use_path_style_endpoint'] !== '') {
            $normalized['use_path_style_endpoint'] = filter_var(
                $settings['use_path_style_endpoint'],
                FILTER_VALIDATE_BOOLEAN
            );
        }

        if (!empty($settings['signature_version'])) {
            $normalized['signature_version'] = $settings['signature_version'];
        }

        return $normalized;
    }
}