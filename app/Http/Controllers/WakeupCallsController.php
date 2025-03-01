<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Faxes;
use App\Models\Dialplans;
use App\Models\WakeupCall;
use Illuminate\Support\Str;
use App\Models\Destinations;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Builder;
use App\Services\CallRoutingOptionsService;
use App\Http\Requests\StorePhoneNumberRequest;
use App\Http\Requests\UpdatePhoneNumberRequest;
use App\Http\Requests\BulkUpdatePhoneNumberRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WakeupCallsController extends Controller
{
    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'WakeupCalls';
    protected $searchable = ['status',];

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
        // if (!userCheckPermission("")) {
        //     return redirect('/');
        // }

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
                    return $this->getTimezone();
                },
                'routes' => [
                    'current_page' => route('wakeup-calls.index'),
                    'store' => route('wakeup-calls.store'),
                    // 'select_all' => route('wakeup-calls.select.all'),
                    // 'bulk_update' => route('wakeup-calls.bulk.update'),
                    // 'bulk_delete' => route('wakeup-calls.bulk.delete'),
                    // 'item_options' => route('wakeup-calls.item.options'),
                    //'bulk_delete' => route('messages.settings.bulk.delete'),
                    //'bulk_update' => route('devices.bulk.update'),
                ],
                // 'conditions' => [
                //     [
                //         'name' => 'Caller ID Number',
                //         'value' => 'caller_id_number'
                //     ]
                // ],
                // 'domain' => Session::get('domain_uuid')
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
                [
                    'name' => 'Advanced',
                    'icon' => 'AdjustmentsHorizontalIcon',
                    'slug' => 'advanced',
                ],
            ];

            $routingOptionsService = new CallRoutingOptionsService;
            $routingTypes = $routingOptionsService->routingTypes;

            $faxes = [];
            $faxesCollection = Faxes::query();
            $faxesCollection->where('domain_uuid', Session::get('domain_uuid'));
            $faxesCollection = $faxesCollection->orderBy('fax_name')->get([
                'fax_extension',
                'fax_name',
                'fax_uuid'
            ]);
            foreach ($faxesCollection as $fax) {
                $faxes[] = [
                    'name' => $fax->fax_extension . ' ' . $fax->fax_name,
                    'value' => $fax->fax_uuid
                ];
            }

            $domains = [];
            $domainsCollection = Session::get("domains");
            if ($domainsCollection) {
                foreach ($domainsCollection as $domain) {
                    $domains[] = [
                        'value' => $domain->domain_uuid,
                        'name' => $domain->domain_description
                    ];
                }
            }

            // Check if item_uuid exists to find an existing voicemail
            if ($item_uuid) {
                // Find existing item by item_uuid
                $phoneNumber = $this->model::where($this->model->getKeyName(), $item_uuid)->first();

                // logger($phoneNumber);

                // If a voicemail exists, use it; otherwise, create a new one
                if (!$phoneNumber) {
                    throw new \Exception("Failed to fetch item details. Item not found");
                }

                // Define the update route
                $updateRoute = route('phone-numbers.update', ['phone_number' => $item_uuid]);
            } else {
                // Create a new voicemail if item_uuid is not provided
                $phoneNumber = $this->model;
            }

            $permissions = $this->getUserPermissions();

            $routes = [
                'update_route' => $updateRoute ?? null,
                'get_routing_options' => route('routing.options'),

            ];


            // Construct the itemOptions object
            $itemOptions = [
                'navigation' => $navigation,
                'phone_number' => $phoneNumber,
                'permissions' => $permissions,
                'routes' => $routes,
                'routing_types' => $routingTypes,
                'faxes' => $faxes,
                'domains' => $domains,
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

    /**
     * @return LengthAwarePaginator
     */
    public function getData($paginate = 50): LengthAwarePaginator
    {
        if (!empty(request('filterData.dateRange'))) {
            $startPeriod = Carbon::parse(request('filterData.dateRange')[0])->setTimeZone('UTC');
            $endPeriod = Carbon::parse(request('filterData.dateRange')[1])->setTimeZone('UTC');
        } else {
            $startPeriod = Carbon::now($this->getTimezone())->startOfDay()->setTimeZone('UTC');
            $endPeriod = Carbon::now($this->getTimezone())->endOfDay()->setTimeZone('UTC');
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
        } else {
            $data = $data->get(); // This will return a collection
        }

        logger($data);

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
                $query->orWhere($field, 'ilike', '%' . $value . '%');
            }
        });
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
     * @param  \App\Http\Requests\StorePhoneNumberRequest  $request
     * @return JsonResponse
     */
    public function store(StorePhoneNumberRequest $request): JsonResponse
    {
        try {
            $inputs = array_map(function ($value) {
                return $value === 'NULL' ? null : $value;
            }, $request->validated());

            // Process routing_options to form destination_actions
            $destination_actions = [];
            if (!empty($inputs['routing_options'])) {
                foreach ($inputs['routing_options'] as $option) {
                    $destination_actions[] = $this->buildDestinationAction($option);
                }
            }

            // Assign the formatted actions to the destination_actions field
            $inputs['destination_actions'] = json_encode($destination_actions);

            $instance = $this->model;
            $instance->fill([
                'domain_uuid' => $inputs['domain_uuid'],
                'dialplan_uuid' => Str::uuid(),
                'fax_uuid' => $inputs['fax_uuid'] ?? null,
                'destination_type' => 'inbound',
                'destination_prefix' => $inputs['destination_prefix'],
                'destination_number' => $inputs['destination_number'],
                'destination_actions' => $inputs['destination_actions'],
                // 'destination_conditions' => $inputs['destination_conditions'],
                'destination_hold_music' => $inputs['destination_hold_music'] ?? null,
                'destination_description' => $inputs['destination_description'] ?? null,
                'destination_enabled' => $inputs['destination_enabled'] ?? true,
                'destination_record' => $inputs['destination_record'] ?? false,
                'destination_type_fax' => $inputs['destination_type_fax'] ?? false,
                'destination_cid_name_prefix' => $inputs['destination_cid_name_prefix'] ?? null,
                'destination_accountcode' => $inputs['destination_accountcode'] ?? null,
                'destination_distinctive_ring' => $inputs['destination_distinctive_ring'] ?? null,
                'destination_context' => $inputs['destination_context'] ?? 'public',
            ]);
            $instance->save();

            $this->generateDialPlanXML($instance);

            return response()->json([
                'messages' => ['success' => ['New item created']]
            ], 201);
        } catch (\Exception $e) {
            // Log the error message
            logger($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to create new item'], 'ss' => $e->getMessage()]
            ], 500);  // 500 Internal Server Error for any other errors
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
     * Bulk update requested items
     *
     * @param  BulkUpdatePhoneNumberRequest  $request
     * @return JsonResponse
     */
    public function bulkUpdate(BulkUpdatePhoneNumberRequest  $request): JsonResponse
    {
        // $request->items has items IDs that need to be updated
        // $request->validated has the update data

        try {
            // Prepare the data for updating
            $inputs = collect($request->validated())
                ->filter(function ($value) {
                    return $value !== null;
                })->toArray();

            $inputs = $this->processActionConditionInputs($inputs);

            if ($inputs['destination_actions'] == null) {
                unset($inputs['destination_actions']);
            }

            if ($inputs['destination_conditions'] == null) {
                unset($inputs['destination_conditions']);
            }

            //var_dump($inputs);

            /*if (isset($inputs['device_template'])) {
                $inputs['device_vendor'] = explode("/", $inputs['device_template'])[0];
                if ($inputs['device_vendor'] === 'poly') {
                    $inputs['device_vendor'] = 'polycom';
                }
            }

            if (isset($inputs['extension'])) {
                $extension = $inputs['extension'];
                unset($inputs['extension']);
            } else {
                $extension = null;
            }*/

            if (sizeof($inputs) > 0) {
                $updated = $this->model::whereIn($this->model->getKeyName(), request()->items)
                    ->update($inputs);
            }

            /*if ($extension) {
                // First, we are deleting all existing device lines
                $this->deleteDeviceLines(request('items'));

                // Create new lines
                $this->createDeviceLines(request('items'), $extension);
            }*/

            return response()->json([
                'messages' => ['success' => ['Selected items updated']],
            ], 200);
        } catch (\Exception $e) {
            logger($e);
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update selected items']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UpdatePhoneNumberRequest  $request
     * @param  Destinations  $phone_number
     * @return JsonResponse
     */
    public function update(UpdatePhoneNumberRequest $request, Destinations $phone_number)
    {
        if (!$phone_number) {
            // If the model is not found, return an error response
            return response()->json([
                'success' => false,
                'errors' => ['model' => ['Model not found']]
            ], 404); // 404 Not Found if the model does not exist
        }

        try {
            $inputs = array_map(function ($value) {
                return $value === 'NULL' ? null : $value;
            }, $request->validated());

            // logger($inputs);

            // Process routing_options to form destination_actions
            $destination_actions = [];
            if (!empty($inputs['routing_options'])) {
                foreach ($inputs['routing_options'] as $option) {
                    $destination_actions[] = $this->buildDestinationAction($option);
                }
            }

            // Assign the formatted actions to the destination_actions field
            $inputs['destination_actions'] = json_encode($destination_actions);

            $phone_number->update($inputs);

            $this->generateDialPlanXML($phone_number);
        } catch (\Exception $e) {
            logger($e);
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update this item']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  Destinations  $phone_number
     * @return RedirectResponse
     */
    public function destroy(Destinations $phoneNumber)
    {
        try {
            //Get dialplan details
            $dialPlan = Dialplans::where('dialplan_uuid', $phoneNumber->dialplan_uuid)->first();

            // Delete dialplan
            if ($dialPlan) {
                $dialPlan->delete();
            }

            // Delete Phone Number
            $phoneNumber->delete();

            //clear fusionpbx cache
            $this->clearCache($phoneNumber);

            return redirect()->back()->with('message', ['server' => ['Item deleted']]);
        } catch (\Exception $e) {
            // Log the error message
            logger($e);
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
            $items = $this->model::whereIn('destination_uuid', request('items'))
                ->get(['destination_uuid']);

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
            logger($e);
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Server returned an error while deleting the selected items.']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }


    public function getUserPermissions()
    {
        $permissions = [];
        return $permissions;
    }

    protected function getTimezone()
    {
        $domainUuid = session('domain_uuid');
        $cacheKey = "{$domainUuid}_timeZone";
    
        return Cache::remember($cacheKey, 600, function () use ($domainUuid) {
            return get_local_time_zone($domainUuid);
        });
    }

    /**
     * @return JsonResponse
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function selectAll(): JsonResponse
    {
        try {
            if (request()->get('showGlobal')) {
                $uuids = $this->model::get($this->model->getKeyName())->pluck($this->model->getKeyName());
            } else {
                $uuids = $this->model::where('domain_uuid', session('domain_uuid'))
                    ->get($this->model->getKeyName())->pluck($this->model->getKeyName());
            }

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
