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

class WakeupCallsController extends Controller
{
    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'WakeupCalls';
    protected $searchable = ['status', 'extension.extension', 'extension.effective_caller_id_name'];

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
        if (!userCheckPermission("wakeup_calls_list_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },
                'startPeriod' => function () {
                    return $this->filters['startPeriod'];
                },
                'endPeriod' => function () {
                    return $this->filters['endPeriod'];
                },
                'timezone' => function () {
                    return get_local_time_zone(session('domain_uuid'));
                },
                'routes' => [
                    'current_page' => route('wakeup-calls.index'),
                    'store' => route('wakeup-calls.store'),
                    'select_all' => route('wakeup-calls.select.all'),
                    'bulk_delete' => route('wakeup-calls.bulk.delete'),
                    'item_options' => route('wakeup-calls.item.options'),
                    'settings' => route('wakeup-calls.settings'),
                ],

            ]
        );
    }


    public function getItemOptions()
    {
        try {

            $domain_uuid = request('domain_uuid') ?? session('domain_uuid');
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
                $wakeup_call = $this->model::where($this->model->getKeyName(), $item_uuid)->first();

                // If a model exists, use it; otherwise, create a new one
                if (!$wakeup_call) {
                    throw new \Exception("Failed to fetch item details. Item not found");
                }

                // Define the update route
                $updateRoute = route('wakeup-calls.update', ['wakeup_call' => $item_uuid]);
            } else {
                // Create a new model if item_uuid is not provided
                $wakeup_call = $this->model;
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
                'timezone' => get_local_time_zone($wakeup_call->domain_uuid)
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
        if (!empty(request('filterData.dateRange'))) {
            $startPeriod = Carbon::parse(request('filterData.dateRange')[0])->setTimeZone('UTC');
            $endPeriod = Carbon::parse(request('filterData.dateRange')[1])->setTimeZone('UTC');
        } else {
            $domain_uuid = session('domain_uuid');
            $startPeriod = Carbon::now(get_local_time_zone($domain_uuid))->startOfMonth()->setTimeZone('UTC');
            $endPeriod   = Carbon::now(get_local_time_zone($domain_uuid))->endOfMonth()->setTimeZone('UTC');            
        }

        $this->filters = [
            'startPeriod' => $startPeriod,
            'endPeriod' => $endPeriod,
            'search' => request('filterData.search') ?? null,
        ];

        // Check if showGlobal parameter is present and not empty
        if (!empty(request('filterData.showGlobal'))) {
            $this->filters['showGlobal'] = request('filterData.showGlobal') === 'true';
        } else {
            $this->filters['showGlobal'] = null;
        }

        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'wake_up_time');
        $this->sortOrder = request()->get('sortOrder', 'asc');

        $data = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $data = $data->paginate($paginate);
            $data->getCollection()->transform(function ($wakeUpCall) {
                return $wakeUpCall->append(['wake_up_time_formatted', 'next_attempt_at_formatted', 'destroy_route']);
            });
        } else {
            $data = $data->get();
        }

        // logger($data);

        return $data;
    }


    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = []): Builder
    {
        $data =  $this->model::query();

        if (isset($filters['showGlobal']) and $filters['showGlobal']) {
            $data->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description'); // Specify the fields you need
            }]);
            // Access domains through the session and filter devices by those domains
            $domainUuids = Session::get('domains')->pluck('domain_uuid');
            $data->whereHas('domain', function ($query) use ($domainUuids) {
                $query->whereIn($this->model->getTable() . '.domain_uuid', $domainUuids);
            });
        } else {
            // Directly filter devices by the session's domain_uuid
            $domainUuid = session('domain_uuid');
            $data = $data->where($this->model->getTable() . '.domain_uuid', $domainUuid);
        }

        $data->with(['extension' => function ($query) {
            $query->select('extension_uuid', 'extension', 'effective_caller_id_name');
        }]);

        $data->select(
            'uuid',
            'domain_uuid',
            'extension_uuid',
            'wake_up_time',
            'next_attempt_at',
            'recurring',
            'status',
            'retry_count',
        );


        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    $this->$method($data, $value);
                }
            }
        }

        // Apply sorting
        $data->orderBy($this->sortField, $this->sortOrder);

        return $data;
    }

    /**
     * @param $query
     * @param $value
     * @return void
     */
    protected function filterSearch($query, $value)
    {
        $searchable = $this->searchable;

        // Case-insensitive partial string search in the specified fields
        $query->where(function ($query) use ($value, $searchable) {
            foreach ($searchable as $field) {
                if (strpos($field, '.') !== false) {
                    // Nested field (e.g., 'extension.name_formatted')
                    [$relation, $nestedField] = explode('.', $field, 2);

                    $query->orWhereHas($relation, function ($query) use ($nestedField, $value) {
                        $query->where($nestedField, 'ilike', '%' . $value . '%');
                    });
                } else {
                    // Direct field
                    $query->orWhere($field, 'ilike', '%' . $value . '%');
                }
            }
        });
    }

    protected function filterStartPeriod($query, $value)
    {
        $query->where('wake_up_time', '>=', $value->toIso8601String());
    }

    protected function filterEndPeriod($query, $value)
    {
        $query->where('wake_up_time', '<=', $value->toIso8601String());
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
            if (!$wakeup_call) {
                return response()->json([
                    'success' => false,
                    'errors' => ['message' => ['Wakeup call not found.']]
                ], 404);
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
            // Begin Transaction
            DB::beginTransaction();

            // Retrieve all items at once
            $items = $this->model::whereIn('uuid', request('items'))
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
        $permissions = [];
        return $permissions;
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
            $query = $this->model::query();

            // Apply domain filtering unless showGlobal is enabled
            if (!request()->get('showGlobal')) {
                $query->where('domain_uuid', session('domain_uuid'));
            }

            // Apply date range filter if provided
            if (!empty(request('dateRange'))) {
                $startPeriod = Carbon::parse(request('dateRange')[0])->setTimeZone('UTC');
                $endPeriod = Carbon::parse(request('dateRange')[1])->setTimeZone('UTC');

                $query->whereBetween('wake_up_time', [$startPeriod, $endPeriod]);
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
}
