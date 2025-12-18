<?php

namespace App\Http\Controllers;

use App\Models\Devices;
use App\Models\Extensions;
use Illuminate\Http\Request;
use App\Models\DeviceSettings;
use App\Models\DomainSettings;
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
            'keys' => function ($k) {
                // Need FK back to device + correct orderBy column
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

        return [
            'device_uuid'   => (string) $device->device_uuid,
            'domain_uuid'   => (string) $device->domain_uuid,
            'vendor'        => $device->device_vendor ?? null,
            'domain_name' => $device->domain?->domain_name ?? null,
            'template' => $device->device_template ?? null,
            'template_uuid' => $device->device_template_uuid ?? null,
            'serial'        => (string) $device->serial_number ?? null,
            'mac'           => $device->device_address ?? null,
            'keys'        => $this->getEffectiveDeviceKeys($device),
            'lines'       => $lines,
            'line_count' => count($lines),
            'settings' => $this->getProvisionSettings(
                (string) $device->domain_uuid,
                (string) $device->device_uuid
            ),
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


    /**
     * Build final key layout where device-level keys override profile keys
     * by the same key order (device_key_id === profile_key_id).
     *
     * Output is a zero-indexed array sorted by 'id' (key order), each item:
     * [
     *   'id'        => int,   // key order
     *   'category'  => ?string,
     *   'type'      => ?string,
     *   'line'      => ?int,
     *   'value'     => ?string,
     *   'extension' => ?string,
     *   'label'     => ?string,
     *   'source'    => 'profile'|'device',
     * ]
     */
    private function getEffectiveDeviceKeys(Devices $device): array
    {
        $profileKeys = collect($device->profile?->keys ?? []);
        $deviceKeys  = collect($device->keys ?? []);

        $map = [];

        // Seed with profile keys (base)
        foreach ($profileKeys as $pk) {
            $id = (int) $pk->profile_key_id;
            if ($id <= 0) {
                continue;
            }
            $map[$id] = [
                'id'        => $id,
                'category'  => $pk->profile_key_category ?? null,
                'type'      => $pk->profile_key_type ?? null,
                'line'      => $pk->profile_key_line !== null ? (int) $pk->profile_key_line : null,
                'value'     => $pk->profile_key_value ?? null,
                'extension' => $pk->profile_key_extension ?? null,
                'label'     => $pk->profile_key_label ?? null,
                'source'    => 'profile',
            ];
        }

        // Overlay with device keys (override by same key order)
        foreach ($deviceKeys as $dk) {
            $id = (int) $dk->device_key_id;
            if ($id <= 0) {
                continue;
            }
            $map[$id] = [
                'id'        => $id,
                'category'  => $dk->device_key_category ?? null,
                'type'      => $dk->device_key_type ?? null,
                'line'      => $dk->device_key_line !== null ? (int) $dk->device_key_line : null,
                'value'     => $dk->device_key_value ?? null,
                'extension' => $dk->device_key_extension ?? null,
                'label'     => $dk->device_key_label ?? null,
                'source'    => 'device',
            ];
        }

        ksort($map, SORT_NUMERIC);

        // Use this if array needs to be normilized by key_id
        // $keys = collect(array_values($map))->keyBy('id')->toArray();

        $keys = array_values($map);
        // fill BLF labels from Extensions.effective_caller_id_name (domain-scoped)
        $blfTargets = collect($keys)
            ->filter(fn($k) => (empty($k['label']) || $k['label'] === null)
                && !empty($k['value']))
            ->map(fn($k) => (string) $k['value'])
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
                if ((empty($k['label']) || $k['label'] === null)
                ) {

                    $val = (string) ($k['value'] ?? '');
                    if ($val !== '' && !empty($extLabels[$val])) {
                        $k['label'] = $extLabels[$val];
                    }
                    // else: leave label null; Blade will fall back to value
                }
            }
            unset($k);
        }

        return $keys;
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
