<?php

namespace App\Http\Controllers;

use App\Models\Devices;
use App\Models\Extensions;
use Illuminate\Http\Request;
use App\Models\DeviceSettings;
use App\Models\DomainSettings;
use Illuminate\Support\Carbon;
use App\Models\DefaultSettings;
use App\Models\ProvisioningTemplate;
use Illuminate\Support\Facades\Blade;

class ProvisioningController extends Controller
{
    /**
     * Catch-all entrypoint:
     * Route: /prov/{path}  (path can contain subfolders like "83/serial.xml")
     * DigestProvisionAuth middleware MUST run before this.
     */
    public function serve(Request $request, string $path = '')
    {
        // Extract {id}.{ext} from $path or from the current request path
        [$id, $ext] = $this->extractIdAndExt($request, $path);

        // Device is attached by DigestProvisionAuth; otherwise 404
        /** @var Devices|null $device */
        $device = $request->attributes->get('prov.device');
        if (!$device) {
            return response('', 404);
        }

        // Choose content type based on ext
        $contentType = $this->contentTypeFromExt($ext);

        // Load the device’s chosen template (DB-backed or legacy default)
        $tpl = $this->resolveTemplateForDevice($device);
        if (!$tpl) {
            return response('', 404);
        }

        // HEAD quick-path
        if ($request->isMethod('HEAD')) {
            return response('', 200, [
                'Content-Type'    => $contentType,
                'Cache-Control'   => 'private, max-age=0, must-revalidate',
                'X-Prov-Template' => (string)$tpl->template_uuid,
                'X-Prov-Type'     => (string)$tpl->type,
                'X-Prov-Version'  => (string)($tpl->version ?? ''),
            ]);
        }

        // Build Blade variables and render
        $vars = $this->buildTemplateVars($device);

        // logger($vars);
        // Compute flavor + MIME
        $flv = $this->computeFlavor($request, $device, $id, $ext);

        // Add provisioning context
        $vars += [
            'flavor'        => $flv['flavor'],           // 'serial.xml' | 'mac.cfg' | 'poly-index.cfg' | 'yealink-model.cfg'
            'requested_ext' => strtolower($ext),
        ];

        $body = Blade::render($tpl->content, $vars);

        // If the rendered body is valid XML, pretty-print it (keeps XML decl if present)
        if ($pretty = $this->maybePrettyPrintXml($body)) {
            $body = $pretty;
        } else {
            // Not XML → normalize provisioning text
            $body = $this->normalizeProvisionText($body);
        }

        // ETag / 304 support
        $etag = '"' . hash('sha256', $body) . '"';
        $ifNoneMatch = $request->headers->get('If-None-Match');
        if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
            return response('', 304, [
                'ETag'            => $etag,
                'Content-Type'    => $contentType,
                'Cache-Control'   => 'private, max-age=0, must-revalidate',
                'X-Prov-Template' => (string)$tpl->template_uuid,
                'X-Prov-Type'     => (string)$tpl->type,
                'X-Prov-Version'  => (string)($tpl->version ?? ''),
            ]);
        }

        // Log device provisioning event
        $device->fill([
            'device_provisioned_date'   => Carbon::now('UTC'),
            'device_provisioned_method' => strtolower($request->getScheme()),
            'device_provisioned_ip'     => $request->ip(),
            'device_provisioned_agent'  => (string) $request->userAgent(),
        ])->save();

