<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Dialplans;
use App\Models\RingGroups;
use App\Models\FusionCache;
use Illuminate\Support\Str;
use App\Models\BusinessHour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Services\CallRoutingOptionsService;
use App\Http\Requests\StoreBusinessHoursRequest;
use App\Http\Requests\UpdateBusinessHoursRequest;


class BusinessHoursController extends Controller
{

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'BusinessHours';
    protected $searchable = ['name', 'extension', 'description'];

    public function __construct()
    {
        $this->model = new BusinessHour();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request)
    {
        // Check permissions
        if (!userCheckPermission("business_hours_list_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },

                'routes' => [
                    'current_page' => route('business-hours.index'),
                    'item_options' => route('business-hours.item.options'),
                    'bulk_delete' => route('business-hours.bulk.delete'),
                    'select_all' => route('business-hours.select.all'),
                    'duplicate_business_hours' => route('business-hours.duplicate'),

                ]
            ]
        );
    }

    /**
     *  Get data
     */
    public function getData($paginate = 50)
    {

        // Check if search parameter is present and not empty
        if (!empty(request('filterData.search'))) {
            $this->filters['search'] = request('filterData.search');
        }

        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'extension');
        $this->sortOrder = request()->get('sortOrder', 'asc');

        $data = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $data = $data->paginate($paginate);
        } else {
            $data = $data->get(); // This will return a collection
        }

        // logger($data);

        return $data;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [])
    {
        $data =  $this->model::query();
        $domainUuid = session('domain_uuid');
        $data = $data->where($this->model->getTable() . '.domain_uuid', $domainUuid);
        // $data->with(['destinations' => function ($query) {
        //     $query->select('ring_group_destination_uuid', 'ring_group_uuid', 'destination_number');
        // }]);

        $data->select(
            'uuid',
            'name',
            'extension',
            'description',
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

    public function getItemOptions()
    {
        try {
            $item_uuid = request('item_uuid'); // Retrieve item_uuid from the request


            // Check if item_uuid exists to find an existing model
            if ($item_uuid) {
                // Find existing item by item_uuid
                $item = $this->model::where($this->model->getKeyName(), $item_uuid)
                    ->with(['periods' => function ($query) {
                        $query->select('business_hour_uuid', 'day_of_week', 'start_time', 'end_time', 'action', 'target_type', 'target_id');
                    }])
                    // ->with(['exceptions' => function ($query) {
                    //     $query->select('business_hour_uuid', 'start_date', 'start_time', 'end_date', 'start_time', 'end_time', 'mon', 'wday', 'mweek', 'mday', 'action', 'target_type', 'target_id');
                    // }])
                    ->first();

                // If a model exists, use it; otherwise, create a new one
                if (!$item) {
                    throw new \Exception("Failed to fetch item details. Item not found");
                }

                $item->after_hours_target = [
                    'value'     => $item->after_hours_target_id,
                ];

                $timeSlots = $item->periods
                    // group by start|end|action|target_type|target_id
                    ->groupBy(function ($p) {
                        return implode('|', [
                            $p->start_time,
                            $p->end_time,
                            $p->action,
                            $p->target_type,
                            $p->target_id,
                        ]);
                    })
                    ->map(function ($group) {
                        /** @var \Illuminate\Support\Collection $group */
                        $first = $group->first();

                        // restore weekdays 1–7 → 0–6
                        $weekdays = $group
                            ->pluck('day_of_week')
                            ->map(fn($dow) => (string) $dow)
                            ->sort()
                            ->values()
                            ->all();

                        // format back to “h:i a”
                        $timeFrom = Carbon::createFromFormat('H:i:s', $first->start_time)
                            ->format('h:i a');
                        $timeTo   = Carbon::createFromFormat('H:i:s', $first->end_time)
                            ->format('h:i a');

                        // rebuild the target object
                        $targetModel = $first->target;
                        $target = null;
                        if ($targetModel) {
                            $target = [
                                'value'     => $first->target_id,
                            ];
                        }

                        return [
                            'weekdays'  => $weekdays,
                            'time_from' => $timeFrom,
                            'time_to'   => $timeTo,
                            'action'    => $first->action,
                            'target'    => $target,
                        ];
                    })
                    ->values()
                    ->all();

                // logger($timeSlots);

                // Define the update route
                $updateRoute = route('business-hours.update', ['business_hour' => $item_uuid]);
            } else {
                // Create a new model if item_uuid is not provided
                $item = $this->model;
                $item->extension = $item->generateUniqueSequenceNumber();

                $storeRoute  = route('business-hours.store');
            }

            $permissions = $this->getUserPermissions();

            $routingOptionsService = new CallRoutingOptionsService;
            $routingTypes = $routingOptionsService->routingTypes;

            $routes = [
                'store_route' => $storeRoute ?? null,
                'update_route' => $updateRoute ?? null,
                'get_routing_options' => route('routing.options'),
                'holidays' => route('holiday-hours.index'),
                'holiday_item_options' => route('holiday-hours.item.options'),
                'holiday_bulk_delete' => route('holiday-hours.bulk.delete'),
            ];

            // Construct the itemOptions object
            $itemOptions = [
                'item' => $item,
                'custom_hours' => $item->periods->isNotEmpty(),
                'time_slots' => $timeSlots ?? [],
                'permissions' => $permissions,
                'routes' => $routes,
                'routing_types' => $routingTypes,
                'timezones' => getGroupedTimezones(),
                'timezone' => $item->timezone ?? get_local_time_zone(),
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

    public function getUserPermissions()
    {
        $permissions = [];
        $permissions['holidays_list_view'] = userCheckPermission('business_hours_holidays_list_view');
        $permissions['holidays_create'] = userCheckPermission('business_hours_holidays_create');
        $permissions['holidays_update'] = userCheckPermission('business_hours_holidays_update');
        $permissions['holidays_delete'] = userCheckPermission('business_hours_holidays_delete');
        $permissions['is_superadmin'] = isSuperAdmin();

        return $permissions;
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreBusinessHoursRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreBusinessHoursRequest $request)
    {
        if (!userCheckPermission('business_hours_create')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        $validated = $request->validated();
        $domainUuid = session('domain_uuid');

        try {
            DB::beginTransaction();

            // 1) Create the BusinessHour record
            $businessHour = BusinessHour::create([
                'uuid'                    => Str::uuid(),
                'domain_uuid'             => $domainUuid,
                'context'                 => session('domain_name'),
                'name'                    => $validated['name'],
                'extension'               => $validated['extension'],
                'timezone'                => $validated['timezone'],
                'description'             => $validated['description'] ?? null,
                'enabled'                 => true,
                // after-hours defaults
                'after_hours_action'      => $validated['after_hours_action'] ?? null,
                'after_hours_target_type' => $validated['after_hours_action']
                    ? (new CallRoutingOptionsService)
                    ->mapActionToModel($validated['after_hours_action'])
                    : null,
                'after_hours_target_id'   => $validated['after_hours_target']['value'] ?? null,
            ]);

            // 2) Persist each period (time slot)
            $callRoutingService = new CallRoutingOptionsService;
            foreach ($validated['time_slots'] ?? [] as $slot) {
                $start = Carbon::createFromFormat('h:i a', $slot['time_from'])->format('H:i:s');
                $end   = Carbon::createFromFormat('h:i a', $slot['time_to'])->format('H:i:s');

                foreach ($slot['weekdays'] as $wd) {
                    $dow = intval($wd) === 0 ? 7 : intval($wd); // 1=Mon…7=Sun

                    $periodData = [
                        'day_of_week' => $dow,
                        'start_time'  => $start,
                        'end_time'    => $end,
                        'action'      => $slot['action'],
                    ];

                    // attach polymorphic target if applicable
                    if (
                        isset($slot['target']['value']) &&
                        $callRoutingService->mapActionToModel($slot['action'])
                    ) {
                        $periodData['target_type'] = $callRoutingService->mapActionToModel($slot['action']);
                        $periodData['target_id']   = $slot['target']['value'];
                    }

                    $businessHour->periods()->create($periodData);
                }
            }

            // 3) Generate and write the FreeSWITCH dialplan XML
            $xml = $this->generateDialPlanXML($businessHour);

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Business hours created.']],
                'business_hours_uuid' =>  $businessHour->uuid,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger(
                'BusinessHours store error: '
                    . $e->getMessage()
                    . ' at ' . $e->getFile() . ':' . $e->getLine()
            );

            return response()->json([
                'messages' => ['error' => ['Something went wrong while saving business hours.']],
            ], 500);
        }
    }


    /**
     * Generate (or update) the FreeSWITCH dialplan for a BusinessHour.
     */
    public function generateDialPlanXML(BusinessHour $businessHour): void
    {
        // logger($businessHour);
        // Data to pass to the Blade template
        $data = [
            'businessHour' => $businessHour,
        ];

        // Render the Blade template and get the XML content as a string
        $xml = view('layouts.xml.business-hours-dial-plan-template', $data)->render();

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;  // Removes extra spaces
        $dom->loadXML($xml);
        $dom->formatOutput = true;         // Formats XML properly
        $xml = $dom->saveXML($dom->documentElement);

        // 1) if we don’t yet have a dialplan_uuid, make one and persist it
        if (! $businessHour->dialplan_uuid) {
            $businessHour->dialplan_uuid = (string) Str::uuid();
            $businessHour->saveQuietly(); // avoid touching update_date again
        }

        // logger($businessHour);

        $dialPlan = Dialplans::where('dialplan_uuid', $businessHour->dialplan_uuid)->first();

        if (!$dialPlan) {
            $dialPlan = new Dialplans();
            $dialPlan->dialplan_uuid = $businessHour->dialplan_uuid;
            $dialPlan->app_uuid = '4b821450-926b-175a-af93-a03c441818b2';
            $dialPlan->domain_uuid = session('domain_uuid');
            $dialPlan->dialplan_name = $businessHour->name;
            $dialPlan->dialplan_number = $businessHour->extension;
            if (isset($businessHour->context)) {
                $dialPlan->dialplan_context = $businessHour->context;
            }
            $dialPlan->dialplan_continue = 'false';
            $dialPlan->dialplan_destination = 'false';
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_order = 300;
            $dialPlan->dialplan_enabled = $businessHour->enabled;
            $dialPlan->dialplan_description = $businessHour->description;
            $dialPlan->insert_date = date('Y-m-d H:i:s');
            $dialPlan->insert_user = Session::get('user_uuid');
        } else {
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_name = $businessHour->name;
            $dialPlan->dialplan_number = $businessHour->extension;
            $dialPlan->dialplan_description = $businessHour->description;
            $dialPlan->update_date = date('Y-m-d H:i:s');
            $dialPlan->update_user = Session::get('user_uuid');
        }

        $dialPlan->save();

        // reload XML in FreeSWITCH
        $fp = event_socket_create(
            config('eventsocket.ip'),
            config('eventsocket.port'),
            config('eventsocket.password')
        );
        event_socket_request($fp, 'bgapi reloadxml');

        // clear FS PBX cache for this context
        FusionCache::clear('dialplan:' . $dialPlan->dialplan_context);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateBusinessHoursRequest  $request
     * @param  RingGroups  $ringGroup
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update(UpdateBusinessHoursRequest $request, BusinessHour $businessHour)
    {
        if (!userCheckPermission('business_hours_update')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        $validated = $request->validated();
        $domain_uuid = session('domain_uuid');

        // logger($validated);

        try {

            DB::beginTransaction();

            $callRoutingService = new CallRoutingOptionsService;

            $action = $validated['after_hours_action'] ?? null;
            $targetId = $validated['after_hours_target']['value'] ?? null;

            $businessHour->update([
                'name'         => $validated['name'],
                'extension'    => $validated['extension'],
                'timezone'     => $validated['timezone'],
                'description'     => $validated['description'] ?? null,
                'after_hours_action'     => $validated['after_hours_action'] ?? null,
                'after_hours_target_type'   => $action ? $callRoutingService->mapActionToModel($action) : null,
                'after_hours_target_id'     => $targetId,
            ]);

            // 2) delete old periods
            $businessHour->periods()->delete();

            // 3) recreate periods with action + polymorphic target
            foreach ($validated['time_slots'] ?? [] as $slot) {
                // parse times into "H:i:s"
                $start = Carbon::createFromFormat('h:i a', $slot['time_from'])
                    ->format('H:i:s');
                $end = Carbon::createFromFormat('h:i a', $slot['time_to'])
                    ->format('H:i:s');

                foreach ($slot['weekdays'] as $wd) {
                    $dow = intval($wd) === 0 ? 7 : intval($wd); // 1=Mon…7=Sun

                    $periodData = [
                        'day_of_week' => $dow,
                        'start_time'  => $start,
                        'end_time'    => $end,
                        'action'      => $slot['action'],
                    ];

                    // if target.value is set and we have a mapping, store it
                    if (
                        isset($slot['target']['value']) &&
                        $callRoutingService->mapActionToModel($slot['action'])
                    ) {
                        $periodData['target_type'] = $callRoutingService->mapActionToModel($slot['action']);
                        $periodData['target_id']   = $slot['target']['value'];
                    }

                    $businessHour->periods()->create($periodData);
                }
            }

            $xml = $this->generateDialPlanXML($businessHour);

            DB::commit();

            //clear fusionpbx cache
            // FusionCache::clear("dialplan:" . $ringGroup->ring_group_context);

            return response()->json([
                'messages' => ['success' => ['Business hours updated']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('Business Hours update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while updating.']]
            ], 500);
        }
    }


    /**
     * Bulk-delete selected business hours.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        // 1) Permission check
        if (! userCheckPermission('business_hours_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        try {
            DB::beginTransaction();

            $domainUuid = session('domain_uuid');
            $uuids      = $request->input('items', []);

            // 2) Fetch the BusinessHour models
            $businessHours = BusinessHour::where('domain_uuid', $domainUuid)
                ->whereIn('uuid', $uuids)
                ->get();

            foreach ($businessHours as $bh) {
                // 3) Delete related periods & holidays
                $bh->periods()->delete();
                $bh->holidays()->delete();

                // 4) Delete its dialplan record
                if ($bh->dialplan_uuid) {
                    Dialplans::where('dialplan_uuid', $bh->dialplan_uuid)->delete();
                }

                // 5) Delete the BusinessHour itself
                $bh->delete();

                // 6) Clear cached dialplan for this context
                FusionCache::clear('dialplan:' . $bh->context);
            }

            // 7) Tell FreeSWITCH to reload XML
            $fp = event_socket_create(
                config('eventsocket.ip'),
                config('eventsocket.port'),
                config('eventsocket.password')
            );
            event_socket_request($fp, 'bgapi reloadxml');

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected business hour(s) deleted successfully.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger(
                'BusinessHours bulkDelete error: '
                    . $e->getMessage()
                    . ' at ' . $e->getFile() . ':' . $e->getLine()
            );

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting business hours.']]
            ], 500);
        }
    }



    /**
     * Helper function to build destination action based on exit action.
     */
    protected function buildExitDestinationAction($inputs)
    {
        switch ($inputs['failback_action']) {
            case 'extensions':
            case 'ring_groups':
            case 'ivrs':
            case 'business_hours':
            case 'time_conditions':
            case 'contact_centers':
            case 'faxes':
            case 'call_flows':
                return  ['action' => 'transfer', 'data' => $inputs['failback_target'] . ' XML ' . session('domain_name')];
            case 'voicemails':
                return ['action' => 'transfer', 'data' => '*99' . $inputs['failback_target'] . ' XML ' . session('domain_name')];

            case 'recordings':
                // Handle recordings with 'lua' destination app
                return ['action' => 'lua', 'data' => 'streamfile.lua ' . $inputs['failback_target']];

            case 'check_voicemail':
                return ['action' => 'transfer', 'data' => '*98 XML ' . session('domain_name')];

            case 'company_directory':
                return ['action' => 'transfer', 'data' => '*411 XML ' . session('domain_name')];

            case 'hangup':
                return ['action' => 'hangup', 'data' => ''];

                // Add other cases as necessary for different types
            default:
                return [];
        }
    }


    public function selectAll()
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

    /**
     * Duplicate the specified Business Hour.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function duplicate(Request $request)
    {
        // 1. Validate Input
        $request->validate([
            'uuid' => 'required|uuid|exists:business_hours,uuid',
        ]);

        // 2. Permission Check
        if (!userCheckPermission('business_hours_create')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        try {
            DB::beginTransaction();

            // 4. Fetch Original with relationships
            $original = $this->model::where('uuid', $request->uuid)
                ->where('domain_uuid', session('domain_uuid'))
                ->with(['periods', 'holidays'])
                ->firstOrFail();

            // 5. Replicate Parent
            $newBusinessHour = $original->replicate();
            $newBusinessHour->uuid = Str::uuid();
            $newBusinessHour->name = $original->name . ' (Copy)';

            // Generate unique extension
            $newBusinessHour->extension = $this->model->generateUniqueSequenceNumber();

            // Reset Dialplan UUID so generateDialPlanXML creates a new one
            $newBusinessHour->dialplan_uuid = null;

            $newBusinessHour->save();

            // 6. Replicate Periods (Time Slots)
            foreach ($original->periods as $period) {
                $newPeriod = $period->replicate();
                $newPeriod->uuid = Str::uuid();
                $newPeriod->business_hour_uuid = $newBusinessHour->uuid;
                $newPeriod->save();
            }

            // 7. Replicate Holidays (if any exist)
            foreach ($original->holidays as $holiday) {
                $newHoliday = $holiday->replicate();
                $newHoliday->uuid = Str::uuid(); 
                $newHoliday->business_hour_uuid = $newBusinessHour->uuid;
                $newHoliday->save();
            }

            // 8. Generate Dialplan XML
            $this->generateDialPlanXML($newBusinessHour);

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Business Hours duplicated successfully', 'New Extension: ' . $newBusinessHour->extension]],
                'business_hour_uuid' => $newBusinessHour->uuid
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to duplicate business hours.']]
            ], 500);
        }
    }
}
