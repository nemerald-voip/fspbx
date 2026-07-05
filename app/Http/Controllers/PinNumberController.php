<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePinNumberRequest;
use App\Http\Requests\UpdatePinNumberRequest;
use App\Models\PinNumber;
use App\Services\PinNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PinNumberController extends Controller
{
    protected int $perPage = 50;

    public function index()
    {
        if (! userCheckPermission('pin_number_view')) {
            return redirect('/');
        }

        return Inertia::render('PinNumbers', [
            'routes' => [
                'current_page' => route('pin-numbers.index'),
                'data_route' => route('pin-numbers.data'),
                'select_all' => route('pin-numbers.select.all'),
                'bulk_copy' => route('pin-numbers.bulk.copy'),
                'bulk_delete' => route('pin-numbers.bulk.delete'),
                'bulk_toggle' => route('pin-numbers.bulk.toggle'),
                'store' => route('pin-numbers.store'),
                'item_options' => route('pin-numbers.item.options'),
                'export' => route('pin-numbers.export'),
            ],
            'permissions' => $this->permissions(),
        ]);
    }

    public function export()
    {
        if (! userCheckPermission('pin_number_view')) {
            abort(403);
        }

        $columns = [
            'pin_number_uuid',
            'domain_uuid',
            'pin_number',
            'accountcode',
            'enabled',
            'description',
        ];

        $rows = PinNumber::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->orderBy('pin_number')
            ->get($columns);

        return response()->streamDownload(function () use ($columns, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            foreach ($rows as $row) {
                fputcsv($handle, $row->only($columns));
            }

            fclose($handle);
        }, 'pin_numbers_' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function store(StorePinNumberRequest $request, PinNumberService $service): JsonResponse
    {
        try {
            $pinNumber = $service->save($request->validated());

            return response()->json([
                'messages' => ['success' => ['PIN number created successfully.']],
                'pin_number_uuid' => $pinNumber->pin_number_uuid,
            ], 201);
        } catch (\Throwable $e) {
            logger('PinNumberController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create PIN number.']],
            ], 500);
        }
    }

    public function update(UpdatePinNumberRequest $request, PinNumber $pin_number, PinNumberService $service): JsonResponse
    {
        if ($pin_number->domain_uuid !== session('domain_uuid')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $service->save($request->validated(), $pin_number);

            return response()->json([
                'messages' => ['success' => ['PIN number updated successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('PinNumberController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update PIN number.']],
            ], 500);
        }
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('pin_number_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if (! $itemUuid && ! userCheckPermission('pin_number_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($itemUuid) {
            $item = PinNumber::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereKey($itemUuid)
                ->firstOrFail();
        } else {
            $item = new PinNumber();
            $item->enabled = 'true';
        }

        return response()->json([
            'item' => $item,
            'routes' => [
                'store_route' => route('pin-numbers.store'),
                'update_route' => $itemUuid ? route('pin-numbers.update', ['pin_number' => $item->pin_number_uuid]) : null,
            ],
        ]);
    }

    public function getData(Request $request)
    {
        if (! userCheckPermission('pin_number_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        return $this->scopedPinNumbers($request)
            ->select([
                'domain_uuid',
                'pin_number_uuid',
                'pin_number',
                'accountcode',
                'enabled',
                'description',
            ])
            ->allowedSorts([
                'pin_number',
                'accountcode',
                'enabled',
                'description',
            ])
            ->defaultSort('pin_number')
            ->paginate($this->perPage);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('pin_number_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->scopedPinNumbers($request)
            ->select(['pin_number_uuid'])
            ->defaultSort('pin_number')
            ->pluck('pin_number_uuid');

        return response()->json([
            'items' => $items,
            'messages' => ['success' => ['All matching PIN numbers selected.']],
        ]);
    }

    public function bulkCopy(Request $request, PinNumberService $service): JsonResponse
    {
        if (! userCheckPermission('pin_number_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->itemsFromRequest($request);
        if ($items->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No PIN numbers selected.']],
            ], 422);
        }

        $copied = $service->copy($items);

        return response()->json([
            'messages' => ['success' => ["Copied {$copied} PIN number(s)."]],
        ]);
    }

    public function bulkDelete(Request $request, PinNumberService $service): JsonResponse
    {
        if (! userCheckPermission('pin_number_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->itemsFromRequest($request);
        if ($items->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No PIN numbers selected.']],
            ], 422);
        }

        $deleted = $service->delete($items);

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} PIN number(s)."]],
        ]);
    }

    public function bulkToggle(Request $request, PinNumberService $service): JsonResponse
    {
        if (! userCheckPermission('pin_number_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->itemsFromRequest($request);
        if ($items->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No PIN numbers selected.']],
            ], 422);
        }

        $service->toggle($items);

        return response()->json([
            'messages' => ['success' => ['PIN number status toggled.']],
        ]);
    }

    private function scopedPinNumbers(Request $request): QueryBuilder
    {
        return QueryBuilder::for(PinNumber::class)
            ->where('domain_uuid', session('domain_uuid'))
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('pin_number', 'ilike', "%{$needle}%")
                            ->orWhere('accountcode', 'ilike', "%{$needle}%")
                            ->orWhere('enabled', 'ilike', "%{$needle}%")
                            ->orWhere('description', 'ilike', "%{$needle}%");
                    });
                }),
                AllowedFilter::exact('enabled'),
            ]);
    }

    private function itemsFromRequest(Request $request): Collection
    {
        $uuids = collect($request->input('items', []))
            ->filter(fn ($uuid) => is_string($uuid) && preg_match('/^[0-9a-fA-F-]{36}$/', $uuid))
            ->values()
            ->all();

        if (empty($uuids)) {
            return collect();
        }

        return PinNumber::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('pin_number_uuid', $uuids)
            ->get();
    }

    private function permissions(): array
    {
        return [
            'create' => userCheckPermission('pin_number_add'),
            'update' => userCheckPermission('pin_number_edit'),
            'destroy' => userCheckPermission('pin_number_delete'),
            'copy' => userCheckPermission('pin_number_add'),
            'export' => userCheckPermission('pin_number_view'),
        ];
    }
}