        return response($body, 200, [
            'ETag'            => $etag,
            'Content-Type'    => $contentType,
            'Cache-Control'   => 'private, max-age=0, must-revalidate',
            'X-Prov-Template' => (string)$tpl->template_uuid,
            'X-Prov-Type'     => (string)$tpl->type,
            'X-Prov-Version'  => (string)($tpl->version ?? ''),
        ]);
    }

    /* ------------------------- helpers ------------------------- */

    private function contentTypeFromExt(string $ext): string
    {
        return strtolower($ext) === 'xml' ? 'application/xml' : 'text/plain';
    }

    private function resolveTemplateForDevice(Devices $device): ?ProvisioningTemplate
    {
        if (!empty($device->device_template_uuid)) {
            $row = ProvisioningTemplate::where('template_uuid', $device->device_template_uuid)->first();
            if ($row) return $row;
        }

        if (!empty($device->device_template) && str_contains($device->device_template, '/')) {
            [$vendor, $name] = explode('/', strtolower($device->device_template), 2);
            return ProvisioningTemplate::where('vendor', $vendor)
                ->where('name', $name)
                ->where('type', 'default')
                ->orderByDesc('created_at')
                ->first();
        }

        return null;
    }

    private function buildTemplateVars(Devices $device): array
    {
        $device->load([
            'lines' => function ($q) {
                // Include PK, FK, and only the columns you use
                $q->select([
                    'device_line_uuid',   // PK on device_lines
                    'device_uuid',        // FK to devices (required for the relation)
                    'line_number',
                    'user_id',
                    'auth_id',
                    'server_address',
                    'server_address_primary',
                    'server_address_secondary',
                    'outbound_proxy_primary',
                    'outbound_proxy_secondary',
                    'display_name',
                    'password',
                    'sip_port',
                    'sip_transport',
                    'register_expires',
                    'shared_line',
                    'domain_uuid',
                ]);
            },
            'profile' => function ($q) {
                $q->select([
                    'device_profile_uuid',
                    'device_profile_name',
                ])->with([
                    'keys' => function ($k) {
                        // Need FK back to profile + correct orderBy column
                        $k->select([
                            'device_profile_key_uuid',
                            'device_profile_uuid',
                            'profile_key_id',
                            'profile_key_category',
                            'profile_key_type',
                            'profile_key_line',
                            'profile_key_value',
                            'profile_key_extension',
                            'profile_key_label',
                        ])->orderBy('profile_key_line');
                    },
                ]);
            },
            'legacy_keys' => function ($k) {
                $k->select([
                    'device_key_uuid',
                    'device_uuid',
                    'device_key_id',
                    'device_key_category',
                    'device_key_type',
                    'device_key_line',
                    'device_key_value',
                    'device_key_extension',
                    'device_key_label',
                ])->orderBy('device_key_line');
            },
            'keys' => function ($k) {
                $k->select([
                    'device_key_uuid',
                    'device_uuid',
                    'key_area',
                    'key_index',
                    'key_type',
                    'key_value',
                    'key_label',
                ])->orderBy('key_area')->orderBy('key_index');
            },
            'domain' => function ($q) {
                $q->select([
                    'domain_uuid',
                    'domain_name',
                ]);
            },
        ]);

        $lines = [];
        foreach ($device->lines as $line) {
            $lines[$line->line_number] = [
                'user_id'           => $line->user_id ?? null,
                'auth_id'           => $line->auth_id ?? null,
                'password'          => $line->password ?? null,
                'display_name'      => $line->display_name ?? null,
                'server_address'    => $line->server_address ?? null,
                'server_address_primary'   => $line->server_address_primary ?? null,
                'server_address_secondary' => $line->server_address_secondary ?? null,
                'outbound_proxy_primary'   => $line->outbound_proxy_primary ?? null,
                'outbound_proxy_secondary' => $line->outbound_proxy_secondary ?? null,
                'sip_port'          => $line->sip_port ?? null,
                'sip_transport'     => $this->normalizeTransportForVendor($device->device_vendor, $line->sip_transport),
                'register_expires'  => $line->register_expires ?? null,
                'shared_line'  => $line->shared_line ?? null,
                'line_number'       => $line->line_number,
            ];
        }

        $settings = $this->getProvisionSettings(
            (string) $device->domain_uuid,
            (string) $device->device_uuid
        );

        $keyAreas = $this->getEffectiveDeviceKeysByArea($device, $settings);
        // logger($keyAreas);

        return [
            'device_uuid'   => (string) $device->device_uuid,
            'domain_uuid'   => (string) $device->domain_uuid,
            'vendor'        => $device->device_vendor ?? null,
            'domain_name'   => $device->domain?->domain_name ?? null,
            'template'      => $device->device_template ?? null,
            'template_uuid' => $device->device_template_uuid ?? null,
            'serial'        => (string) $device->serial_number ?? null,
            'mac'           => $device->device_address ?? null,

            // all keys flattened for legacy templates that expect a single list:
            'keys' => $keyAreas['main'] ?? [],
            // keys separated by area for newer templates that want to organize by area:
            'keys_by_area' => $keyAreas,
            'main_keys' => $keyAreas['main'] ?? [],
            'multi_purpose_keys' => $keyAreas['multi_purpose'] ?? [],

            'lines'       => $lines,
            'line_count'  => count($lines),
            'settings'    => $settings,
        ];
    }

    /**
     * Extract {id}.{ext} from provided $path or from request path.
     * Accepts tails like: "83/da44-1017-9088-0092.xml" or "0004f23a5bc7.cfg"
     */
    private function extractIdAndExt(Request $request, string $path = ''): array
    {
        $tail = $path !== '' ? $path : $request->path(); // e.g. "prov/83/da44-...xml"
        $tail = ltrim($tail, '/');

        // Strip leading "prov/" if present
        if (str_starts_with($tail, 'prov/')) {
            $tail = substr($tail, 5);
        }

        // Only care about the last segment
        $tail = basename($tail);

        if (preg_match('#^([^/]+)\.(cfg|xml|boot)$#i', $tail, $m)) {
            return [$m[1], strtolower($m[2])];
        }
        // fallback: whole tail as id, assume cfg
        return [$tail, 'cfg'];
    }

    private function computeFlavor(Request $request, Devices $device, string $id, string $ext): array
    {
        $debug = false;
        // Raw tail after /prov/
        $raw = ltrim((string)($request->route('path') ?? $request->path()), '/');
        if (str_starts_with($raw, 'prov/')) $raw = substr($raw, 5);

        $vendor = strtolower((string) $device->device_vendor);
        $idLower = strtolower($id);
        $extLower = strtolower($ext);

        if ($debug) {
            logger('Vendor: ' . $vendor . '. ID: ' . $idLower . '. Ext: ' . $extLower);
        }

        // Detect Dinstar serial index: "{productId}/{serial}.xml"
        if ($vendor === 'dinstar' && $extLower === 'xml' && preg_match('#^(?<pid>\d{2})/[A-Za-z0-9-]+\.xml$#', $raw, $m)) {
            return [
                'flavor'     => 'serial.xml',
                'mime'       => 'application/xml',
            ];
        }

        // Polycom bootstrap index
        if ($vendor === 'polycom' && $extLower === 'cfg' && $idLower === '000000000000') {
            return [
                'flavor' => 'mac.cfg',
                'mime'   => 'application/xml',
            ];
        }

        // Polycom per-device: phone<MAC>.cfg (e.g., phone0004f2abcdef.cfg)
        if ($vendor === 'polycom' && $extLower === 'cfg' && preg_match('#(^|/)phone[0-9a-f]{12}\.cfg$#i', $raw)) {
            return [
                'flavor' => 'phonemac.cfg',
                'mime'   => 'application/xml',
            ];
        }

        // Polycom model-MAC (3–4 digit models, e.g., VVX600-, SPIP321-, SSIP7000-; also SSDuo)
        if (
            $vendor === 'polycom' &&
            $extLower === 'cfg' &&
            preg_match('#(^|/)(?:(?:SPIP|VVX|SSIP|EdgeE)\d{3,4}|SSDuo)-[0-9a-f]{12}\.cfg$#i', $raw)
        ) {
            return [
                'flavor' => 'model-mac.cfg',
                'mime'   => 'application/xml',
            ];
        }

        // Yealink model index (loose check)
        if ($vendor === 'yealink' && $extLower === 'cfg' && preg_match('/^y0{8}[0-9a-f]{2}$/i', $id)) {
            return [
                'flavor' => 'yealink-model.cfg',
                'mime'   => 'text/plain',
            ];
        }

        // Htek per-device: cfg<MAC>.xml (e.g., cfg200a0d30064a.xml)
        if (
            $vendor === 'htek' &&
            $extLower === 'xml' &&
            preg_match('/^cfg[0-9a-f]{12}$/', $idLower)
        ) {
            return [
                'flavor' => 'mac.xml',
                'mime'   => 'application/xml',
            ];
        }

        // Grandstream per-device: cfg<MAC>.xml (e.g., cfg000b82877bd4.xml)
        if (
            $vendor === 'grandstream' &&
            $extLower === 'xml' &&
            preg_match('/^cfg[0-9a-f]{12}$/', $idLower)
        ) {
            return [
                'flavor' => 'cfgmac.xml',
                'mime'   => 'application/xml',
            ];
        }

        // Otherwise, treat as per-device config for all vendors
        // Note: Dinstar's per-device ".cfg" content is XML, others are plain text.
        $mime = ($vendor === 'dinstar') ? 'application/xml' : ($extLower === 'xml' ? 'application/xml' : 'text/plain');

        return [
            'flavor' => 'mac.cfg',
            'mime'   => $mime,
        ];
    }

    private function getProvisionSettings(?string $domainUuid, ?string $deviceUuid = null): array
    {
        // Defaults → simple [subcategory => cast(value)]
        $settings = [];
        DefaultSettings::query()
            ->select([
                'default_setting_subcategory',
                'default_setting_name',
                'default_setting_value',
                'default_setting_order',
                'default_setting_enabled',
            ])
            ->where('default_setting_category', 'provision')
            ->where('default_setting_enabled', 'true')
            ->orderBy('default_setting_subcategory')
            ->get()
            ->each(function ($r) use (&$settings) {
                $sub = (string) $r->default_setting_subcategory;
                $settings[$sub] = $this->castSettingValue(
                    (string) $r->default_setting_name,
                    (string) $r->default_setting_value
                );
            });

        // Domain overrides
        if (!empty($domainUuid)) {
            DomainSettings::query()
                ->select([
                    'domain_setting_subcategory',
                    'domain_setting_name',
                    'domain_setting_value',
                    'domain_setting_order',
                    'domain_setting_enabled',
                ])
                ->where('domain_uuid', $domainUuid)
                ->where('domain_setting_category', 'provision')
                ->where('domain_setting_enabled', 'true')
                ->orderBy('domain_setting_subcategory')
                ->get()
                ->each(function ($r) use (&$settings) {
                    $sub = (string) $r->domain_setting_subcategory;
                    $settings[$sub] = $this->castSettingValue(
                        (string) $r->domain_setting_name,
                        (string) $r->domain_setting_value
                    );
                });
        }

        // Device overrides (highest precedence)
        if (!empty($deviceUuid)) {
            $q = DeviceSettings::query()
                ->select([
                    'device_setting_subcategory',
                    'device_setting_name',
                    'device_setting_value',
                    'device_setting_enabled',
                ])
                ->where('device_uuid', $deviceUuid)
                ->where('device_setting_enabled', 'true');

            // optional tenant safety (recommended in multi-tenant setups)
            if (!empty($domainUuid)) {
                $q->where('domain_uuid', $domainUuid);
            }

            $q->orderBy('device_setting_subcategory')
                ->get()
                ->each(function ($r) use (&$settings) {
                    $sub = (string) $r->device_setting_subcategory;
                    $settings[$sub] = $this->castSettingValue(
                        (string) $r->device_setting_name,
                        (string) $r->device_setting_value
                    );
                });
        }
        $settings['provision_base_url'] = config('app.url') . '/prov/';
        return $settings;
    }

    /**
     * Cast common types: text, numeric, boolean, json.
     * Falls back to string.
     */
    private function castSettingValue(string $type, string $value)
    {
        $t = strtolower($type);

        if ($t === 'boolean' || $t === 'bool' || $t === 'enabled') {
            return in_array(strtolower($value), ['true', 't', '1', 'yes', 'on'], true);
        }

        if ($t === 'numeric' || $t === 'number' || $t === 'integer' || $t === 'int' || $t === 'float') {
            return is_numeric($value) ? ($value + 0) : $value;
        }

        if ($t === 'json') {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        // text, password, select, label, etc.
        return $value;
    }


    private function getEffectiveDeviceKeysByArea(Devices $device, array $settings = []): array
    {
        $profileKeys = collect($device->profile?->keys ?? []);
        $legacyKeys  = collect($device->legacy_keys ?? []);
        $newKeys     = collect($device->keys ?? []);

        // For Polycom, collapse duplicate line keys before mapping
        if ($device->device_vendor === 'polycom') {
            $newKeys = collect($this->normalizeNewKeysForPolycom($device, $device->keys ?? []));
        }

        $maps = [
            'main' => [],
            'multi_purpose' => [],
        ];

        // Normalize old/profile/legacy keys into the same effective shape
        $normalizeLegacyKey = function (array $item, string $source) use ($device) {
            $vendor = strtolower((string) $device->device_vendor);
            $type  = strtolower((string) ($item['type'] ?? ''));
            $line  = $item['line'] !== null ? (int) $item['line'] : null;
            $value = $item['value'] ?? null;
            $label = $item['label'] ?? null;

            if ($type === 'line') {
                // Polycom legacy/profile line keys already store the correct value.
                // Do not rewrite them from the device line's auth_id/display_name.
                if ($vendor !== 'polycom') {
                    // Legacy/profile line indexes appear to be zero-based:
                    // 0 => first line, 1 => second line, etc.
                    $lookupLineNumber = $line !== null ? $line + 1 : 1;

                    $lineObj = collect($device->lines ?? [])->firstWhere('line_number', $lookupLineNumber);

                    if ($lineObj) {
                        $value = $lineObj->auth_id ?? $value;

                        if (empty($label)) {
                            $label = $lineObj->display_name ?? $lineObj->auth_id ?? null;
                        }
                    }
                }
            }

            return [
                'id'        => $item['id'],
                'area'      => $item['area'],
                'category'  => $item['category'],
                'type'      => $item['type'], // keep logical type here
                'line'      => $line,
                'value'     => $value,
                'extension' => $item['extension'] ?? null,
                'label'     => $label,
                'source'    => $source,
            ];
        };

        // Seed with profile keys
        foreach ($profileKeys as $pk) {
            $id = (int) $pk->profile_key_id;
            if ($id <= 0) {
                continue;
            }

            [$area, $category] = $this->resolveLegacyKeyPlacement(
                $device->device_vendor,
                $pk->profile_key_category
            );

            if (!array_key_exists($area, $maps)) {
                $maps[$area] = [];
            }

            $maps[$area][$id] = $normalizeLegacyKey([
                'id'        => $id,
                'area'      => $area,
                'category'  => $category,
                'type'      => $pk->profile_key_type ?? null,
                'line'      => $pk->profile_key_line !== null ? (int) $pk->profile_key_line : null,
                'value'     => $pk->profile_key_value ?? null,
                'extension' => $pk->profile_key_extension ?? null,
                'label'     => $pk->profile_key_label ?? null,
            ], 'profile');
        }

        // Overlay with legacy device keys
        foreach ($legacyKeys as $dk) {
            $id = (int) $dk->device_key_id;
            if ($id <= 0) {
                continue;
            }

            [$area, $category] = $this->resolveLegacyKeyPlacement(
                $device->device_vendor,
                $dk->device_key_category
            );

            if (!array_key_exists($area, $maps)) {
                $maps[$area] = [];
            }

            $maps[$area][$id] = $normalizeLegacyKey([
                'id'        => $id,
                'area'      => $area,
                'category'  => $category,
                'type'      => $dk->device_key_type ?? null,
                'line'      => $dk->device_key_line !== null ? (int) $dk->device_key_line : null,
                'value'     => $dk->device_key_value ?? null,
                'extension' => $dk->device_key_extension ?? null,
                'label'     => $dk->device_key_label ?? null,
            ], 'device');
        }

        // Overlay with new keys -> keyed by area + index
        foreach ($newKeys as $nk) {
            $id = (int) ($nk->key_index ?? 0);
            if ($id <= 0) {
                continue;
            }

            $area = (string) ($nk->key_area ?? 'main');

            if (!array_key_exists($area, $maps)) {
                $maps[$area] = [];
            }

            $mapped = $this->mapNewDeviceKeyToLegacyShape($device, $nk);
            $mapped['area'] = $area;

            $maps[$area][$id] = $mapped;
        }

        foreach ($maps as $area => $map) {
            ksort($map, SORT_NUMERIC);

            $processed = $this->postProcessEffectiveKeys($device, array_values($map), $settings);

            // Translate to vendor-specific output only after all logical processing is done
            $maps[$area] = $this->translateEffectiveKeysForVendor($device, $processed);
        }

        return $maps;
    }

    private function translateEffectiveKeysForVendor(Devices $device, array $keys): array
    {
        foreach ($keys as &$key) {
            $translated = $this->translateKeyTypeForVendor(
                $device->device_vendor,
                (string) ($key['type'] ?? '')
            );

            if (!empty($translated['category'])) {
                $key['category'] = $translated['category'];
            }

            if (array_key_exists('type', $translated)) {
                $key['type'] = $translated['type'];
            }
        }
        unset($key);

        return $keys;
    }


    private function resolveLegacyKeyPlacement(?string $vendor, ?string $category): array
    {
        $vendor = strtolower((string) $vendor);
        $category = strtolower(trim((string) $category));

        // Grandstream legacy "memory" keys belong to multi-purpose keys,
        // not the main key area.
        if ($vendor === 'grandstream' && $category === 'memory') {
            return ['multi_purpose', 'line'];
        }

        return ['main', $category ?: null];
    }

    private function postProcessEffectiveKeys(Devices $device, array $keys, array $settings = []): array
    {
        $dropSelfExtensionKeys = $settings['drop_self_extension_keys'] ?? true;

        if ($dropSelfExtensionKeys) {
            // Build list of device’s own extensions
            $selfExts = collect($device->lines ?? [])
                ->pluck('auth_id')
                ->filter()
                ->map(fn($v) => (string) $v)
                ->unique();

            // Drop any key whose value matches a self extension,
            // but keep real line keys
            $keys = array_values(array_filter($keys, function ($k) use ($selfExts) {
                $type = strtolower((string) ($k['type'] ?? ''));
                $val = (string) ($k['value'] ?? '');

                if ($val === '') {
                    return true;
                }

                if ($type === 'line') {
                    return true;
                }

                return !$selfExts->contains($val);
            }));
        }

        foreach ($keys as $i => &$k) {
            $k['id'] = $i + 1;
        }
        unset($k);

        // Fill labels from Extensions.effective_caller_id_name (domain-scoped)
        $blfTargets = collect($keys)
            ->filter(fn($k) => (empty($k['label']) || $k['label'] === null) && !empty($k['value']))
            ->map(fn($k) => (string) ($k['value'] ?? ''))
            ->filter()
            ->unique()
            ->values();

        if ($blfTargets->isNotEmpty()) {
            $extLabels = Extensions::query()
                ->where('domain_uuid', $device->domain_uuid)
                ->whereIn('extension', $blfTargets->all())
                ->get(['extension', 'effective_caller_id_name'])
                ->keyBy('extension')
                ->map(fn($r) => $r->effective_caller_id_name)
                ->toArray();

            foreach ($keys as &$k) {
                if (empty($k['label'])) {
                    $val = (string) ($k['value'] ?? '');
                    if ($val !== '' && !empty($extLabels[$val])) {
                        $k['label'] = $extLabels[$val];
                    }
                }
            }
            unset($k);
        }

        return $keys;
    }

    /**
     * Convert a row from new device_keys table to the "legacy" key array shape,
     * including vendor-specific key_type translation.
     */
    private function mapNewDeviceKeyToLegacyShape(Devices $device, $nk): array
    {
        $type  = strtolower(trim((string) ($nk->key_type ?? '')));
        $id    = (int) ($nk->key_index ?? 0);
        $area  = (string) ($nk->key_area ?? 'main');
        $line  = 1;
        $value = $nk->key_value ?? null;
        $label = $nk->key_label ?? null;

        if ($device->device_vendor === 'polycom') {
            return $this->mapNewDeviceKeyToPolycomShape($device, $id, $type, $nk);
        }

        // Keep existing Grandstream line behavior
        if ($device->device_vendor === 'grandstream') {
            $line = $line - 1;
            if ($line < 0) {
                $line = 0;
            }
        }

        switch ($type) {
            case 'line':
                $line = (int) ($nk->key_value ?? 1);

                $lines = $device->lines ?? [];
                $lineObj = collect($lines)->firstWhere('line_number', $line);

                $label = $lineObj->display_name ?? null;
                $value = $lineObj->auth_id ?? null;

                if ($device->device_vendor === 'grandstream') {
                    $line = $line - 1;
                    if ($line < 0) {
                        $line = 0;
                    }
                }
                break;

            case 'park':
                $park = (string) ($nk->key_value ?? '');
                $value = ($park !== '') ? ('park+*' . $park) : null;
                break;

            case 'check_voicemail':
                $vm = (string) ($nk->key_value ?? '');
                $value = ($vm !== '') ? ('vm' . $vm) : null;
                break;
        }

        return [
            'id'        => $id,
            'area'      => $area,
            'category'  => 'line',
            'type'      => $type, // keep logical type here
            'line'      => $line,
            'value'     => $value,
            'extension' => null,
            'label'     => $label,
            'source'    => 'device',
        ];
    }

    /**
     * Translate generic key types (line/blf/park/...) to vendor-specific types/categories.
     * Expand these mappings as you discover what each vendor template expects.
     */
    private function translateKeyTypeForVendor(?string $vendor, string $keyType): array
    {
        $t = strtolower(trim($keyType));

        // Default: passthrough
        $out = ['category' => null, 'type' => $t];

        switch ($vendor) {
            case 'yealink':
                $out['type'] = match ($t) {
                    'line' => '15',
                    'blf' => '16',
                    'speed_dial' => '13',
                    '' => '0',
                    'park' => '16',
                    'check_voicemail' => '16',
                    default      => $t,
                };
                break;

            case 'grandstream':
                $out['type'] = match ($t) {
                    'speed_dial' => 'speed dial',
                    '' => 'none',
                    'park' => 'monitored call park',
                    'check_voicemail' => 'blf',
                    default      => $t,
                };
                break;

            default:
                // unknown vendor -> keep generic
                break;
        }

        return $out;
    }

    private function mapNewDeviceKeyToPolycomShape(Devices $device, int $id, string $type, $nk): array
    {
        $value = $nk->key_value ?? null;
        $label = $nk->key_label ?? null;

        $category = 'line';
        $polyType = null;
        $line     = 0;
        $outValue = null;

        switch ($type) {
            case '':
            case 'unassigned':
                $category = 'line';
                $polyType = 'unassigned';
                break;

            case 'line':
                $category = 'line';
                $polyType = 'line';
                $line     = (int) ($value ?? 1); // line number (from key_value)

                $lineStr = (string) ($value ?? '');
                $count = collect($device->keys ?? [])
                    ->filter(
                        fn($k) =>
                        strtolower((string)($k->key_type ?? '')) === 'line'
                            && (string)($k->key_value ?? '') === $lineStr
                    )
                    ->count();

                $outValue = (string) max(1, $count); // appearances
                break;

            case 'blf':
                $category = 'line';
                $polyType = 'normal';
                $line     = 1;
                $outValue = ($value !== null && $value !== '') ? (string) $value : null;
                break;

            case 'park':
                $category = 'line';
                $polyType = 'automata';
                $line     = 1;
                $park = (string) ($value ?? '');
                $outValue = ($park !== '') ? ('park+*' . $park) : null;
                break;

            case 'check_voicemail':
                $category = 'line';
                $polyType = 'normal';
                $line     = 1;
                $vm = (string) ($value ?? '');
                $outValue = ($vm !== '') ? ('vm' . $vm) : null;
                break;

            case 'speed_dial':
                $category = 'line';
                $polyType = null;
                $line     = 1;
                $outValue = ($value !== null && $value !== '') ? (string) $value : null;
                break;
        }

        return [
            'id'        => $id,
            'category'  => $category,
            'type'      => $polyType,
            'line'      => $line,
            'value'     => $outValue,
            'extension' => null,
            'label'     => ($label !== '' ? $label : null),
            'source'    => 'device',
        ];
    }


    private function normalizeNewKeysForPolycom(Devices $device, $newKeys)
    {
        $vendor = strtolower((string) $device->device_vendor);

        $keys = collect($newKeys ?? []);

        if ($vendor !== 'polycom') {
            return $keys;
        }

        // For Polycom: group "line" keys by key_value (line number)
        $lineGroups = $keys
            ->filter(fn($k) => strtolower((string)($k->key_type ?? '')) === 'line')
            ->groupBy(fn($k) => (string)($k->key_value ?? ''));

        $collapsedLineKeys = $lineGroups->map(function ($group) {
            // choose the first key_index as the "id"
            $first = $group->sortBy(fn($k) => (int)($k->key_index ?? PHP_INT_MAX))->first();

            // attach how many times it appears
            $first->polycom_line_count = $group->count();

            return $first;
        })->values();

        $nonLineKeys = $keys->reject(fn($k) => strtolower((string)($k->key_type ?? '')) === 'line');

        // return merged list (non-line + collapsed line keys)
        return $nonLineKeys->concat($collapsedLineKeys);
    }


    /**
     * If $raw is valid XML, returns pretty-printed XML.
     * Keeps the XML declaration if the original had one.
     * Otherwise returns null (so callers can no-op for plain text).
     */
    private function maybePrettyPrintXml(string $raw): ?string
    {
        $trimmed = ltrim($raw);
        // quick heuristic to avoid wasting work for obvious non-XML
        if ($trimmed === '' || $trimmed[0] !== '<') {
            return null;
        }

        $hadDeclaration = str_starts_with($trimmed, '<?xml');

        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;

        // suppress warnings; we'll check success explicitly
        $ok = $dom->loadXML($raw, LIBXML_NOERROR | LIBXML_NOWARNING);
        if (!$ok) {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            return null;
        }

        $dom->formatOutput = true;

        // If the original had an XML declaration, keep it; otherwise output element only
        $out = $hadDeclaration ? $dom->saveXML() : $dom->saveXML($dom->documentElement);

        libxml_use_internal_errors($prev);
        return $out;
    }

    private function normalizeTransportForVendor(?string $vendor, ?string $transport): ?string
    {
        $vendor = strtolower((string) $vendor);
        $t = strtolower(trim((string) $transport));

        if ($vendor === 'polycom') {
            return match ($t) {
                'tcp' => 'TCPOnly',
                'tls' => 'TLS',
                'dns srv', 'dnssrv', 'dnsnaptr' => 'DNSnaptr',
                '', 'udp' => 'UDPOnly',
                default => 'UDPOnly',
            };
        }

        if ($vendor === 'yealink') {
            return match ($t) {
                'tcp' => '1',
                'tls' => '2',
                'dns srv', 'dnssrv', 'dnsnaptr' => '3',
                '', 'udp' => '0',
                default => '0',
            };
        }

        if ($vendor === 'grandstream') {
            return match ($t) {
                'tcp' => 'TCP',
                'tls' => 'Tls Or Tcp',
                'dns srv', 'dnssrv', 'dnsnaptr' => 'dnssrv',
                '', 'udp' => 'UDP',
                default => 'UDP',
            };
        }

        // Other vendors: leave as-is (or extend with more mappings later)
        return $transport ?: null;
    }

    private function normalizeProvisionText(string $raw): string
    {
        // 1) Strip leading indentation on each non-empty line
        $out = preg_replace('/^[ \t]+/m', '', $raw);

        // 2) Collapse 3+ blank lines into a single blank line
        $out = preg_replace('/\R{3,}/', PHP_EOL . PHP_EOL, $out);

        // 3) Trim trailing whitespace at line ends
        $out = preg_replace('/[ \t]+$/m', '', $out);

        // 4) Ensure file ends with a single newline (many phones prefer it)
        if ($out === '' || substr($out, -1) !== "\n") {
            $out .= "\n";
        }

        return $out;
    }
}
