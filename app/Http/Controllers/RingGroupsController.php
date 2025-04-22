<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\IvrMenus;
use App\Models\Dialplans;
use App\Models\Extensions;
use App\Models\Recordings;
use App\Models\RingGroups;
use App\Models\FusionCache;
use App\Models\MusicOnHold;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Models\RingGroupsDestinations;
use Illuminate\Support\Facades\Session;
use App\Services\CallRoutingOptionsService;
use Propaganistas\LaravelPhone\PhoneNumber;
use App\Http\Requests\StoreRingGroupRequest;
use App\Http\Requests\UpdateRingGroupRequest;

class RingGroupsController extends Controller
{

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'RingGroups';
    protected $searchable = ['ring_group_name', 'ring_group_extension'];

    public function __construct()
    {
        $this->model = new RingGroups();
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
        if (!userCheckPermission("ring_group_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },

                'routes' => [
                    'current_page' => route('ring-groups.index'),
                    'store' => route('ring-groups.store'),
                    // 'update' => route('ring-groups.update', ['id' => 'id_placeholder']),
                    'item_options' => route('ring-groups.item.options'),
                    'bulk_delete' => route('ring-groups.bulk.delete'),
                    'select_all' => route('ring-groups.select.all'),
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
        $data->with(['destinations' => function ($query) {
            $query->select('ring_group_destination_uuid', 'ring_group_uuid', 'destination_number');
        }]);

        $data->select(
            'ring_group_uuid',
            'ring_group_name',
            'ring_group_extension',
            'ring_group_enabled',
            'ring_group_description'
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

            $call_distributions = [
                [
                    'value' => 'enterprise',
                    'label' => 'Advanced',
                ],
                [
                    'value' => 'simultaneous',
                    'label' => 'Simultaneous Ring',
                ],
                [
                    'value' => 'sequence',
                    'label' => 'Sequential Ring',
                ],
                [
                    'value' => 'random',
                    'label' => 'Random Ring',
                ],
                [
                    'value' => 'rollover',
                    'label' => 'Rollover',
                ],

            ];

            $extensions = Extensions::where('domain_uuid', $domain_uuid)
                ->select('extension_uuid', 'extension', 'effective_caller_id_name')
                ->orderBy('extension', 'asc')
                ->get();


            $ringGroups = RingGroups::where('domain_uuid', $domain_uuid)
                ->select('ring_group_uuid', 'ring_group_extension', 'ring_group_name')
                ->orderBy('ring_group_extension', 'asc')
                ->get();

            $memberOptions = [
                [
                    'groupLabel' => 'Extensions',
                    'groupOptions' => $extensions->map(function ($extension) {
                        return [
                            'value' => $extension->extension_uuid,
                            'label' => $extension->name_formatted,
                            'destination' => $extension->extension,
                            'type' => 'extension',
                        ];
                    })->toArray(),
                ],
                [
                    'groupLabel' => 'Ring Groups',
                    'groupOptions' => $ringGroups->map(function ($group) {
                        return [
                            'value' => $group->ring_group_uuid,
                            'label' => $group->name_formatted,
                            'destination' => $group->ring_group_extension,
                            'type' => 'ring_group',
                        ];
                    })->toArray(),
                ]
            ];

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
                $updateRoute = route('ring-groups.update', ['ring_group' => $item_uuid]);
            } else {
                // Create a new model if item_uuid is not provided
                $item = $this->model;
            }

            $permissions = $this->getUserPermissions();

            $routingOptionsService = new CallRoutingOptionsService;
            $routingTypes = $routingOptionsService->routingTypes;
            $forwardingTypes = $routingOptionsService->forwardingTypes;

            $routes = [
                'update_route' => $updateRoute ?? null,
                'get_routing_options' => route('routing.options'),

            ];

            // Transform greetings into the desired array format
            $greetingsArray = Recordings::where('domain_uuid', session('domain_uuid'))
                ->orderBy('recording_name')
                ->get()
                ->map(function ($greeting) {
                    return [
                        'value' => $greeting->recording_filename,
                        'label' => $greeting->recording_name,
                        'description' => $greeting->recording_description,
                    ];
                })->toArray();


            $routes = array_merge($routes, [
                'text_to_speech_route' => route('greetings.textToSpeech'),
                'greeting_route' => route('greeting.url'),
                'delete_greeting_route' => route('greetings.file.delete'),
                'update_greeting_route' => route('greetings.file.update'),
                'upload_greeting_route' => route('greetings.file.upload'),
                // 'update_route' => route('virtual-receptionists.update', $ivr),
                'apply_greeting_route' => route('virtual-receptionist.greeting.apply'),

            ]);

            $openAiVoices = [
                ['value' => 'alloy', 'name' => 'Alloy'],
                ['value' => 'echo', 'name' => 'Echo'],
                ['value' => 'fable', 'name' => 'Fable'],
                ['value' => 'onyx', 'name' => 'Onyx'],
                ['value' => 'nova', 'name' => 'Nova'],
                ['value' => 'shimmer', 'name' => 'Shimmer'],
            ];

            $openAiSpeeds = [];

            for ($i = 0.85; $i <= 1.3; $i += 0.05) {
                if (floor($i) == $i) {
                    // Whole number, format with one decimal place
                    $formattedValue = sprintf('%.1f', $i);
                } else {
                    // Fractional number, format with two decimal places
                    $formattedValue = sprintf('%.2f', $i);
                }
                $openAiSpeeds[] = ['value' => $formattedValue, 'name' => $formattedValue];
            }



            // Define the instructions for recording a voicemail greeting using a phone call
            $phoneCallInstructions = [
                'Dial <strong>*732</strong> from your phone.',
                'Enter the ring group extension number when prompted and press <strong>#</strong>.',
                'Follow the prompts to record your greeting.',
            ];

            $sampleMessage = 'Thank you for contacting the Sales Department. Please hold the line; a representative will be with you shortly.';

            $ring_back_tones = getRingBackTonesCollectionGrouped(session('domain_uuid'));

            // Construct the itemOptions object
            $itemOptions = [
                'navigation' => $navigation,
                'ring_group' => $item,
                'member_options' => $memberOptions,
                'permissions' => $permissions,
                'call_distributions' => $call_distributions,
                'routes' => $routes,
                'routing_types' => $routingTypes,
                'forwarding_types' => $forwardingTypes,
                'voices' => $openAiVoices,
                'speeds' => $openAiSpeeds,
                'phone_call_instructions' => $phoneCallInstructions,
                'sample_message' => $sampleMessage,
                'greetings' => $greetingsArray,
                'ring_back_tones' => $ring_back_tones,
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
        $permissions['manage_cid_name_prefix'] = userCheckPermission('ring_group_cid_name_prefix');
        $permissions['manage_cid_number_prefix'] = userCheckPermission('ring_group_cid_number_prefix');
        $permissions['manage_cid_name'] = userCheckPermission('ring_group_caller_id_name');
        $permissions['manage_cid_number'] = userCheckPermission('ring_group_caller_id_number');
        $permissions['manage_context'] = userCheckPermission('ring_group_context');
        $permissions['destination_create'] = userCheckPermission('ring_group_destination_add');
        $permissions['destination_delete'] = userCheckPermission('ring_group_destination_delete');
        $permissions['destination_update'] = userCheckPermission('ring_group_destination_edit');
        $permissions['destination_view'] = userCheckPermission('ring_group_destination_view');
        $permissions['manage_forwarding'] = userCheckPermission('ring_group_forward');
        $permissions['manage_forwarding_toll_allow'] = userCheckPermission('ring_group_forward_toll_allow');
        $permissions['manage_settings'] = userCheckPermission('ring_group_view_settings');
        $permissions['manage_advanced'] = userCheckPermission('ring_group_view_advanced');
        $permissions['manage_missed_call'] = userCheckPermission('ring_group_missed_call');
        $permissions['manage_greeting'] = !userCheckPermission('ring_group_prompt');

        return $permissions;
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreRingGroupRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRingGroupRequest $request)
    {
        $attributes = $request->validated();

        if (isset($attributes['ring_group_forward'])) {
            if ($attributes['ring_group_forward']['all']['type'] == 'external') {
                $attributes['ring_group_forward_destination'] = (new PhoneNumber(
                    $attributes['ring_group_forward']['all']['target_external'],
                    "US"
                ))->formatE164();
            } else {
                $attributes['ring_group_forward_destination'] = ($attributes['ring_group_forward']['all']['target_internal'] == '0') ? '' : $attributes['ring_group_forward']['all']['target_internal'];;
                if (empty($attributes['ring_group_forward_destination'])) {
                    $attributes['ring_group_forward_enabled'] = 'false';
                }
            }
        }

        if (!isset($attributes['ring_group_missed_call_category'])) {
            $attributes['ring_group_missed_call_category'] = null;
        }

        if ($attributes['ring_group_ringback'] != '${us-ring}' and $attributes['ring_group_ringback'] != 'local_stream://default' and $attributes['ring_group_ringback'] != 'null') {
            $attributes['ring_group_ringback'] = getDefaultSetting('switch', 'recordings') . "/" . Session::get('domain_name') . "/" . $attributes['ring_group_ringback'];
        }

        $ringGroup = new RingGroups();
        $ringGroup->fill([
            'domain_uuid' => session('domain_uuid'),
            'ring_group_name' => $attributes['ring_group_name'],
            'ring_group_extension' => $attributes['ring_group_extension'],
            'ring_group_greeting' => $attributes['ring_group_greeting'] ?? null,
            'ring_group_timeout_app' => $attributes['timeout_category'] == 'disabled' ? null : ($attributes['timeout_category'] == 'recordings' ? 'lua' : 'transfer'),
            'ring_group_timeout_data' => $attributes['ring_group_timeout_data'],
            'ring_group_cid_name_prefix' => $attributes['ring_group_cid_name_prefix'] ?? null,
            'ring_group_cid_number_prefix' => $attributes['ring_group_cid_number_prefix'] ?? null,
            'ring_group_description' => $attributes['ring_group_description'],
            'ring_group_enabled' => $attributes['ring_group_enabled'],
            'ring_group_forward_enabled' => $attributes['ring_group_forward_enabled'] ?? 'false',
            'ring_group_forward_destination' => $attributes['ring_group_forward_destination'] ?? null,
            'ring_group_strategy' => $attributes['ring_group_strategy'],
            'ring_group_caller_id_name' => $attributes['ring_group_caller_id_name'] ?? null,
            'ring_group_caller_id_number' => $attributes['ring_group_caller_id_number'] ?? null,
            'ring_group_distinctive_ring' => $attributes['ring_group_distinctive_ring'],
            'ring_group_ringback' => ($attributes['ring_group_ringback'] == 'null') ? null : $attributes['ring_group_ringback'],
            'ring_group_call_forward_enabled' => $attributes['ring_group_call_forward_enabled'],
            'ring_group_follow_me_enabled' => $attributes['ring_group_follow_me_enabled'],
            'ring_group_missed_call_data' => $attributes['ring_group_missed_call_data'] ?? null,
            'ring_group_missed_call_app' => ($attributes['ring_group_missed_call_category'] == 'disabled') ? null : $attributes['ring_group_missed_call_category'],
            'ring_group_forward_toll_allow' => $attributes['ring_group_forward_toll_allow'] ?? null,
            'ring_group_context' => $attributes['ring_group_context'] ?? null,
            'dialplan_uuid' => Str::uuid(),
        ]);

        $ringGroup->save();

        $sumDestinationsTimeout = $longestDestinationsTimeout = 0;
        if (isset($attributes['ring_group_destinations']) && count($attributes['ring_group_destinations']) > 0) {
            $i = 0;
            $order = 5;
            $destinationsAdded = [];
            foreach ($attributes['ring_group_destinations'] as $destination) {
                if ($i > 49) {
                    break;
                }
                $groupsDestinations = new RingGroupsDestinations();
                if ($destination['type'] == 'external') {
                    $groupsDestinations->destination_number = format_phone_or_extension($destination['target_external']);
                } else {
                    $groupsDestinations->destination_number = $destination['target_internal'];
                }

                if (empty($groupsDestinations->destination_number) || in_array($groupsDestinations->destination_number, $destinationsAdded)) {
                    continue;
                }

                if ($ringGroup->ring_group_strategy == 'sequence' || $ringGroup->ring_group_strategy == 'rollover') {
                    $groupsDestinations->destination_delay = $order;
                    $order += 5;
                } else {
                    $groupsDestinations->destination_delay = $destination['delay'];
                }
                $groupsDestinations->destination_timeout = $destination['timeout'];
                if ($destination['status'] == 'true') {
                    $sumDestinationsTimeout += $destination['timeout'];
                }

                // Save the longest timeout
                if (($destination['timeout'] + $destination['delay']) > $longestDestinationsTimeout && $destination['status'] == 'true') {
                    $longestDestinationsTimeout = ($destination['timeout'] + $destination['delay']);
                }
                if ($destination['prompt'] == 'true') {
                    $groupsDestinations->destination_prompt = 1;
                } else {
                    $groupsDestinations->destination_prompt = null;
                }
                if ($destination['status'] == 'true') {
                    $groupsDestinations->destination_enabled = true;
                } else {
                    $groupsDestinations->destination_enabled = null;
                }
                //$groupsDestinations->follow_me_order = $i;
                $ringGroup->groupDestinations()->save($groupsDestinations);
                $destinationsAdded[] = $groupsDestinations->destination_number;
                $i++;
            }
        }

        $ringGroup->ring_group_call_timeout = match ($attributes['ring_group_strategy']) {
            'random', 'sequence', 'rollover' => $sumDestinationsTimeout,
            'simultaneous', 'enterprise' => $longestDestinationsTimeout,
            default => 0,
        };

        $ringGroup->save();

        $this->generateDialPlanXML($ringGroup);

        return response()->json([
            'status' => 'success',
            'redirect_url' => route('ring-groups.edit', $ringGroup),
            'ring_group' => $ringGroup,
            'message' => 'RingGroup has been created.'
        ]);
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
            $dialPlan->dialplan_description = $ringGroup->queue_description;
            $dialPlan->insert_date = date('Y-m-d H:i:s');
            $dialPlan->insert_user = Session::get('user_uuid');
        } else {
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_name = $ringGroup->ring_group_name;
            $dialPlan->dialplan_number = $ringGroup->ring_group_extension;
            $dialPlan->dialplan_description = $ringGroup->queue_description;
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


        logger($validated);


        try {
            DB::beginTransaction();

            $timeoutData = $this->buildExitDestinationAction($validated);
            $callTimeout = $this->calculateTimeout($validated);

            foreach (['ring_group_forward_enabled', 'ring_group_call_forward_enabled', 'ring_group_follow_me_enabled'] as $key) {
                if (array_key_exists($key, $validated)) {
                    $validated[$key] = $validated[$key] ? 'true' : 'false';
                }
            }

            $updateData = array_merge($validated, [
                'ring_group_call_timeout'       => $callTimeout,
                'ring_group_timeout_app'        => $timeoutData['action'],
                'ring_group_timeout_data'       => $timeoutData['data'],
                'ring_group_forward_destination' => !empty($validated['ring_group_forward_enabled'])
                    ? ($request->input('forward_action') === 'external'
                        ? $request->input('forward_external_target')
                        : $request->input('forward_target'))
                    : null,
            ]);

            // Only update missed call fields if they're present in the request
            if ($request->has('missed_call_notifications')) {
                $updateData['ring_group_missed_call_app'] = $request->boolean('missed_call_notifications') ? 'email' : null;
                $updateData['ring_group_missed_call_data'] = $request->boolean('missed_call_notifications')
                    ? $request->input('ring_group_missed_call_data')
                    : null;
            }

            // Delete old destinations and re-insert new ones
            if (!empty($validated['members']) && is_array($validated['members'])) {
                $ringGroup->destinations()->delete();

                foreach ($validated['members'] as $member) {
                    $ringGroup->destinations()->create([
                        'domain_uuid'         => $domain_uuid,
                        'destination_number'  => $member['destination'] ?? null,
                        'destination_delay'   => $member['delay'] ?? null,
                        'destination_timeout' => $member['timeout'] ?? null,
                        'destination_prompt'  => $member['prompt'] ?? null,
                        'destination_enabled' => !empty($member['enabled']) ? 'true' : 'false',
                    ]);
                }
            }

            DB::commit();

            $this->generateDialPlanXML($ringGroup);

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
     * @param  RingGroups  $ringGroup
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(RingGroups $ringGroup)
    {
        if (!userCheckPermission('ring_group_delete')) {
            return redirect('/');
        }

        $deleted = $ringGroup->delete();
        $dialPlan = Dialplans::where('dialplan_uuid', $ringGroup->dialplan_uuid)->first();
        $dialPlan->delete();

        $fp = event_socket_create(
            config('eventsocket.ip'),
            config('eventsocket.port'),
            config('eventsocket.password')
        );

        event_socket_request($fp, 'bgapi reloadxml');

        //clear fusionpbx cache
        FusionCache::clear("dialplan:" . $ringGroup->ring_group_context);

        if ($deleted) {
            return response()->json([
                'status' => 200,
                'success' => [
                    'message' => 'Selected Ring Groups have been deleted'
                ]
            ]);
        } else {
            return response()->json([
                'status' => 401,
                'errors' => [
                    'message' => "There was an error deleting this Ring Group",
                ],
            ]);
        }
    }

    private function getDestinationExtensions()
    {
        $extensions = Extensions::where('domain_uuid', Session::get('domain_uuid'))
            //->whereNotIn('extension_uuid', [$extension->extension_uuid])
            ->orderBy('extension')
            ->get();
        $ivrMenus = IvrMenus::where('domain_uuid', Session::get('domain_uuid'))
            //->whereNotIn('extension_uuid', [$extension->extension_uuid])
            ->orderBy('ivr_menu_extension')
            ->get();
        $ringGroups = RingGroups::where('domain_uuid', Session::get('domain_uuid'))
            //->whereNotIn('extension_uuid', [$extension->extension_uuid])
            ->orderBy('ring_group_extension')
            ->get();

        /* NOTE: disabling voicemails as a call forward destination
         * $voicemails = Voicemails::where('domain_uuid', Session::get('domain_uuid'))
            //->whereNotIn('extension_uuid', [$extension->extension_uuid])
            ->orderBy('voicemail_id')
            ->get();*/
        return [
            'Extensions' => $extensions,
            'Ivr Menus' => $ivrMenus,
            'Ring Groups' => $ringGroups,
            //'Voicemails' => $voicemails
        ];
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
