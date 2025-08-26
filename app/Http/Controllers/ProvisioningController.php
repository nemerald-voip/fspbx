<?php

namespace App\Http\Controllers;

use App\Models\Devices;
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
        // Compute flavor + MIME
        $flv = $this->computeFlavor($request, $device, $id, $ext);

        // Add provisioning context
        $vars += [
            'flavor'        => $flv['flavor'],           // 'serial.xml' | 'mac.cfg' | 'poly-index.cfg' | 'yealink-model.cfg'
            'requested_ext' => strtolower($ext),
        ];

        $body = Blade::render($tpl->content, $vars);

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
                // include the PK and the FK back to devices + anything you actually use
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
                'sip_transport'     => $line->sip_transport ?? null,
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

        if (preg_match('#^([^/]+)\.(cfg|xml)$#i', $tail, $m)) {
            return [$m[1], strtolower($m[2])];
        }
        // fallback: whole tail as id, assume cfg
        return [$tail, 'cfg'];
    }

    private function computeFlavor(Request $request, Devices $device, string $id, string $ext): array
    {
        // Raw tail after /prov/
        $raw = ltrim((string)($request->route('path') ?? $request->path()), '/');
        if (str_starts_with($raw, 'prov/')) $raw = substr($raw, 5);

        $vendor = strtolower((string) $device->device_vendor);
        $idLower = strtolower($id);
        $extLower = strtolower($ext);

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
                'flavor' => 'poly-index.cfg',
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
     * Cast common FusionPBX types: text, numeric, boolean, json.
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
}
