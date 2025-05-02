<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Dialplans;
use App\Models\Extensions;
use App\Models\Recordings;
use App\Models\RingGroups;
use App\Models\FusionCache;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Services\CallRoutingOptionsService;
use App\Http\Requests\StoreBusinessHoursRequest;
use App\Http\Requests\UpdateRingGroupRequest;
use App\Models\BusinessHour;

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
        $this->sortField = request()->get('sortField', 'ring_group_extension');
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
                    ->with(['destinations' => function ($query) {
                        $query->select('ring_group_destination_uuid', 'ring_group_uuid', 'destination_delay', 'destination_enabled', 'destination_number', 'destination_prompt', 'destination_timeout');
                    }])
                    ->first();

                // If a model exists, use it; otherwise, create a new one
                if (!$item) {
                    throw new \Exception("Failed to fetch item details. Item not found");
                }

                $item->append([
                    'timeout_target_uuid',
                    'timeout_action',
                    'timeout_action_display',
                    'timeout_target_name',
                    'timeout_target_extension',
                    'destroy_route',
                    'forward_target_uuid',
                    'forward_action',
                    'forward_action_display',
                    'forward_target_name',
                    'forward_target_extension',
                ]);

                // Define the update route
                $updateRoute = route('business-hours.update', ['business_hours' => $item_uuid]);
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
                'permissions' => $permissions,
                'routes' => $routes,
                'routing_types' => $routingTypes,
                'timezones' => getGroupedTimezones(),
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

        return;

        try {
            DB::beginTransaction();




            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Ring group updated']],
 
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('RingGroup update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while updating.']]
            ], 500);
        }
    }

    public function generateDialPlanXML($ringGroup): void
    {
        // Data to pass to the Blade template
        $data = [
            'ring_group' => $ringGroup,
        ];

        // Render the Blade template and get the XML content as a string
        $xml = view('layouts.xml.ring-group-dial-plan-template', $data)->render();

        $dialPlan = Dialplans::where('dialplan_uuid', $ringGroup->dialplan_uuid)->first();

        if (!$dialPlan) {
            $dialPlan = new Dialplans();
            $dialPlan->dialplan_uuid = $ringGroup->dialplan_uuid;
            $dialPlan->app_uuid = '1d61fb65-1eec-bc73-a6ee-a6203b4fe6f2';
            $dialPlan->domain_uuid = Session::get('domain_uuid');
            $dialPlan->dialplan_name = $ringGroup->ring_group_name;
            $dialPlan->dialplan_number = $ringGroup->ring_group_extension;
            if (isset($ringGroup->ring_group_context)) {
                $dialPlan->dialplan_context = $ringGroup->ring_group_context;
            }
            $dialPlan->dialplan_continue = 'false';
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_order = 101;
            $dialPlan->dialplan_enabled = $ringGroup->ring_group_enabled;
            $dialPlan->dialplan_description = $ringGroup->ring_group_description;
            $dialPlan->insert_date = date('Y-m-d H:i:s');
            $dialPlan->insert_user = Session::get('user_uuid');
        } else {
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_name = $ringGroup->ring_group_name;
            $dialPlan->dialplan_number = $ringGroup->ring_group_extension;
            $dialPlan->dialplan_description = $ringGroup->ring_group_description;
            $dialPlan->update_date = date('Y-m-d H:i:s');
            $dialPlan->update_user = Session::get('user_uuid');
        }

        $dialPlan->save();

        $fp = event_socket_create(
            config('eventsocket.ip'),
            config('eventsocket.port'),
            config('eventsocket.password')
        );
        event_socket_request($fp, 'bgapi reloadxml');

        //clear fusionpbx cache
        FusionCache::clear("dialplan:" . $ringGroup->ring_group_context);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateRingGroupRequest  $request
     * @param  RingGroups  $ringGroup
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update(UpdateRingGroupRequest $request, RingGroups $ringGroup)
    {
        if (!userCheckPermission('ring_group_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        $validated = $request->validated();
        $domain_uuid = session('domain_uuid');

        try {
            DB::beginTransaction();

            foreach (['ring_group_forward_enabled', 'ring_group_call_forward_enabled', 'ring_group_follow_me_enabled'] as $key) {
                if (array_key_exists($key, $validated)) {
                    $validated[$key] = $validated[$key] ? 'true' : 'false';
                }
            }

            $callTimeout = $this->calculateTimeout($validated);
            $updateData = array_merge($validated, [
                'ring_group_call_timeout' => $callTimeout,
            ]);


            // Add timeout app/data only if failback info exists
            if ($request->has('failback_action') && $request->has('failback_target')) {
                $timeoutData = $this->buildExitDestinationAction($validated);
                $updateData['ring_group_timeout_app'] = $timeoutData['action'];
                $updateData['ring_group_timeout_data'] = $timeoutData['data'];
            }

            // Add forward destination only if all parts are present
            if (
                !empty($validated['ring_group_forward_enabled'])
                && $request->has('forward_action')
                && ($request->has('forward_target') || $request->has('forward_external_target'))
            ) {
                $updateData['ring_group_forward_destination'] = $this->buildForwardDestinationTarget($validated);
            }

            if (!empty($failbackData)) {
                $updateData['ring_group_timeout_app'] = $failbackData['action'];
                $updateData['ring_group_timeout_data'] = $failbackData['data'];
            }

            // Only update missed call fields if they're present in the request
            if ($request->has('missed_call_notifications')) {
                $updateData['ring_group_missed_call_app'] = $request->boolean('missed_call_notifications') ? 'email' : null;
                $updateData['ring_group_missed_call_data'] = $request->boolean('missed_call_notifications')
                    ? $request->input('ring_group_missed_call_data')
                    : null;
            }

            $ringGroup->update($updateData);

            // Delete old destinations and re-insert new ones
            if (!empty($validated['members']) && is_array($validated['members'])) {
                $ringGroup->destinations()->delete();

                foreach ($validated['members'] as $member) {
                    $ringGroup->destinations()->create([
                        'domain_uuid'         => $domain_uuid,
                        'destination_number'  => $member['destination'] ?? null,
                        'destination_delay'   => $member['delay'] ?? null,
                        'destination_timeout' => $member['timeout'] ?? null,
                        'destination_prompt'  => $member['prompt'] ? 1: null,
                        'destination_enabled' => !empty($member['enabled']) ? 'true' : 'false',
                    ]);
                }
            }

            $this->generateDialPlanXML($ringGroup);

            DB::commit();

            //clear fusionpbx cache
            FusionCache::clear("dialplan:" . $ringGroup->ring_group_context);

            return response()->json([
                'messages' => ['success' => ['Ring group updated']]
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
