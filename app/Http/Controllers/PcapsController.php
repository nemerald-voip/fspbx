<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePcapSessionRequest;
use App\Models\Domain;
use App\Models\Extensions;
use App\Models\PcapCapture;
use App\Models\PcapSession;
use App\Services\Pcap\PcapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PcapsController extends Controller
{
    public function __construct(private PcapService $pcap)
    {
    }

    public function index(Request $request)
    {
        if (!userCheckPermission('pcap_list_view')) {
            return redirect('/');
        }

        return Inertia::render('Pcaps', [
            'routes' => [
                'data_route' => route('pcaps.data'),
                'item_options' => route('pcaps.item.options'),
                'store' => route('pcaps.store'),
                'bulk_stop' => route('pcaps.bulk.stop'),
                'bulk_delete' => route('pcaps.bulk.delete'),
                'captures' => route('pcaps.captures', ['session' => '__SESSION__']),
            ],
            'permissions' => [
                'create' => userCheckPermission('pcap_create'),
                'stop' => userCheckPermission('pcap_stop'),
                'destroy' => userCheckPermission('pcap_delete'),
                'download' => userCheckPermission('pcap_download'),
            ],
            'tenant_name' => $this->tenantName(),
            'status' => [
                'missing_tools' => $this->pcap->missingTools(),
                'paused' => $this->pcap->isPaused(),
            ],
        ]);
    }

    public function getData(Request $request)
    {
        abort_unless(userCheckPermission('pcap_list_view'), 403);

        $timezone = get_local_time_zone(session('domain_uuid'));
        $extensionLabels = null;

        $items = QueryBuilder::for(PcapSession::class)
            ->where('domain_uuid', session('domain_uuid'))
            ->withCount(['captures', 'captures as ready_count' => fn ($query) => $query->where('status', PcapCapture::STATUS_READY)])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);
                    if ($needle !== '') {
                        $query->where('name', 'ilike', "%{$needle}%");
                    }
                }),
            ])
            ->allowedSorts(['name', 'started_at', 'expires_at', 'delete_at'])
            ->defaultSort('-started_at')
            ->paginate(fspbx_pagination_per_page($request));

        $extensionUuids = $items->getCollection()->pluck('extension_uuids')->flatten()->filter()->unique()->values();
        $extensionLabels = Extensions::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('extension_uuid', $extensionUuids)
            ->pluck('extension', 'extension_uuid');

        $items->getCollection()->transform(function (PcapSession $session) use ($timezone, $extensionLabels) {
            return [
                'uuid' => $session->uuid,
                'name' => $session->name,
                'status' => $this->status($session),
                'duration' => $session->duration,
                'directions' => $session->directions ?? [],
                'scope' => $session->scope,
                'extensions' => collect($session->extension_uuids ?? [])
                    ->map(fn ($uuid) => $extensionLabels[$uuid] ?? null)
                    ->filter()
                    ->sort()
                    ->values(),
                'calls' => $session->ready_count,
                'started_at' => $this->formatTime($session->started_at, $timezone),
                'stopped_at' => $this->formatTime($session->stopped_at, $timezone),
                'capture_ends_at' => $this->formatTime($session->captureEndsAt(), $timezone),
                'delete_at' => $this->formatTime($session->delete_at, $timezone),
            ];
        });

        return $items;
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        if (!userCheckPermission('pcap_create')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $extensions = Extensions::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->orderBy('extension')
            ->get(['extension_uuid', 'extension', 'effective_caller_id_name'])
            ->map(fn ($extension) => [
                'value' => $extension->extension_uuid,
                'label' => $extension->name_formatted,
            ]);

        return response()->json([
            'item' => [
                'name' => null,
                'duration' => '1h',
                'keep_hours' => 24,
                'directions' => PcapSession::DIRECTIONS,
                'scope' => PcapSession::SCOPE_ALL,
                'extension_uuids' => [],
            ],
            'extension_options' => $extensions,
            'tenant_name' => $this->tenantName(),
            'routes' => [
                'store_route' => route('pcaps.store'),
            ],
        ]);
    }

    public function store(StorePcapSessionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $startedAt = now();
        $expiresAt = PcapService::expiresAt($data['duration'], $startedAt);

        PcapSession::query()->create([
            'domain_uuid' => session('domain_uuid'),
            'name' => $data['name'],
            'duration' => $data['duration'],
            'directions' => array_values(array_unique($data['directions'])),
            'scope' => $data['scope'],
            'extension_uuids' => $data['scope'] === PcapSession::SCOPE_EXTENSIONS ? array_values(array_unique($data['extension_uuids'] ?? [])) : null,
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
            'keep_hours' => (int) $data['keep_hours'],
            'delete_at' => PcapService::deleteAt($expiresAt, (int) $data['keep_hours']),
            'created_by' => auth()->user()?->user_uuid,
        ]);

        $this->pcap->forgetCapturingDomains();
        $this->pcap->syncCaptureFlag();

        return response()->json([
            'messages' => ['success' => [__('PCAP capture started.')]],
        ], 201);
    }

    public function bulkStop(Request $request): JsonResponse
    {
        if (!userCheckPermission('pcap_stop')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['uuid'],
        ]);

        // Stopping early starts the keep time now.
        $sessions = PcapSession::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('uuid', $validated['items'])
            ->capturing()
            ->get();

        foreach ($sessions as $session) {
            $session->stopped_at = now();
            $session->delete_at = PcapService::deleteAt($session->stopped_at, $session->keep_hours);
            $session->save();
        }
        $count = $sessions->count();

        $this->pcap->forgetCapturingDomains();
        $this->pcap->syncCaptureFlag();

        return response()->json([
            'messages' => ['success' => [__('Stopped :count capture(s).', ['count' => $count])]],
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        if (!userCheckPermission('pcap_delete')) {
            return response()->json(['messages' => ['error' => [__('Access denied.')]]], 403);
        }

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*' => ['uuid'],
        ]);

        $sessions = PcapSession::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('uuid', $validated['items'])
            ->get();

        foreach ($sessions as $session) {
            $this->pcap->deleteSession($session);
        }

        $this->pcap->syncCaptureFlag();

        return response()->json([
            'messages' => ['success' => [__('Deleted :count capture(s).', ['count' => $sessions->count()])]],
        ]);
    }

    public function captures(Request $request, string $session)
    {
        abort_unless(userCheckPermission('pcap_list_view'), 403);

        $session = PcapSession::query()->where('domain_uuid', session('domain_uuid'))->findOrFail($session);
        $timezone = get_local_time_zone(session('domain_uuid'));
        $canDownload = userCheckPermission('pcap_download');

        $items = $session->captures()
            ->where('status', PcapCapture::STATUS_READY)
            ->orderByDesc('call_started_at')
            ->paginate(fspbx_pagination_per_page($request));

        $items->getCollection()->transform(fn (PcapCapture $capture) => [
            'uuid' => $capture->uuid,
            'direction' => $capture->direction,
            'caller_id_name' => $capture->caller_id_name,
            'caller_id_number' => $capture->caller_id_number,
            'destination_number' => $capture->destination_number,
            'started_at' => $this->formatTime($capture->call_started_at, $timezone),
            'duration' => gmdate($capture->duration >= 3600 ? 'H:i:s' : 'i:s', $capture->duration),
            'legs' => $capture->legs,
            'packet_count' => $capture->packet_count,
            'file_size' => $this->formatBytes($capture->file_size),
            'download_url' => $canDownload ? route('pcaps.captures.download', ['capture' => $capture->uuid]) : null,
        ]);

        return $items;
    }

    public function download(string $capture)
    {
        abort_unless(userCheckPermission('pcap_download'), 403);

        $capture = PcapCapture::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('status', PcapCapture::STATUS_READY)
            ->findOrFail($capture);

        $disk = $this->pcap->disk();
        if (!$capture->file_path || !$disk->exists($capture->file_path)) {
            if ($capture->node !== $this->pcap->node()) {
                abort(404, __('This PCAP is stored on server :node.', ['node' => $capture->node]));
            }
            abort(404);
        }

        return response()->download($disk->path($capture->file_path), basename($capture->file_path), [
            'Content-Type' => 'application/vnd.tcpdump.pcap',
        ]);
    }

    private function tenantName(): string
    {
        $domain = Domain::query()->whereKey(session('domain_uuid'))->first(['domain_name', 'domain_description']);

        return trim((string) $domain?->domain_description) ?: (string) ($domain?->domain_name ?? session('domain_name'));
    }

    private function status(PcapSession $session): string
    {
        if ($session->stopped_at !== null) {
            return 'stopped';
        }

        if (!$session->expires_at->isFuture()) {
            return 'finished';
        }

        return $this->pcap->isPaused() ? 'paused' : 'capturing';
    }

    private function formatTime(?Carbon $time, string $timezone): ?string
    {
        return $time?->copy()->setTimezone($timezone)->format('M j, Y g:i A');
    }

    private function formatBytes(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return ($unit === 'B' ? $bytes : number_format($bytes, 1)) . ' ' . $unit;
            }
            $bytes /= 1024;
        }

        return '';
    }
}
