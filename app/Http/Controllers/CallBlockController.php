<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCallBlockRequest;
use App\Http\Requests\UpdateCallBlockRequest;
use App\Models\CallBlock;
use App\Models\CDR;
use App\Models\Extensions;
use App\Models\Voicemails;
use App\Services\CallBlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CallBlockController extends Controller
{
    protected int $perPage = 50;

    public function index()
    {
        if (! userCheckPermission('call_block_view')) {
            return redirect('/');
        }

        return Inertia::render('CallBlocks', [
            'routes' => [
                'current_page' => route('call-blocks.index'),
                'data_route' => route('call-blocks.data'),
                'select_all' => route('call-blocks.select.all'),
                'bulk_delete' => route('call-blocks.bulk.delete'),
                'bulk_toggle' => route('call-blocks.bulk.toggle'),
                'store' => route('call-blocks.store'),
                'item_options' => route('call-blocks.item.options'),
            ],
            'permissions' => [
                'create' => userCheckPermission('call_block_add'),
                'update' => userCheckPermission('call_block_edit'),
                'destroy' => userCheckPermission('call_block_delete'),
                'view_all_records' => userCheckPermission('call_block_view_all_records'),
                'view_self_records' => userCheckPermission('call_block_view_self_records'),
            ],
        ]);
    }

    public function store(StoreCallBlockRequest $request, CallBlockService $service): JsonResponse
    {
        try {
            $callBlock = $service->save($request->validated(), $request->parsedAction());

            return response()->json([
                'messages' => ['success' => ['Call block created successfully.']],
                'call_block_uuid' => $callBlock->call_block_uuid,
            ], 201);
        } catch (\Throwable $e) {
            logger('CallBlockController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create call block.']],
            ], 500);
        }
    }

    public function update(UpdateCallBlockRequest $request, CallBlock $call_block, CallBlockService $service): JsonResponse
    {
        if (! $this->canAccessCallBlock($call_block)) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $service->save($request->validated(), $request->parsedAction(), $call_block);

            return response()->json([
                'messages' => ['success' => ['Call block updated successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('CallBlockController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update call block.']],
            ], 500);
        }
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));
        $sourceCdrUuid = $request->input('source_cdr_uuid');

        if ($itemUuid && ! userCheckPermission('call_block_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (! $itemUuid && ! userCheckPermission('call_block_add')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if ($itemUuid) {
            $item = CallBlock::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereKey($itemUuid)
                ->firstOrFail();

            if (! $this->canAccessCallBlock($item)) {
                return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
            }
        } else {
            $item = new CallBlock([
                'call_block_direction' => 'inbound',
                'extension_uuid' => userCheckPermission('call_block_view_all_records') ? null : optional(auth()->user())->extension_uuid,
                'call_block_app' => 'reject',
                'call_block_enabled' => 'true',
            ]);

            if ($sourceCdrUuid) {
                $item = $this->prefillFromCdr($sourceCdrUuid, $item);
            }
        }

        return response()->json([
            'item' => $item,
            'duplicate_call_block' => $sourceCdrUuid ? $this->duplicateCallBlock($item) : null,
            'extension_scope_options' => $this->extensionScopeOptions(),
            'action_options' => $this->actionOptions(),
            'voicemail_options' => $this->voicemailOptions(),
            'routes' => [
                'store_route' => route('call-blocks.store'),
                'update_route' => $itemUuid ? route('call-blocks.update', ['call_block' => $item->call_block_uuid]) : null,
            ],
        ]);
    }

    public function getData(Request $request)
    {
        if (! $this->canViewRecords()) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return $this->scopedCallBlocks($request)
            ->select([
                'domain_uuid',
                'call_block_uuid',
                'call_block_direction',
                'extension_uuid',
                'call_block_name',
                'call_block_country_code',
                'call_block_number',
                'call_block_count',
                'call_block_app',
                'call_block_data',
                'call_block_enabled',
                'call_block_description',
            ])
            ->with([
                'extension:extension_uuid,extension,effective_caller_id_name',
            ])
            ->allowedSorts([
                'call_block_direction',
                'call_block_name',
                'call_block_country_code',
                'call_block_number',
                'call_block_count',
                'call_block_app',
                'call_block_enabled',
                'call_block_description',
            ])
            ->defaultSort('call_block_direction', 'call_block_number')
            ->paginate($this->perPage);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! $this->canViewRecords()) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $items = $this->scopedCallBlocks($request)
            ->select(['call_block_uuid'])
            ->defaultSort('call_block_direction', 'call_block_number')
            ->pluck('call_block_uuid');

        return response()->json([
            'items' => $items,
            'messages' => ['success' => ['All matching call blocks selected.']],
        ]);
    }

    public function bulkDelete(Request $request, CallBlockService $service): JsonResponse
    {
        if (! userCheckPermission('call_block_delete')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json(['messages' => ['error' => ['No call blocks selected.']]], 422);
        }

        $items = $this->scopedCallBlocks(new Request())
            ->whereIn('call_block_uuid', $uuids)
            ->get();

        $deleted = $service->delete($items);

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} call block(s)."]],
        ]);
    }

    public function bulkToggle(Request $request, CallBlockService $service): JsonResponse
    {
        if (! userCheckPermission('call_block_edit')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $uuids = $this->validatedUuids($request);
        if (empty($uuids)) {
            return response()->json(['messages' => ['error' => ['No call blocks selected.']]], 422);
        }

        $items = $this->scopedCallBlocks(new Request())
            ->whereIn('call_block_uuid', $uuids)
            ->get();

        $service->toggle($items);

        return response()->json([
            'messages' => ['success' => ['Call block status toggled.']],
        ]);
    }

    private function scopedCallBlocks(Request $request): QueryBuilder
    {
        return QueryBuilder::for(CallBlock::class, $request)
            ->where('domain_uuid', session('domain_uuid'))
            ->when(! userCheckPermission('call_block_view_all_records'), function ($query) {
                $query->where('extension_uuid', optional(auth()->user())->extension_uuid);
            })
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('call_block_uuid', 'ilike', "%{$needle}%")
                            ->orWhere('call_block_direction', 'ilike', "%{$needle}%")
                            ->orWhere('call_block_name', 'ilike', "%{$needle}%")
                            ->orWhere('call_block_country_code', 'ilike', "%{$needle}%")
                            ->orWhere('call_block_number', 'ilike', "%{$needle}%")
                            ->orWhere('call_block_app', 'ilike', "%{$needle}%")
                            ->orWhere('call_block_data', 'ilike', "%{$needle}%")
                            ->orWhere('call_block_description', 'ilike', "%{$needle}%");
                    });
                }),
            ]);
    }

    private function canViewRecords(): bool
    {
        return userCheckPermission('call_block_view')
            && (userCheckPermission('call_block_view_all_records') || userCheckPermission('call_block_view_self_records'));
    }

    private function canAccessCallBlock(CallBlock $callBlock): bool
    {
        if ($callBlock->domain_uuid !== session('domain_uuid')) {
            return false;
        }

        if (userCheckPermission('call_block_view_all_records')) {
            return true;
        }

        return userCheckPermission('call_block_view_self_records')
            && $callBlock->extension_uuid === optional(auth()->user())->extension_uuid;
    }

    private function validatedUuids(Request $request): array
    {
        return collect($request->input('items', []))
            ->filter(fn ($uuid) => is_string($uuid) && preg_match('/^[0-9a-fA-F-]{36}$/', $uuid))
            ->values()
            ->all();
    }

    private function extensionScopeOptions(): array
    {
        $query = Extensions::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->select('extension_uuid', 'extension', 'effective_caller_id_name')
            ->orderBy('extension');

        if (! userCheckPermission('call_block_view_all_records')) {
            $query->where('extension_uuid', optional(auth()->user())->extension_uuid);
        }

        $options = $query->get()
            ->map(fn (Extensions $extension) => [
                'label' => $extension->name_formatted,
                'value' => $extension->extension_uuid,
            ])
            ->values()
            ->all();

        if (userCheckPermission('call_block_view_all_records')) {
            array_unshift($options, ['label' => 'All extensions', 'value' => '']);
        }

        return $options;
    }

    private function actionOptions(): array
    {
        $options = [
            ['label' => 'Reject', 'value' => 'reject:'],
            ['label' => 'Busy', 'value' => 'busy:'],
        ];

        if (userCheckPermission('call_block_voicemail')) {
            $options[] = ['label' => 'Voicemail', 'value' => 'voicemail'];
        }

        return $options;
    }

    private function voicemailOptions(): array
    {
        if (! userCheckPermission('call_block_voicemail')) {
            return [];
        }

        return Voicemails::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('voicemail_enabled', 'true')
            ->with([
                'extension' => function ($query) {
                    $query->select('extension_uuid', 'extension', 'effective_caller_id_name')
                        ->where('domain_uuid', session('domain_uuid'));
                },
            ])
            ->select('voicemail_uuid', 'voicemail_id', 'voicemail_description')
            ->orderBy('voicemail_id')
            ->get()
            ->map(fn (Voicemails $voicemail) => [
                'label' => $voicemail->extension
                    ? $voicemail->extension->name_formatted
                    : $voicemail->voicemail_id . ' - Team Voicemail',
                'value' => (string) $voicemail->voicemail_id,
            ])
            ->values()
            ->all();
    }

    private function prefillFromCdr(string $sourceCdrUuid, CallBlock $item): CallBlock
    {
        $cdr = CDR::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereKey($sourceCdrUuid)
            ->select([
                'xml_cdr_uuid',
                'domain_uuid',
                'direction',
                'caller_id_name',
                'caller_id_number',
                'caller_destination',
                'destination_number',
                'start_epoch',
            ])
            ->firstOrFail();

        $direction = $cdr->direction === 'outbound' ? 'outbound' : 'inbound';
        $callerIdName = $direction === 'inbound' ? trim((string) $cdr->caller_id_name) : '';
        $number = $direction === 'outbound'
            ? trim((string) ($cdr->destination_number ?: $cdr->caller_destination))
            : trim((string) $cdr->caller_id_number);

        if ($callerIdName !== '' && preg_replace('/\D+/', '', $callerIdName) === preg_replace('/\D+/', '', $number)) {
            $callerIdName = '';
        }

        $description = 'Created from call history';
        if ($cdr->start_date && $cdr->start_time) {
            $description .= ' on ' . $cdr->start_date . ' at ' . $cdr->start_time;
        }

        $item->forceFill([
            'call_block_direction' => $direction,
            'call_block_name' => $callerIdName ?: null,
            'call_block_country_code' => null,
            'call_block_number' => $number ?: null,
            'call_block_description' => $description,
        ]);

        return $item;
    }

    private function duplicateCallBlock(CallBlock $item): ?array
    {
        $name = trim((string) $item->call_block_name);
        $number = trim((string) $item->call_block_number);
        $digits = preg_replace('/\D+/', '', $number);

        if ($name === '' && $digits === '') {
            return null;
        }

        $duplicate = CallBlock::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('call_block_direction', $item->call_block_direction ?: 'inbound')
            ->when(! userCheckPermission('call_block_view_all_records'), function ($query) {
                $query->where('extension_uuid', optional(auth()->user())->extension_uuid);
            })
            ->where(function ($query) use ($name, $digits) {
                if ($name !== '') {
                    $query->orWhereRaw('lower(call_block_name) = ?', [mb_strtolower($name)]);
                }

                if ($digits !== '') {
                    $query->orWhereRaw("regexp_replace(coalesce(call_block_country_code::text, '') || coalesce(call_block_number::text, ''), '\\D+', '', 'g') = ?", [$digits]);
                }
            })
            ->select([
                'call_block_uuid',
                'call_block_name',
                'call_block_country_code',
                'call_block_number',
                'extension_uuid',
                'call_block_enabled',
            ])
            ->first();

        if (! $duplicate) {
            return null;
        }

        return [
            'call_block_uuid' => $duplicate->call_block_uuid,
            'label' => collect([
                trim((string) $duplicate->call_block_name),
                trim((string) $duplicate->call_block_country_code . $duplicate->call_block_number),
            ])->filter()->implode(' - '),
            'enabled' => $duplicate->call_block_enabled === 'true',
        ];
    }
}
