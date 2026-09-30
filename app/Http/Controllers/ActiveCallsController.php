<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Gateways;
use App\Services\FreeswitchEslService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ActiveCallsController extends Controller
{

    protected $viewName = 'ActiveCalls';
    protected $searchable = ['cid_name', 'cid_num', 'dest', 'application_data', 'application', 'read_codec', 'write_codec', 'secure'];
    protected $allowedSortFields = [
        'context',
        'created_epoch',
        'duration',
        'cid_name',
        'cid_num',
        'dest',
        'application',
        'read_codec',
        'secure',
    ];

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (! userCheckPermission('call_active_view')) {
            abort(403);
        }

        return Inertia::render(
            $this->viewName,
            [
                'showGlobal' => $this->filters($request)['showGlobal'],
                'pagination' => [
                    'per_page' => fspbx_pagination_per_page(),
                    'per_page_options' => fspbx_pagination_options(),
                ],
                'permissions' => $this->permissions(),

                'routes' => [
                    'data_route' => route('active-calls.data'),
                    'select_all' => route('active-calls.select.all'),
                    'action' => route('active-calls.action'),
                ]
            ]
        );
    }


    /**
     *  Get data
     */
    public function getData(Request $request, FreeswitchEslService $eslService)
    {
        if (! userCheckPermission('call_active_view')) {
            abort(403);
        }

        $filters = $this->filters($request);
        $data = $this->builder($filters, $eslService, $request);
        $userTz = auth()->user()->time_zone ?? 'UTC';
        $displayTz = $filters['showGlobal']
            ? $userTz
            : (get_local_time_zone(session('domain_uuid')) ?? $userTz);

        $data = $data->map(function ($call) use ($displayTz) {
            // Replace gateway UUID with gateway name (unchanged)
            if (isset($call['application_data']) && strpos($call['application_data'], 'sofia/gateway') !== false) {
                preg_match('/sofia\/gateway\/([a-z0-9\-]+)\//', $call['application_data'], $matches);

                if (isset($matches[1])) {
                    $gatewayUuid = $matches[1];
                    $gateway = Gateways::where('gateway_uuid', $gatewayUuid)->first();

                    if ($gateway) {
                        $call['application_data'] = str_replace($gatewayUuid, $gateway->gateway, $call['application_data']);
                    }
                }
            }

            $createdEpoch = isset($call['created_epoch']) ? (int) $call['created_epoch'] : null;

            // Duration start for JS (ms)
            $call['start_epoch'] = $createdEpoch ? $createdEpoch * 1000 : null;

            $call['display_timezone'] = $displayTz;

            // Build a reliable display timestamp (no ambiguous parsing)
            $call['created_display'] = $createdEpoch
                ? Carbon::createFromTimestamp($createdEpoch, 'UTC')
                    ->setTimezone($displayTz)
                    ->format('Y-m-d H:i:s')
                : null;

            $applicationData = $call['application_data'] ?? '';
            $call['app_full'] = trim(($call['application'] ?? '') . ($applicationData ? ': ' . $applicationData : ''));

            // short preview (keep it small)
            $call['app_preview'] = mb_strimwidth($call['app_full'], 0, 90, '…');

            return $call;
        });

        $perPage = fspbx_pagination_per_page($request);
        $lastPage = max(1, (int) ceil($data->count() / $perPage));
        $page = min($lastPage, max(1, (int) $request->input('page', 1)));

        return fspbx_paginate_collection($data, $perPage, $page);
    }

    /**
     * @param  array  $filters
     * @return \Illuminate\Support\Collection
     */
    public function builder(array $filters, FreeswitchEslService $eslService, Request $request)
    {

        $data = $eslService->getAllChannels();
        [$sortField, $sortOrder] = $this->sort($request);

        // Apply sorting using sortBy or sortByDesc depending on the sort order
        if ($sortField === 'duration') {
            $data = $sortOrder === 'asc'
                ? $data->sortByDesc('created_epoch')
                : $data->sortBy('created_epoch');
        } elseif ($sortOrder === 'asc') {
            $data = $data->sortBy($sortField);
        } else {
            $data = $data->sortByDesc($sortField);
        }

        // Check if showGlobal is set to true, otherwise filter by context
        if (empty($filters['showGlobal']) || ! userCheckPermission('call_active_all')) {
            $domainName = session('domain_name');

            $data = $data->filter(function ($item) use ($domainName) {
                return $item['context'] === $domainName;
            });
        }

        // Apply additional filters, if any
        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    // Pass the collection by reference to modify it directly
                    $data = $this->$method($data, $value);
                }
            }
        }

        return $data->values(); // Ensure re-indexing of the collection
    }

    /**
     * @param $collection
     * @param $value
     * @return void
     */
    protected function filterSearch($collection, $value)
    {
        $searchable = $this->searchable;

        // Case-insensitive partial string search in the specified fields
        $collection = $collection->filter(function ($item) use ($value, $searchable) {
            foreach ($searchable as $field) {
                if (stripos($item[$field] ?? '', $value) !== false) {
                    return true;
                }
            }
            return false;
        });

        return $collection;
    }


    public function handleAction(FreeswitchEslService $eslService)
    {
        if (! userCheckPermission('call_active_hangup')) {
            return response()->json([
                'errors' => ['permission' => [__('You do not have permission to end active calls.')]],
            ], 403);
        }

        try {
            foreach (request('ids') as $uuid) {
                if (request('action') == 'end_call') {
                    $result = $eslService->killChannel($uuid);
                }
            }

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => [__('Request successfully processed.')]]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Get all item IDs without pagination
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function selectAll(Request $request, FreeswitchEslService $eslService)
    {
        if (! userCheckPermission('call_active_view')) {
            return response()->json([
                'errors' => ['permission' => [__('You do not have permission to view active calls.')]],
            ], 403);
        }

        try {
            $allCalls = $this->builder($this->filters($request), $eslService, $request);

            $uuids = $allCalls->pluck('uuid');

            return response()->json([
                'messages' => ['success' => [__('All items selected')]],
                'items' => $uuids,
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Failed to select all items')]]
            ], 500);
        }
    }

    private function permissions(): array
    {
        return [
            'view' => userCheckPermission('call_active_view'),
            'hangup' => userCheckPermission('call_active_hangup'),
            'view_global' => userCheckPermission('call_active_all'),
        ];
    }

    private function filters(Request $request): array
    {
        $filters = $request->input('filter', $request->input('filterData', []));
        $filters = is_array($filters) ? $filters : [];

        return [
            'search' => $filters['search'] ?? null,
            'showGlobal' => filter_var($filters['showGlobal'] ?? false, FILTER_VALIDATE_BOOLEAN)
                && userCheckPermission('call_active_all'),
        ];
    }

    private function sort(Request $request): array
    {
        $sort = (string) $request->input('sort', '-created_epoch');
        $sortField = ltrim($sort, '-');

        if (! in_array($sortField, $this->allowedSortFields, true)) {
            return ['created_epoch', 'desc'];
        }

        return [$sortField, str_starts_with($sort, '-') ? 'desc' : 'asc'];
    }

}
