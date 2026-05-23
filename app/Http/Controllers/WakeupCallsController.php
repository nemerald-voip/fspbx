<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Extensions;
use App\Models\WakeupCall;
use App\Models\Destinations;
use Illuminate\Http\Request;
use App\Models\WakeupAuthExt;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Requests\CreateWakeupCallRequest;
use App\Http\Requests\UpdateWakeupCallRequest;
use App\Http\Requests\UpdateWakeupCallSettingsRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class WakeupCallsController extends Controller
{
    public $model;
    protected $viewName = 'WakeupCalls';

    public function __construct()
    {
        $this->model = new WakeupCall();
    }

    /**
     * Display a listing of the resource.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index()
    {
        if (!$this->canViewRecords()) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'startPeriod' => now(get_local_time_zone(session('domain_uuid')))->startOfMonth()->toIso8601String(),
                'endPeriod' => now(get_local_time_zone(session('domain_uuid')))->endOfMonth()->toIso8601String(),
                'timezone' => function () {
                    return get_local_time_zone(session('domain_uuid'));
                },
                'routes' => [
                    'current_page' => route('wakeup-calls.index'),
                    'data_route' => route('wakeup-calls.data'),
                    'store' => route('wakeup-calls.store'),
                    'select_all' => route('wakeup-calls.select.all'),
                    'bulk_delete' => route('wakeup-calls.bulk.delete'),
                    'item_options' => route('wakeup-calls.item.options'),
                    'settings' => route('wakeup-calls.settings'),
                ],
                'permissions' => $this->permissions(),
                'pagination' => [
                    'per_page' => fspbx_pagination_per_page(),
                    'per_page_options' => fspbx_pagination_options(),
                ],

            ]
        );
    }


    public function getItemOptions()
    {
        try {

            $domain_uuid = session('domain_uuid');
            $item_uuid = request('item_uuid'); // Retrieve item_uuid from the request

            // Base navigation array without Greetings
            $navigation = [
                [
                    'name' => 'Settings',
                    'icon' => 'Cog6ToothIcon',
                    'slug' => 'settings',
                ],

            ];

            $status_options = [
                [
                    'value' => 'scheduled',
                    'name' => 'Scheduled',
                ],
                [
                    'value' => 'in_progress',
                    'name' => 'In Progress',
                ],
                [
                    'value' => 'snoozed',
                    'name' => 'Snoozed',
                ],
                [
                    'value' => 'completed',
                    'name' => 'Completed',
                ],
                [
                    'value' => 'canceled',
                    'name' => 'Cancelled',
                ],
                [
                    'value' => 'failed',
                    'name' => 'Failed',
                ],

            ];

            $extensions = Extensions::where('domain_uuid', $domain_uuid)
                ->when(!$this->canViewAllRecords(), function ($query) {
                    $query->where('extension_uuid', optional(auth()->user())->extension_uuid);
                })
                ->select('extension_uuid', 'extension', 'effective_caller_id_name')
                ->orderBy('extension', 'asc')
                ->get();

            // Transform the collection into the desired array format
            $extensionsOptions = $extensions->map(function ($extension) {
                return [
                    'value' => $extension->extension_uuid,
                    'name' => $extension->name_formatted,
                ];
            })->toArray();

            // Check if item_uuid exists to find an existing model
            if ($item_uuid) {
                // Find existing item by item_uuid
                $wakeup_call = $this->scopedWakeupCalls()
                    ->where($this->model->getKeyName(), $item_uuid)
                    ->first();

                // If a model exists, use it; otherwise, create a new one
                if (!$wakeup_call) {
                    throw new \Exception("Failed to fetch item details. Item not found");
                }

                // Define the update route
                $updateRoute = route('wakeup-calls.update', ['wakeup_call' => $item_uuid]);
            } else {
                // Create a new model if item_uuid is not provided
                $wakeup_call = new WakeupCall([
                    'domain_uuid' => $domain_uuid,
                    'extension_uuid' => $this->canViewAllRecords() ? null : optional(auth()->user())->extension_uuid,
                ]);
            }

            $permissions = $this->getUserPermissions();

            $routes = [
                'update_route' => $updateRoute ?? null,
                // 'get_routing_options' => route('routing.options'),

            ];


            // Construct the itemOptions object
            $itemOptions = [
                'navigation' => $navigation,
                'wakeup_call' => $wakeup_call,
                'extensions' => $extensionsOptions,
                'permissions' => $permissions,
                'status_options' => $status_options,
                'routes' => $routes,
                'timezone' => get_local_time_zone($wakeup_call->domain_uuid ?: $domain_uuid)
                // Define options for other fields as needed
            ];
            // logger($itemOptions);

            return $itemOptions;
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to fetch item details']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    public function getSettings()
    {
        try {
            if (!userCheckPermission('wakeup_calls_view_settings')) {
                return response()->json([
                    'success' => false,
                    'errors' => ['authorization' => ['Access denied.']]
                ], 403);
            }

            $domain_uuid = request('domain_uuid') ?? session('domain_uuid');

            // Base navigation array without Greetings
            $navigation = [
                [
                    'name' => 'Remote Wakeup',
                    'icon' => 'Cog6ToothIcon',
                    'slug' => 'remote_wakeup',
                ],

            ];

            $extensions = Extensions::where('domain_uuid', $domain_uuid)
                ->select('extension_uuid', 'extension', 'effective_caller_id_name')
                ->orderBy('extension', 'asc')
                ->get();

            // Transform the collection into the desired array format
            $extensionsOptions = $extensions->map(function ($extension) {
                return [
                    'value' => $extension->extension_uuid,
                    'name' => $extension->name_formatted,
                ];
            })->toArray();

            $allowed_list = WakeupAuthExt::where('domain_uuid', $domain_uuid)
                ->select('extension_uuid')
                ->get();

            // Transform the collection into the desired array format
            $allowed_list = $allowed_list->map(function ($item) {
                return [
                    'value' => $item->extension_uuid,
                ];
            })->toArray();

            $permissions = $this->getUserPermissions();

            $routes = [
                'update_route' => route('wakeup-calls.settings.update')
            ];


            // Construct the settings object
            $settings = [
                'navigation' => $navigation,
                'allowed_list' => $allowed_list,
                'extensions' => $extensionsOptions,
                'permissions' => $permissions,
                'routes' => $routes,
                // Define options for other fields as needed
            ];
            // logger($settings);

            return $settings;
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to fetch settings']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * @return LengthAwarePaginator
     */
    public function getData($paginate = 50): LengthAwarePaginator
    {
        if (!$this->canViewRecords()) {
            abort(403);
        }

        $showGlobal = filter_var(request('filter.showGlobal', false), FILTER_VALIDATE_BOOLEAN)
            && $this->canViewGlobalRecords();

        $data = QueryBuilder::for(WakeupCall::class)
            ->select([
                'uuid',
                'domain_uuid',
                'extension_uuid',
                'wake_up_time',
                'next_attempt_at',
                'recurring',
                'status',
                'retry_count',
            ])
            ->when($showGlobal, function ($query) {
                $query->with(['domain' => function ($query) {
                    $query->select('domain_uuid', 'domain_name', 'domain_description');
                }]);

                $domainUuids = Session::get('domains')->pluck('domain_uuid');
                $query->whereIn($this->model->getTable() . '.domain_uuid', $domainUuids);
            })
            ->when(!$showGlobal, function ($query) {
                $query->where($this->model->getTable() . '.domain_uuid', session('domain_uuid'));
            })
            ->when(!$this->canViewAllRecords(), function ($query) {
                $query->where('extension_uuid', optional(auth()->user())->extension_uuid);
            })
            ->with(['extension' => function ($query) {
                $query->select('extension_uuid', 'extension', 'effective_caller_id_name');
            }])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('status', 'ilike', "%{$needle}%")
                            ->orWhereHas('extension', function ($query) use ($needle) {
                                $query->where('extension', 'ilike', "%{$needle}%")
                                    ->orWhere('effective_caller_id_name', 'ilike', "%{$needle}%");
                            })
                            ->orWhereHas('domain', function ($query) use ($needle) {
                                $query->where('domain_name', 'ilike', "%{$needle}%")
                                    ->orWhere('domain_description', 'ilike', "%{$needle}%");
                            });
                    });
                }),
                AllowedFilter::callback('dateRange', function ($query, $value) {
                    if (!is_array($value) || count($value) < 2) {
                        return;
                    }

                    $query->whereBetween('wake_up_time', [
                        Carbon::parse($value[0])->setTimeZone('UTC'),
                        Carbon::parse($value[1])->setTimeZone('UTC'),
                    ]);
                }),
                AllowedFilter::callback('showGlobal', function ($query, $value) {
                    return;
                }),
            ])
            ->allowedSorts([
                'wake_up_time',
                'next_attempt_at',
                'status',
                'retry_count',
            ])
            ->defaultSort('wake_up_time');

        $data = $data->paginate(fspbx_pagination_per_page(request()));
        $data->getCollection()->transform(function ($wakeUpCall) {
            return $wakeUpCall->append(['wake_up_time_formatted', 'next_attempt_at_formatted']);
        });

        return $data;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\CreateWakeupCallRequest  $request
     * @return JsonResponse
     */
    public function store(CreateWakeupCallRequest $request)
    {
        try {
            // Extract validated data
            $validated = $request->validated();

            // Create a new WakeupCall entry
            $wakeupCall = WakeupCall::create([
                'domain_uuid' => session('domain_uuid'), // Ensure domain is set
                'extension_uuid' => $validated['extension'],
                'wake_up_time' => Carbon::parse($validated['wake_up_time'])->setTimezone('UTC'),
                'recurring' => $validated['recurring'] ?? false,
                'status' => $validated['status'],
                'next_attempt_at' => $validated['wake_up_time'], // Initially the same as wake_up_time
            ]);

            return response()->json([
                'success' => true,
                'messages' => ['success' => ['Wake-up call scheduled successfully']],
                'data' => $wakeupCall,
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to schedule wake-up call']]
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     *
     * @param  Destinations  $destinations
     * @return \Illuminate\Http\Response
     */
    public function show(Destinations $destinations)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  Destinations  $phone_number
     * @return JsonResponse
     */
    public function edit(Request $request, Destinations $phone_number)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateWakeupCallRequest  $request
     * @param  WakeupCall $wakeup_call
     * @return JsonResponse
     */
    public function update(UpdateWakeupCallRequest $request, WakeupCall $wakeup_call)
    {
        if (!$wakeup_call) {
            return response()->json([
                'success' => false,
                'errors' => ['model' => ['Wake-up call not found']]
            ], 404);
        }

        if (!$this->canAccessWakeupCall($wakeup_call)) {
            return response()->json([
                'success' => false,
                'errors' => ['authorization' => ['Access denied.']]
            ], 403);
        }

        try {
            // Extract validated data
            $validated = $request->validated();

            // Update fields
            $wakeup_call->wake_up_time = Carbon::parse($validated['wake_up_time'])->setTimezone('UTC');
            $wakeup_call->next_attempt_at = Carbon::parse($validated['wake_up_time'])->setTimezone('UTC');
            $wakeup_call->extension_uuid = $validated['extension'];
            $wakeup_call->recurring = $validated['recurring'];
            $wakeup_call->status = $validated['status'];

            if ($validated['status'] === 'completed') {
                $wakeup_call->next_attempt_at = null; // No next attempt needed
            }

            if ($validated['status'] === 'scheduled') {
                $wakeup_call->retry_count = 0; // resetting retry count
            }

            // Save the updates
            $wakeup_call->save();

            return response()->json([
                'success' => true,
                'messages' => ['success' => ['Wake-up call updated successfully']],
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update this item']]
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  WakeupCall  $wakeup_call
     * @return RedirectResponse
     */
    public function destroy(WakeupCall $wakeup_call)
    {
        try {
            if (!userCheckPermission('wakeup_calls_delete')) {
                return redirect()->back()->with('error', ['server' => ['Access denied.']]);
            }

            if (!$wakeup_call) {
                return response()->json([
                    'success' => false,
                    'errors' => ['message' => ['Wakeup call not found.']]
                ], 404);
            }

            if (!$this->canAccessWakeupCall($wakeup_call)) {
                return redirect()->back()->with('error', ['server' => ['Access denied.']]);
            }

            // Delete the record
            $wakeup_call->delete();

            return redirect()->back()->with('message', ['server' => ['Item deleted']]);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return redirect()->back()->with('error', ['server' => ['Server returned an error while deleting this item']]);
        }
    }


    /**
     * Remove the specified resource from storage.
     * @return JsonResponse
     */
    public function bulkDelete(): JsonResponse
    {
        try {
            if (!userCheckPermission('wakeup_calls_delete')) {
                return response()->json([
                    'success' => false,
                    'errors' => ['authorization' => ['Access denied.']]
                ], 403);
            }

            // Begin Transaction
            DB::beginTransaction();

            // Retrieve all items at once
            $items = $this->scopedWakeupCalls()
                ->whereIn('uuid', request('items', []))
                ->get(['uuid']);

            foreach ($items as $item) {
                // Delete the item itself
                $item->delete();
            }

            // Commit Transaction
            DB::commit();

            return response()->json([
                'messages' => ['server' => ['All selected items have been deleted successfully.']],
            ], 200);
        } catch (\Exception $e) {
            // Rollback Transaction if any error occurs
            DB::rollBack();

            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Server returned an error while deleting the selected items.']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateWakeupCallSettingsRequest  $request
     * @param  Destinations  $phone_number
     * @return JsonResponse
     */
    public function updateSettings(UpdateWakeupCallSettingsRequest $request)
    {
        try {
            if (!userCheckPermission('wakeup_calls_view_settings')) {
                return response()->json([
                    'success' => false,
                    'errors' => ['authorization' => ['Access denied.']]
                ], 403);
            }

            // Extract validated data
            $validated = $request->validated();
            $allowedList = $validated['allowed_list'] ?? [];
            $domain_uuid = $validated['domain_uuid'];

            // Start a transaction to ensure data integrity
            DB::beginTransaction();

            // Remove all existing allowed extensions for this domain
            WakeupAuthExt::where('domain_uuid', $domain_uuid)->delete();

            // Insert new allowed extensions if provided
            if (!empty($allowedList)) {
                foreach ($allowedList as $extension_uuid) {
                    WakeupAuthExt::create([
                        'domain_uuid'    => $domain_uuid,
                        'extension_uuid' => $extension_uuid,
                    ]);
                }
            }

            // Commit the transaction
            DB::commit();

            return response()->json([
                'success'  => true,
                'messages' => ['success' => ['Wake-up call settings updated successfully']],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            logger($e);
            return response()->json([
                'success' => false,
                'errors'  => ['server' => ['Failed to update this item']]
            ], 500);
        }
    }



    public function getUserPermissions()
    {
        return $this->permissions();
    }

    /**
     * @return JsonResponse
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function selectAll(): JsonResponse
    {

        // logger(request()->all());
        try {
            if (!$this->canViewRecords()) {
                return response()->json([
                    'success' => false,
                    'errors' => ['authorization' => ['Access denied.']]
                ], 403);
            }

            $query = $this->scopedWakeupCalls();

            // Apply domain filtering unless showGlobal is enabled
            if (filter_var(request('filter.showGlobal', false), FILTER_VALIDATE_BOOLEAN) && $this->canViewGlobalRecords()) {
                $domainUuids = Session::get('domains')->pluck('domain_uuid');
                $query->whereIn($this->model->getTable() . '.domain_uuid', $domainUuids);
            } else {
                $query->where('domain_uuid', session('domain_uuid'));
            }

            // Apply date range filter if provided
            if (!empty(request('filter.dateRange'))) {
                $startPeriod = Carbon::parse(request('filter.dateRange')[0])->setTimeZone('UTC');
                $endPeriod = Carbon::parse(request('filter.dateRange')[1])->setTimeZone('UTC');

                $query->whereBetween('wake_up_time', [$startPeriod, $endPeriod]);
            }

            if (filled(request('filter.search'))) {
                $needle = trim((string) request('filter.search'));

                $query->where(function ($query) use ($needle) {
                    $query->where('status', 'ilike', "%{$needle}%")
                        ->orWhereHas('extension', function ($query) use ($needle) {
                            $query->where('extension', 'ilike', "%{$needle}%")
                                ->orWhere('effective_caller_id_name', 'ilike', "%{$needle}%");
                        })
                        ->orWhereHas('domain', function ($query) use ($needle) {
                            $query->where('domain_name', 'ilike', "%{$needle}%")
                                ->orWhere('domain_description', 'ilike', "%{$needle}%");
                        });
                });
            }

            // Retrieve matching wake-up call UUIDs
            $uuids = $query->pluck($this->model->getKeyName());

            logger($uuids->count());
            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $uuids,
            ], 200);
        } catch (\Exception $e) {
            logger($e);
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    private function scopedWakeupCalls(): Builder
    {
        return $this->model::query()
            ->when(!$this->canViewAllRecords(), function ($query) {
                $query->where('extension_uuid', optional(auth()->user())->extension_uuid);
            });
    }

    private function canViewRecords(): bool
    {
        return userCheckPermission('wakeup_calls_list_view')
            && ($this->canViewAllRecords() || userCheckPermission('wakeup_calls_view_self_records'));
    }

    private function canViewAllRecords(): bool
    {
        return userCheckPermission('wakeup_calls_view_all_records')
            || userCheckPermission('wakeup_calls_all');
    }

    private function canViewGlobalRecords(): bool
    {
        return userCheckPermission('wakeup_calls_all');
    }

    private function canAccessWakeupCall(WakeupCall $wakeupCall): bool
    {
        if ($this->canViewGlobalRecords()) {
            $domainUuids = Session::get('domains')->pluck('domain_uuid');
            if (!$domainUuids->contains($wakeupCall->domain_uuid)) {
                return false;
            }
        } elseif ($wakeupCall->domain_uuid !== session('domain_uuid')) {
            return false;
        }

        if ($this->canViewAllRecords()) {
            return true;
        }

        return userCheckPermission('wakeup_calls_view_self_records')
            && $wakeupCall->extension_uuid === optional(auth()->user())->extension_uuid;
    }

    private function permissions(): array
    {
        return [
            'create' => userCheckPermission('wakeup_calls_create'),
            'update' => userCheckPermission('wakeup_calls_edit'),
            'destroy' => userCheckPermission('wakeup_calls_delete'),
            'view_all_records' => $this->canViewAllRecords(),
            'view_self_records' => userCheckPermission('wakeup_calls_view_self_records'),
            'view_global' => $this->canViewGlobalRecords(),
            'view_settings' => userCheckPermission('wakeup_calls_view_settings'),
        ];
    }
}
