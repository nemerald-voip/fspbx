<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\IvrMenus;
use App\Models\Dialplans;
use App\Models\Extensions;
use App\Models\Recordings;
use App\Models\RingGroups;
use App\Models\Voicemails;
use App\Models\FusionCache;
use Illuminate\Support\Str;
use App\Models\BusinessHour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Services\CallRoutingOptionsService;
use App\Http\Requests\StoreBusinessHoursRequest;
use App\Http\Requests\UpdateBusinessHoursRequest;
use App\Models\CallCenterQueues;
use App\Models\CallFlows;
use App\Models\Faxes;

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

            $domain_uuid = request('domain_uuid') ?? session('domain_uuid');
            $item_uuid = request('item_uuid'); // Retrieve item_uuid from the request


            // Check if item_uuid exists to find an existing model
            if ($item_uuid) {
                // Find existing item by item_uuid
                $item = $this->model::where($this->model->getKeyName(), $item_uuid)
                    ->with(['periods' => function ($query) {
                        $query->select('business_hour_uuid', 'day_of_week', 'start_time', 'end_time', 'action', 'target_type', 'target_id');
                    }])
                    ->first();

                // logger($item);

                // If a model exists, use it; otherwise, create a new one
                if (!$item) {
                    throw new \Exception("Failed to fetch item details. Item not found");
                }

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
                            ->map(fn($dow) =>(string) $dow)
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

            ];

            // Construct the itemOptions object
            $itemOptions = [
                'item' => $item,
                'custom_hours' => $item->periods->isNotEmpty(),
                'time_slots' => $timeSlots,
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
        // $permissions['manage_cid_name_prefix'] = userCheckPermission('ring_group_cid_name_prefix');


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
        $validated = $request->validated();
        logger($validated);

        try {
            DB::beginTransaction();

            // 1) Create the BusinessHour record
            $businessHour = BusinessHour::create([
                'uuid'         => Str::uuid(),
                'domain_uuid'  => session('domain_uuid'),
                'name'         => $validated['name'],
                'extension'    => $validated['extension'],
                'timezone'     => $validated['timezone'],
                'context' => session('domain_name'),
                'enabled' => true,
            ]);

            // 2) Persist each period
            // foreach ($validated['time_slots'] as $slot) {
            //     $start = Carbon::createFromFormat('h:i a', $slot['time_from'])->format('H:i:s');
            //     $end   = Carbon::createFromFormat('h:i a', $slot['time_to'])->format('H:i:s');

            //     foreach ($slot['weekdays'] as $wd) {
            //         $dow = intval($wd) === 0 ? 7 : intval($wd); // map 0→7 if you're using 1=Mon…7=Sun

            //         $businessHour->periods()->create([
            //             'day_of_week' => $dow,
            //             'start_time'  => $start,
            //             'end_time'    => $end,
            //         ]);
            //     }
            // }

            // 3) Generate the FreeSWITCH dialplan XML
            // $xml = $this->buildDialplanXml($businessHour);


            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Business hours created and dialplan written.']],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger(
                'BusinessHours store error: '
                    . $e->getMessage()
                    . " at " . $e->getFile()
                    . ":" . $e->getLine()
            );

            return response()->json([
                'messages' => ['error' => ['Something went wrong while saving business hours.']]
            ], 500);
        }
    }

    /**
     * Generate (or update) the FreeSWITCH dialplan for a BusinessHour.
     */
    public function generateDialPlanXML(BusinessHour $businessHour): void
    {
        // Data to pass to the Blade template
        $data = [
            'businessHour' => $businessHour,
        ];

        // Render the Blade template and get the XML content as a string
        $xml = view('layouts.xml.business-hours-dial-plan-template', $data)->render();

        // Find existing dialplan by UUID or instantiate a new one
        $dialPlan = Dialplans::where('dialplan_uuid', $businessHour->dialplan_uuid)
            ->first();

        if (! $dialPlan) {
            $dialPlan = new Dialplans();
            $dialPlan->dialplan_uuid      = $businessHour->dialplan_uuid;
            $dialPlan->app_uuid           = 'YOUR-BUSINESS-HOURS-APP-UUID';
            $dialPlan->domain_uuid        = Session::get('domain_uuid');
            $dialPlan->dialplan_name      = $businessHour->name;
            $dialPlan->dialplan_number    = $businessHour->extension;
            // if you have a specific context field on BusinessHour, use it; otherwise default:
            $dialPlan->dialplan_context   = $businessHour->context ?? Session::get('domain_uuid');
            $dialPlan->dialplan_continue  = 'false';
            $dialPlan->dialplan_order     = 200;
            $dialPlan->insert_date        = now()->format('Y-m-d H:i:s');
            $dialPlan->insert_user        = Session::get('user_uuid');
        }

        // Common fields (for both create & update)
        $dialPlan->dialplan_xml         = $xml;
        $dialPlan->dialplan_enabled     = $businessHour->custom_hours ? 'true' : 'false';
        $dialPlan->dialplan_description = 'Business Hours: ' . $businessHour->name;
        $dialPlan->update_date          = now()->format('Y-m-d H:i:s');
        $dialPlan->update_user          = Session::get('user_uuid');

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

        logger($validated);

        try {

            DB::beginTransaction();

            $businessHour->update([
                'name'         => $validated['name'],
                'extension'    => $validated['extension'],
                'timezone'     => $validated['timezone'],
            ]);


            // 2) delete old periods
            $businessHour->periods()->delete();

            $callRoutingService = new CallRoutingOptionsService;

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


            DB::commit();

            //clear fusionpbx cache
            // FusionCache::clear("dialplan:" . $ringGroup->ring_group_context);

            return response()->json([
                'messages' => ['success' => ['Business hours updated']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('RingGroup update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while updating.']]
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        if (!userCheckPermission('ring_group_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        try {
            DB::beginTransaction();

            $domain_uuid = session('domain_uuid');
            $uuids = $request->input('items');

            $ringGroups = RingGroups::where('domain_uuid', $domain_uuid)
                ->whereIn('ring_group_uuid', $uuids)
                ->get();

            foreach ($ringGroups as $ringGroup) {
                // Delete destinations
                $ringGroup->destinations()->delete();

                // Delete dialplan (if exists)
                if ($ringGroup->dialplan_uuid) {
                    Dialplans::where('dialplan_uuid', $ringGroup->dialplan_uuid)->delete();
                }

                // Delete ring group itself
                $ringGroup->delete();

                // Clear FusionPBX cache per ring group
                FusionCache::clear("dialplan:" . $ringGroup->ring_group_context);
            }

            // Reload XML from FreeSWITCH
            $fp = event_socket_create(
                config('eventsocket.ip'),
                config('eventsocket.port'),
                config('eventsocket.password')
            );
            event_socket_request($fp, 'bgapi reloadxml');

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected ring group(s) were deleted successfully.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('RingGroups bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected ring group(s).']]
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

    /**
     * Helper function to build destination action based on exit action.
     */
    protected function buildForwardDestinationTarget($inputs)
    {
        switch ($inputs['forward_action']) {
            case 'extensions':
            case 'ring_groups':
            case 'ivrs':
            case 'time_conditions':
            case 'contact_centers':
            case 'faxes':
            case 'call_flows':
                return  $inputs['forward_target'];
            case 'voicemails':
                return '*99' . $inputs['forward_target'];
                // Add other cases as necessary for different types
            case 'external':
                return $inputs['forward_external_target'];
            default:
                return null;
        }
    }


    protected function calculateTimeout(array $validated): int
    {
        $enabledMembers = array_filter($validated['members'] ?? [], fn($m) => $m['enabled']);

        if (in_array($validated['ring_group_strategy'] ?? '', ['random', 'sequence', 'rollover'])) {
            return array_reduce($enabledMembers, fn($carry, $m) => $carry + (int) $m['timeout'], 0);
        }

        return collect($enabledMembers)
            ->map(fn($m) => (int) $m['delay'] + (int) $m['timeout'])
            ->max() ?? 0;
    }
}
