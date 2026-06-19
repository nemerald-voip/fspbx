<?php

namespace App\Http\Controllers;

use App\Services\DeviceActionService;
use App\Services\FreeswitchEslService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RegistrationsController extends Controller
{
    protected $viewName = 'Registrations';
    protected $searchable = ['lan_ip', 'wan_ip', 'port', 'agent', 'transport', 'sip_profile_name', 'sip_auth_user', 'sip_auth_realm'];
    protected $allowedSortFields = [
        'sip_auth_user',
        'sip_auth_realm',
        'agent',
        'lan_ip',
        'wan_ip',
        'port',
        'status',
        'expsecs',
        'ping_time',
        'sip_profile_name',
    ];

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $showGlobal = $this->truthy(request('filter.showGlobal', request('filterData.showGlobal', false)))
            && userCheckPermission('registration_all');

        return Inertia::render(
            $this->viewName,
            [
                'showGlobal' => $showGlobal,
                'pagination' => [
                    'per_page' => fspbx_pagination_per_page(),
                    'per_page_options' => fspbx_pagination_options(),
                ],
                'routes' => [
                    'current_page' => route('registrations.index'),
                    'data_route' => route('registrations.data'),
                    'select_all' => route('registrations.select.all'),
                    'action' => route('registrations.action'),
                ],
                'permissions' => [
                    'view_global' => userCheckPermission('registration_all'),
                ],
            ]
        );
    }


    /**
     *  Get data
     */
    public function getData(Request $request, FreeswitchEslService $eslService)
    {
        $data = $this->builder($this->filters($request), $eslService, $request);

        return fspbx_paginate_collection(
            $data,
            fspbx_pagination_per_page($request),
            (int) $request->input('page', 1)
        );
    }

    /**
     * @param  array  $filters
     */
    public function builder(array $filters, FreeswitchEslService $eslService, ?Request $request = null)
    {
        // get a list of current registrations
        $data = $eslService->getAllSipRegistrations();

        [$sortField, $sortOrder] = $this->sort($request);

        // Apply sorting using sortBy or sortByDesc depending on the sort order
        if ($sortOrder === 'asc') {
            $data = $data->sortBy($sortField);
        } else {
            $data = $data->sortByDesc($sortField);
        }

        // Check if showGlobal is set to true, otherwise filter by sip_auth_realm
        if (! $this->shouldShowGlobal($filters)) {
            $domainName = session('domain_name');

            $data = $data->filter(function ($item) use ($domainName) {
                return $item['sip_auth_realm'] === $domainName;
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


    public function handleAction(Request $request, DeviceActionService $deviceActionService, FreeswitchEslService $eslService): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:reboot,provision,unregister'],
            'items' => ['required_without:regs', 'array'],
            'items.*' => ['string'],
            'regs' => ['required_without:items', 'array'],
        ]);

        try {
            $action = $validated['action'];
            $registrations = $this->selectedRegistrations($request, $eslService);

            if ($registrations->isEmpty()) {
                return response()->json([
                    'messages' => ['error' => ['No registrations selected.']],
                ], 422);
            }

            foreach ($registrations as $reg) {
                $profile = (string) ($reg['sip_profile_name'] ?? '');
                $user = (string) ($reg['sip_auth_user'] ?? '');
                $realm = (string) ($reg['sip_auth_realm'] ?? '');
                $target = ($user && $realm) ? "{$user}@{$realm}" : '';

                if ($action === 'unregister' && $target && $profile) {
                    // Use native FreeSWITCH unregister first, then fall back to vendor-specific handling.
                    $commandsToTry = [
                        "sofia profile {$profile} flush_inbound_reg {$target} reboot",
                        "sofia profile {$profile} flush_inbound_reg {$target} all reboot",
                        "sofia profile {$profile} unregister {$user} {$realm}",
                    ];

                    $success = false;
                    foreach ($commandsToTry as $cmd) {
                        try {
                            $result = $eslService->executeCommand($cmd, false);

                            if (is_string($result) && preg_match('/\+?OK/i', $result)) {
                                $success = true;
                                break;
                            }
                        } catch (\Throwable $t) {
                            //
                        }
                    }

                    if (! $success) {
                        $deviceActionService->handleDeviceAction($reg, $action);
                    }
                } else {
                    $deviceActionService->handleDeviceAction($reg, $action);
                }
            }

            // Disconnect once at the end (cleanup)
            $eslService->disconnect();

            return response()->json([
                'messages' => ['success' => ['Request successfully processed.']],
            ], 201);

        } catch (\Exception $e) {
            logger("handleAction Exception: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [$e->getMessage()]],
            ], 500);
        }
    }


    /**
     * Get all items
     *
     * @return \Illuminate\Http\Response
     */
    public function selectAll(Request $request, FreeswitchEslService $eslService): JsonResponse
    {
        try {
            // Fetch all registrations without pagination
            $items = $this->builder($this->filters($request), $eslService, $request)
                ->pluck('call_id')
                ->filter()
                ->values();
    
            return response()->json([
                'messages' => ['success' => ['All matching registrations selected.']],
                'items' => $items,
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage());
    
            return response()->json([
                'messages' => ['error' => ['Failed to select all registrations.']],
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    private function selectedRegistrations(Request $request, FreeswitchEslService $eslService)
    {
        $items = collect($request->input('items', []))->filter()->values();

        if ($items->isEmpty() && $request->filled('regs')) {
            $items = collect($request->input('regs', []))
                ->pluck('call_id')
                ->filter()
                ->values();
        }

        $registrations = $this->builder(['showGlobal' => userCheckPermission('registration_all')], $eslService)
            ->filter(fn ($registration) => $items->contains($registration['call_id'] ?? null))
            ->values();

        return $registrations;
    }

    private function filters(Request $request): array
    {
        $filters = $request->input('filter', $request->input('filterData', []));
        $filters = is_array($filters) ? $filters : [];

        return [
            'search' => $filters['search'] ?? null,
            'showGlobal' => $this->truthy($filters['showGlobal'] ?? false),
        ];
    }

    private function sort(?Request $request): array
    {
        $sort = (string) ($request?->input('sort') ?? 'sip_auth_user');
        $sortOrder = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $sortField = ltrim($sort, '-');

        if (! in_array($sortField, $this->allowedSortFields, true)) {
            $sortField = 'sip_auth_user';
        }

        return [$sortField, $sortOrder];
    }

    private function shouldShowGlobal(array $filters): bool
    {
        return $this->truthy($filters['showGlobal'] ?? false) && userCheckPermission('registration_all');
    }

    private function truthy($value): bool
    {
        return $value === true || $value === 'true' || $value === '1' || $value === 1;
    }
}
