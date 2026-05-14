<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMusicOnHoldRequest;
use App\Http\Requests\UpdateMusicOnHoldRequest;
use App\Http\Requests\UploadMusicOnHoldFileRequest;
use App\Models\MusicOnHold;
use App\Models\Phrases;
use App\Models\Recordings;
use App\Services\MusicOnHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class MusicOnHoldController extends Controller
{

    public function index()
    {
        if (! userCheckPermission('music_on_hold_view')) {
            return redirect('/');
        }

        return Inertia::render('MusicOnHold', [
            'pagination' => [
                'per_page' => fspbx_pagination_per_page(),
                'per_page_options' => fspbx_pagination_options(),
            ],
            'routes' => [
                'current_page' => route('music-on-hold.index'),
                'data_route' => route('music-on-hold.data'),
                'store' => route('music-on-hold.store'),
                'update' => route('music-on-hold.update', ['music_on_hold' => '__STREAM__']),
                'item_options' => route('music-on-hold.item.options'),
                'select_all' => route('music-on-hold.select.all'),
                'bulk_delete' => route('music-on-hold.bulk.delete'),
                'upload' => route('music-on-hold.upload'),
                'file_delete' => route('music-on-hold.files.delete'),
                'reload' => route('music-on-hold.reload'),
            ],
            'permissions' => [
                'create' => userCheckPermission('music_on_hold_add'),
                'update' => userCheckPermission('music_on_hold_edit'),
                'destroy' => userCheckPermission('music_on_hold_delete'),
                'reload' => userCheckPermission('music_on_hold_edit'),
                'view_all' => userCheckPermission('music_on_hold_all'),
                'manage_domain' => false,
                'view_path' => userCheckPermission('music_on_hold_path'),
            ],
        ]);
    }

    public function store(StoreMusicOnHoldRequest $request, MusicOnHoldService $service): JsonResponse
    {
        $stream = $service->save($request->validated());

        return response()->json([
            'messages' => ['success' => ['Music on hold stream created.']],
            'music_on_hold_uuid' => $stream->music_on_hold_uuid,
        ], 201);
    }

    public function update(UpdateMusicOnHoldRequest $request, MusicOnHold $music_on_hold, MusicOnHoldService $service): JsonResponse
    {
        abort_unless($this->canManage($music_on_hold), 403);

        $service->save($request->validated(), $music_on_hold);

        return response()->json([
            'messages' => ['success' => ['Music on hold stream updated.']],
        ]);
    }

    public function upload(UploadMusicOnHoldFileRequest $request, MusicOnHoldService $service): JsonResponse
    {
        $stream = $service->upload($request->validated(), $request->file('file'));

        return response()->json([
            'messages' => ['success' => ['File uploaded.']],
            'music_on_hold_uuid' => $stream->music_on_hold_uuid,
        ]);
    }

    public function reload(MusicOnHoldService $service): JsonResponse
    {
        if (! userCheckPermission('music_on_hold_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $result = $service->reloadLocalStream();

        return response()->json([
            'messages' => [
                $result['success'] ? 'success' : 'error' => [$result['message']],
            ],
        ], $result['success'] ? 200 : 409);
    }

    public function getItemOptions(Request $request, MusicOnHoldService $service): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('music_on_hold_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (! $itemUuid && ! userCheckPermission('music_on_hold_add')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $item = $itemUuid
            ? $this->baseQuery($request, $service)->whereKey($itemUuid)->firstOrFail()
            : new MusicOnHold();

        if (! $item->exists) {
            $domainUuid = session('domain_uuid');

            $item->forceFill([
                'domain_uuid' => $domainUuid,
                'music_on_hold_path' => $service->defaultStreamPath($domainUuid, 'default'),
                'music_on_hold_shuffle' => 'false',
                'music_on_hold_channels' => '1',
                'music_on_hold_interval' => 20,
                'music_on_hold_timer_name' => 'soft',
            ]);
        }

        if ($item->exists) {
            abort_unless($this->canManage($item), 403);
            $item->forceFill([
                'music_on_hold_path' => $service->formPath($item),
                'music_on_hold_rate' => null,
            ]);
        }

        return response()->json([
            'item' => $item,
            'domains' => $this->domainOptions($service),
            'current_domain_uuid' => session('domain_uuid'),
            'current_domain_name' => session('domain_name'),
            'sounds_path_prefix' => '$${sounds_dir}/music',
            'streams' => $this->streamOptions($request, $service),
            'chime_options' => $this->chimeOptions(),
        ]);
    }

    public function getData(Request $request, MusicOnHoldService $service)
    {
        if (! userCheckPermission('music_on_hold_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $paginator = $this->baseQuery($request, $service)
            ->allowedSorts([
                'music_on_hold_name',
                'music_on_hold_rate',
                'music_on_hold_path',
                'music_on_hold_channels',
                'music_on_hold_shuffle',
            ])
            ->defaultSort('music_on_hold_name')
            ->paginate(fspbx_pagination_per_page($request))
            ->appends($request->query());

        return response()->json(
            $paginator->through(fn (MusicOnHold $stream) => $this->serializeStream($stream, $service))
        );
    }

    public function selectAll(Request $request, MusicOnHoldService $service): JsonResponse
    {
        if (! userCheckPermission('music_on_hold_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return response()->json([
            'items' => $this->baseQuery($request, $service)
                ->defaultSort('music_on_hold_name')
                ->pluck('music_on_hold_uuid'),
            'messages' => ['success' => ['All matching streams selected.']],
        ]);
    }

    public function bulkDelete(Request $request, MusicOnHoldService $service): JsonResponse
    {
        if (! userCheckPermission('music_on_hold_delete')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $items = collect($request->input('items', []))
            ->filter(fn ($item) => is_string($item) && Str::isUuid($item))
            ->values();

        if ($items->isEmpty()) {
            return response()->json(['messages' => ['error' => ['No streams selected.']]], 422);
        }

        $streams = $this->manageableQuery($service)
            ->whereIn('music_on_hold_uuid', $items)
            ->get();

        $deleted = $service->deleteStreams($streams);

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} music on hold stream(s)."]],
        ]);
    }

    public function deleteFile(Request $request, MusicOnHoldService $service): JsonResponse
    {
        if (! userCheckPermission('music_on_hold_delete')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $validated = $request->validate([
            'music_on_hold_uuid' => ['required', 'uuid'],
            'file' => ['required', 'string'],
        ]);

        $stream = $this->manageableQuery($service)
            ->whereKey($validated['music_on_hold_uuid'])
            ->firstOrFail();

        if (! $service->deleteFile($stream, $validated['file'])) {
            return response()->json([
                'messages' => ['error' => ['File not found.']],
            ], 404);
        }

        return response()->json([
            'messages' => ['success' => ['File deleted.']],
        ]);
    }

    public function download(MusicOnHold $music_on_hold, string $file, MusicOnHoldService $service)
    {
        if (! userCheckPermission('music_on_hold_view')) {
            abort(403);
        }

        abort_unless($this->canView($music_on_hold, $service), 404);

        $path = $service->streamFilePath($music_on_hold, $file);
        abort_unless($path, 404);

        return response()->file($path, [
            'Content-Type' => mime_content_type($path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    private function baseQuery(Request $request, MusicOnHoldService $service): QueryBuilder
    {
        $query = $service->representativeQuery()
            ->when(! ($request->boolean('filter.showGlobal') && userCheckPermission('music_on_hold_all')), function ($query) {
                $query->where(function ($query) {
                    $query->where('domain_uuid', session('domain_uuid'))
                        ->orWhereNull('domain_uuid');
                });
            });

        return QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('music_on_hold_name', 'ilike', "%{$needle}%")
                            ->orWhere('music_on_hold_path', 'ilike', "%{$needle}%")
                            ->orWhereRaw('CAST(music_on_hold_rate AS TEXT) ILIKE ?', ["%{$needle}%"]);
                    });
                }),
                AllowedFilter::callback('showGlobal', function () {}),
            ]);
    }

    private function manageableQuery(MusicOnHoldService $service)
    {
        return $service->scopedQuery()
            ->where('domain_uuid', session('domain_uuid'));
    }

    private function canView(MusicOnHold $stream, MusicOnHoldService $service): bool
    {
        if ($stream->domain_uuid === null) {
            return true;
        }

        return in_array($stream->domain_uuid, $service->accessibleDomainUuids(), true);
    }

    private function canManage(MusicOnHold $stream): bool
    {
        return $stream->domain_uuid === session('domain_uuid');
    }

    private function serializeStream(MusicOnHold $stream, MusicOnHoldService $service): array
    {
        return [
            'music_on_hold_uuid' => $stream->music_on_hold_uuid,
            'domain_uuid' => $stream->domain_uuid,
            'domain_label' => $stream->domain?->domain_description ?: $stream->domain?->domain_name ?: 'Global',
            'music_on_hold_name' => $stream->music_on_hold_name,
            'music_on_hold_path' => $service->formPath($stream),
            'music_on_hold_rate' => null,
            'rate_label' => '8, 16 kHz',
            'music_on_hold_shuffle' => $stream->music_on_hold_shuffle,
            'music_on_hold_channels' => $stream->music_on_hold_channels,
            'music_on_hold_interval' => $stream->music_on_hold_interval,
            'music_on_hold_timer_name' => $stream->music_on_hold_timer_name,
            'music_on_hold_chime_list' => $stream->music_on_hold_chime_list,
            'music_on_hold_chime_freq' => $stream->music_on_hold_chime_freq,
            'music_on_hold_chime_max' => $stream->music_on_hold_chime_max,
            'can_modify' => $this->canManage($stream),
            'files' => $service->fileRows($stream),
        ];
    }

    private function streamOptions(Request $request, MusicOnHoldService $service): array
    {
        return QueryBuilder::for($this->manageableQuery($service))
            ->defaultSort('music_on_hold_name')
            ->get()
            ->map(fn (MusicOnHold $stream) => [
                'label' => $stream->music_on_hold_name,
                'value' => $stream->music_on_hold_uuid,
            ])
            ->values()
            ->all();
    }

    private function domainOptions(MusicOnHoldService $service): array
    {
        if (! userCheckPermission('music_on_hold_domain')) {
            return [];
        }

        $domains = collect(session('domains', []))
            ->map(fn ($domain) => [
                'label' => data_get($domain, 'domain_description') ?? data_get($domain, 'domain_name') ?? data_get($domain, 'domain_uuid'),
                'value' => data_get($domain, 'domain_uuid'),
                'domain_name' => data_get($domain, 'domain_name'),
            ])
            ->values()
            ->all();

        array_unshift($domains, ['label' => 'Global', 'value' => '__global__', 'domain_name' => 'global']);

        return $domains;
    }

    private function chimeOptions(): array
    {
        $groups = [];

        $recordings = Recordings::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->orderBy('recording_name')
            ->get(['recording_name', 'recording_filename'])
            ->map(fn (Recordings $recording) => [
                'label' => $recording->recording_name,
                'value' => $recording->recording_filename,
            ])
            ->values()
            ->all();

        if (! empty($recordings)) {
            $groups[] = ['label' => 'Recordings', 'items' => $recordings];
        }

        $phrases = Phrases::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->orderBy('phrase_name')
            ->get(['phrase_uuid', 'phrase_name'])
            ->map(fn (Phrases $phrase) => [
                'label' => $phrase->phrase_name,
                'value' => 'phrase:' . $phrase->phrase_uuid,
            ])
            ->values()
            ->all();

        if (! empty($phrases)) {
            $groups[] = ['label' => 'Phrases', 'items' => $phrases];
        }

        foreach (getSoundsCollectionGrouped(session('domain_uuid')) as $group) {
            if (($group['label'] ?? '') === 'Sounds') {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    private function rateOptions(bool $includeDefault = false): array
    {
        $rates = [
            ['label' => '8 kHz', 'value' => '8000'],
            ['label' => '16 kHz', 'value' => '16000'],
        ];

        if ($includeDefault) {
            array_unshift($rates, ['label' => 'Default', 'value' => '']);
        }

        return $rates;
    }
}
