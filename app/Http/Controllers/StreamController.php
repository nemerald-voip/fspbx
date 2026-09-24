<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveStreamRequest;
use App\Models\MusicStreams;
use App\Services\StreamService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class StreamController extends Controller
{
    public function __construct(private StreamService $service) {}

    public function index()
    {
        abort_unless(userCheckPermission('stream_view'), 403);
        return Inertia::render('Streams', [
            'pagination' => ['per_page' => fspbx_pagination_per_page(), 'per_page_options' => fspbx_pagination_options()],
            'permissions' => [
                'create' => userCheckPermission('stream_add'),
                'update' => userCheckPermission('stream_edit'),
                'destroy' => userCheckPermission('stream_delete'),
                'global' => userCheckPermission('stream_all'),
            ],
            'routes' => [
                'data_route' => route('streams.data'),
                'item_options' => route('streams.item.options'),
                'select_all' => route('streams.select.all'),
                'bulk_action' => route('streams.bulk.action'),
                'module_status' => route('streams.module.status'),
                'modules' => userCheckPermission('module_view') ? route('modules.index') : null,
            ],
        ]);
    }

    private function filtered(Request $request): QueryBuilder
    {
        return QueryBuilder::for($this->service->visible(), $request)
            ->allowedFilters([AllowedFilter::callback('search', function ($query, $value) {
                $query->where(function ($q) use ($value) {
                    foreach (['stream_name', 'stream_location', 'stream_description', 'stream_enabled'] as $column) {
                        $q->orWhereRaw('LOWER('.$column.') LIKE ?', ['%'.mb_strtolower((string) $value).'%']);
                    }
                });
            })])->allowedSorts(['stream_name', 'stream_location', 'stream_enabled', 'stream_description'])
            ->defaultSort('stream_name', 'stream_uuid');
    }

    public function moduleStatus()
    {
        abort_unless(userCheckPermission('stream_view'), 403);
        $status = 'unknown';
        try {
            $esl = app(\App\Services\FreeswitchEslService::class);
            if ($esl->isConnected()) {
                // The ESL service decodes JSON literals, including true/false.
                $response = $esl->executeCommand('module_exists mod_shout');
                $status = match ($response) {
                    true, 'true' => 'running',
                    false, 'false' => 'stopped',
                    default => 'unknown',
                };
            }
        } catch (\Throwable $exception) {
            // An unavailable event socket must not look like a stopped module.
            $status = 'unknown';
        }

        return response()->json(['status' => $status])->header('Cache-Control', 'no-store');
    }

    public function getData(Request $request)
    {
        abort_unless(userCheckPermission('stream_view'), 403);
        return $this->filtered($request)->paginate(fspbx_pagination_per_page($request))
            ->through(fn ($item) => $item->toArray() + ['can_manage' => $this->service->canManage($item)]);
    }

    public function getItemOptions(Request $request)
    {
        $input = $request->validate(['itemUuid' => ['nullable', 'uuid']], \App\Support\Localization\ValidationMessages::common(), [
            'itemUuid' => __('Unique ID'),
        ]);
        $id = $input['itemUuid'] ?? null;
        abort_unless(userCheckPermission($id ? 'stream_edit' : 'stream_add'), 403);
        $item = $id ? $this->service->visible()->whereKey($id)->firstOrFail() : null;
        abort_if($item && ! $this->service->canManage($item), 403);
        return response()->json([
            'item' => $item ?? ['stream_enabled' => 'true', 'domain_uuid' => session('domain_uuid')],
            'domains' => userCheckPermission('stream_all') ? [
                ['value' => session('domain_uuid'), 'label' => __('Current account')],
                ['value' => '__global__', 'label' => __('Global')],
            ] : [],
            'routes' => [
                'store_route' => route('streams.store'),
                'update_route' => $id ? route('streams.update', $id) : null,
            ],
        ]);
    }

    public function store(SaveStreamRequest $request)
    {
        $item = $this->service->save($request->validated());
        return response()->json(['stream_uuid' => $item->stream_uuid, 'messages' => ['success' => [__('Stream created.')]]], 201);
    }

    public function update(SaveStreamRequest $request, MusicStreams $stream)
    {
        $this->service->save($request->validated(), $stream);
        return response()->json(['messages' => ['success' => [__('Stream updated.')]]]);
    }

    public function selectAll(Request $request)
    {
        abort_unless(userCheckPermission('stream_view'), 403);
        return response()->json(['items' => $this->filtered($request)->pluck('stream_uuid')]);
    }

    public function bulkAction(Request $request)
    {
        $values = $request->validate([
            'items' => ['required', 'array', 'min:1'], 'items.*' => ['required', 'uuid', 'distinct'],
            'action' => ['required', Rule::in(['delete', 'copy', 'enable', 'disable'])],
        ], \App\Support\Localization\ValidationMessages::common(), [
            'items' => __('Selected items'),
            'items.*' => __('Selected item'),
            'action' => __('Action'),
        ]);
        $permission = match ($values['action']) {
            'copy' => 'stream_add', 'delete' => 'stream_delete', default => 'stream_edit',
        };
        abort_unless(userCheckPermission($permission), 403);
        $this->service->bulk($values['items'], $values['action']);
        return response()->json(['messages' => ['success' => [__('Streams updated.')]]]);
    }
}
