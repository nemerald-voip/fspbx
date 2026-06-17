<?php

namespace App\Http\Controllers;

use App\Models\Devices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PolycomProvisioningFileController extends Controller
{
    private const LOG_SIZE_LIMIT_BYTES = 524288;

    private const BUCKETS = [
        'logs',
        'phoneconfigs',
        'directories',
        'calls',
        'corefiles',
    ];

    public function handle(Request $request, string $bucket, string $id, string $kind, string $ext)
    {
        /** @var Devices|null $device */
        $device = $request->attributes->get('prov.device');

        if (!$device || strtolower((string) $device->device_vendor) !== 'polycom') {
            return response('', 404);
        }

        $mac = $this->normalizeMac($id);
        if ($mac === '' || $mac !== $this->normalizeMac((string) $device->device_address)) {
            return response('', 404);
        }

        if (!in_array($bucket, self::BUCKETS, true)) {
            return response('', 404);
        }

        $filename = "{$mac}-{$kind}.{$ext}";
        if (!$this->isSafeFilename($filename)) {
            return response('', 404);
        }

        $path = "{$bucket}/{$filename}";

        if ($request->isMethod('PUT')) {
            if (!$this->storeUpload($bucket, $path, $request->getContent())) {
                return response('', 500);
            }

            return response('OK', 200);
        }

        if (!Storage::disk('polycom_provisioning_uploads')->exists($path)) {
            return response('', 404);
        }

        if ($request->isMethod('HEAD')) {
            return response('', 200);
        }

        return response(
            Storage::disk('polycom_provisioning_uploads')->get($path),
            200,
            ['Content-Type' => Storage::disk('polycom_provisioning_uploads')->mimeType($path) ?: 'application/octet-stream']
        );
    }

    private function normalizeMac(string $value): string
    {
        return strtolower(preg_replace('/[^0-9a-f]/i', '', $value) ?? '');
    }

    private function storeUpload(string $bucket, string $path, string $content): bool
    {
        $disk = Storage::disk('polycom_provisioning_uploads');

        if ($bucket === 'logs') {
            if (
                $disk->exists($path)
                && ($disk->size($path) + strlen($content)) > self::LOG_SIZE_LIMIT_BYTES
            ) {
                return $disk->delete($path) && $disk->put($path, $content);
            }

            return $disk->append($path, $content, '');
        }

        return $disk->put($path, $content);
    }

    private function isSafeFilename(string $filename): bool
    {
        return $filename !== ''
            && $filename === basename($filename)
            && !str_contains($filename, '..')
            && preg_match('/^[A-Za-z0-9._-]+$/', $filename);
    }
}
