<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use App\Models\ProvisioningTemplate;
use App\Models\Devices;

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
        $macRaw   = strtolower(preg_replace('/[^0-9a-f]/i', '', (string) $device->device_address));
        $macCol   = strtoupper(implode(':', str_split($macRaw, 2)));
        $macDash  = strtoupper(implode('-', str_split($macRaw, 2)));

        $device->loadMissing(['lines', 'lines.extension']);
        $lines = $device->lines->sortBy('line_number')->values();

        $account = [];
        foreach ($lines as $line) {
            $i = max(1, (int) $line->line_number);
            $ext = $line->extension;
            $account[$i] = [
                'auth_id'           => $ext?->extension ?? $line->auth_id,
                'password'          => $ext?->password ?? null,
                'display_name'      => $ext?->effective_caller_id_name ?? $line->display_name,
                'server_address'    => $line->server_address ?: ($line->server_address_primary ?? null),
                'server_address_primary'   => $line->server_address_primary,
                'server_address_secondary' => $line->server_address_secondary,
                'sip_port'          => $line->sip_port,
                'sip_transport'     => $line->sip_transport,
                'register_expires'  => $line->register_expires,
                'line_number'       => $i,
            ];
        }

        return [
            'device_uuid'   => (string) $device->device_uuid,
            'domain_uuid'   => (string) $device->domain_uuid,
            'vendor'        => strtolower((string) $device->device_vendor),
            'model'         => (string) $device->device_model,
            'serial'        => (string) $device->serial_number,

            'mac'           => $macRaw,
            'mac_colon'     => $macCol,
            'mac_dash'      => $macDash,

            'account'       => $account,
            'account_count' => count($account),

            'cfg' => function (string $key, $default = null) use (&$account, $macRaw, $macCol, $macDash, $device) {
                $map = [
                    'mac'       => $macRaw,
                    'mac_colon' => $macCol,
                    'mac_dash'  => $macDash,
                    'serial'    => $device->serial_number,
                ];
                return $map[$key] ?? ($default ?? null);
            },
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
}
