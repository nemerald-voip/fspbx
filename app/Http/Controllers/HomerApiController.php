<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateHomerApiSettingsRequest;
use App\Models\CDR;
use App\Services\HomerApiService;
use Illuminate\Http\Request;
use RuntimeException;

class HomerApiController extends Controller
{
    public function update(UpdateHomerApiSettingsRequest $request, HomerApiService $service)
    {
        $service->configured($request->validated())->save();

        return response()->json(['settings' => $service->publicSettings(), 'messages' => [
            'success' => [__('HOMER API settings saved.')],
        ]]);
    }

    public function test(UpdateHomerApiSettingsRequest $request, HomerApiService $service)
    {
        $settings = $service->configured($request->validated());
        try {
            $service->testConnection($settings);
            return response()->json(['messages' => ['success' => [__('HOMER API connection succeeded.')]]]);
        } catch (RuntimeException $exception) {
            return response()->json(['errors' => ['connection' => [$exception->getMessage()]]], 502);
        }
    }

    public function trace(Request $request, HomerApiService $service)
    {
        abort_unless(HomerApiService::canView(), 403);
        $data = $request->validate([
            'cdr_uuid' => ['required', 'uuid'],
            'download' => ['sometimes', 'boolean'],
            'format' => ['sometimes', 'string', 'in:pcap,txt'],
        ]);
        $query = CDR::query()->whereKey($data['cdr_uuid']);
        if (! userCheckPermission('xml_cdr_domain')) {
            $query->where('domain_uuid', session('domain_uuid'));
        }
        $cdr = $query->firstOrFail(['xml_cdr_uuid', 'domain_uuid', 'sip_call_id', 'start_epoch', 'end_epoch', 'duration']);

        try {
            $format = $data['format'] ?? 'pcap';
            $result = $service->trace($cdr, (bool) ($data['download'] ?? false), $format);
            $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
            if ($data['download'] ?? false) {
                return response($result, 200, $headers + [
                    'Content-Type' => $format === 'txt' ? 'text/plain; charset=UTF-8' : 'application/vnd.tcpdump.pcap',
                    'Content-Disposition' => 'attachment; filename="call-'.$cdr->xml_cdr_uuid.'.'.$format.'"',
                ]);
            }
            return response()->json(['messages' => $result], 200, $headers);
        } catch (RuntimeException $exception) {
            return response()->json(['errors' => ['capture' => [$exception->getMessage()]]], 422);
        }
    }
}
