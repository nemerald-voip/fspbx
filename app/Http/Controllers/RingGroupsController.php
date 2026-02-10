<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Dialplans;
use App\Models\Extensions;
use App\Models\Recordings;
use App\Models\RingGroups;
use App\Models\FusionCache;
use Illuminate\Support\Str;
use App\Traits\ChecksLimits;
use Illuminate\Http\Request;
use App\Services\RingGroupService;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use App\Services\FreeswitchEslService;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Support\Facades\Session;
use App\Services\CallRoutingOptionsService;
use App\Http\Requests\StoreRingGroupRequest;
use App\Http\Requests\UpdateRingGroupRequest;

class RingGroupsController extends Controller
{
    use ChecksLimits;

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'RingGroups';

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
                'routes' => [
                    'current_page' => route('ring-groups.index'),
                    'data_route' => route('ring-groups.data'),
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
    public function getData()
    {
        $perPage = 50;
        $currentDomain = session('domain_uuid');

        $items = QueryBuilder::for(RingGroups::class)
            // only voicemails in the current domain
            ->where('domain_uuid', $currentDomain)
            ->select(
                'ring_group_uuid',
                'ring_group_name',
                'ring_group_extension',
                'ring_group_enabled',
                'ring_group_description',
                'ring_group_forward_enabled',
            )
            ->with([
                'destinations' => function ($q) use ($currentDomain) {
                    $q->select([
                        'ring_group_destination_uuid',
                        'ring_group_uuid',
                        'destination_number',
                        'destination_enabled',
                    ])
                        ->orderBy('destination_delay', 'asc')
                        ->orderBy('destination_number', 'asc')
                        ->with([
                            // this will now be domain-safe because of whereColumn in the relationship
                            'extension' => function ($q2) use ($currentDomain) {
                                $q2->select([
                                    'extension_uuid',
                                    'domain_uuid',
                                    'extension',
                                    'effective_caller_id_name',
                                ])
                                    ->where('domain_uuid', $currentDomain);
                            }
                        ]);
                }
            ])
            // ->withCount('messages')
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value)  use ($currentDomain) {
                    $query->where(function ($q) use ($value, $currentDomain) {
                        $q->where('ring_group_name', 'ilike', "%{$value}%")
                            ->orWhere('ring_group_extension', 'ilike', "%{$value}%")
                            // Search related extenion
                            ->orWhereHas('destinations', function ($q2) use ($value) {
                                $q2->where('destination_number', $value);
                            });
                        // Add more fields if needed
                    });
                }),
            ])
            ->allowedSorts(['ring_group_extension'])
            ->defaultSort('ring_group_extension')
            ->paginate($perPage);

        // logger($items);

        return $items;
    }

    public function getItemOptions()
    {
        try {

            $domain_uuid = request('domain_uuid') ?? session('domain_uuid');
            $item_uuid = request('item_uuid'); // Retrieve item_uuid from the request

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

            $extensionsQuery = Extensions::where('v_extensions.domain_uuid', $domain_uuid)
                ->leftJoin('extension_advanced_settings', 'v_extensions.extension_uuid', '=', 'extension_advanced_settings.extension_uuid')
                ->select(
                    'v_extensions.extension_uuid',
                    'v_extensions.extension',
                    'v_extensions.effective_caller_id_name',
                    'extension_advanced_settings.suspended'
                )
                ->orderBy('v_extensions.extension', 'asc');
            //

            // IMPORTANT — THIS MUST STILL EXIST
            $ringGroupsQuery = RingGroups::where('domain_uuid', $domain_uuid)
                ->select('ring_group_uuid', 'ring_group_extension', 'ring_group_name')
                ->orderBy('ring_group_extension', 'asc');


            if (!empty($item_uuid)) {
                // Exclude the ring group currently being edited
                $ringGroupsQuery->where('ring_group_uuid', '!=', $item_uuid);
            }

            $extensions = $extensionsQuery->get();
            $ringGroups = $ringGroupsQuery->get();

            $memberOptions = [
                [
                    'groupLabel' => 'Extensions',
                    'groupOptions' => $extensions->map(function ($extension) {
                        return [
                            'value' => $extension->extension_uuid,
                            'label' => $extension->name_formatted,
                            'destination' => $extension->extension,
                            'type' => 'extension',
                            'suspended' => $extension->suspended === 'true',
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
                        $query
                            ->leftJoin('v_extensions', function ($join) {
                                $join->on('v_ring_group_destinations.destination_number', '=', 'v_extensions.extension')
                                    ->on('v_ring_group_destinations.domain_uuid', '=', 'v_extensions.domain_uuid');
                            })
                            ->leftJoin('extension_advanced_settings', 'v_extensions.extension_uuid', '=', 'extension_advanced_settings.extension_uuid')
                            ->select(
                                'v_ring_group_destinations.ring_group_destination_uuid',
                                'v_ring_group_destinations.ring_group_uuid',
                                'v_ring_group_destinations.domain_uuid',
                                'v_ring_group_destinations.destination_delay',
                                'v_ring_group_destinations.destination_enabled',
                                'v_ring_group_destinations.destination_number',
                                'v_ring_group_destinations.destination_prompt',
                                'v_ring_group_destinations.destination_timeout',
                                DB::raw("CASE 
                            WHEN extension_advanced_settings.suspended = 'true' THEN true
                            ELSE false
                        END AS suspended")
                            )
                            // same deterministic order here
                            ->orderBy('v_ring_group_destinations.destination_delay', 'asc')
                            ->orderBy('v_ring_group_destinations.ring_group_destination_uuid', 'asc');
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

                // Check for limits
                if ($resp = $this->enforceLimit(
                    'ring_groups',
                    \App\Models\RingGroups::class,
                    'domain_uuid',
                    'ring_group_limit_error'
                )) {
                    return $resp;
                }


                // Create a new model if item_uuid is not provided
                $item = $this->model;
                $item->ring_group_extension = $item->generateUniqueSequenceNumber();

                $storeRoute  = route('ring-groups.store');
            }

            $permissions = $this->getUserPermissions();

            $routingOptionsService = new CallRoutingOptionsService;
            $routingTypes = $routingOptionsService->routingTypes;
            $forwardingTypes = $routingOptionsService->forwardingTypes;

            $routes = [
                'store_route' => $storeRoute ?? null,
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


            // Define the instructions for recording a voicemail greeting using a phone call
            $phoneCallInstructions = [
                'Dial <strong>*732</strong> from your phone.',
                'Enter the ring group extension number when prompted and press <strong>#</strong>.',
                'Follow the prompts to record your greeting.',
            ];

            $sampleMessage = 'Thank you for contacting the Sales Department. Please hold the line; a representative will be with you shortly.';

            $ring_back_tones = getRingBackTonesCollectionGrouped(session('domain_uuid'));

            $openAiService = app(\App\Services\OpenAIService::class);

            // Construct the itemOptions object
            $itemOptions = [
                'ring_group' => $item,
                'member_options' => $memberOptions,
                'permissions' => $permissions,
                'call_distributions' => $call_distributions,
                'routes' => $routes,
                'routing_types' => $routingTypes,
                'forwarding_types' => $forwardingTypes,
                'voices' => $openAiService->getVoices(),
                'default_voice' => isset($openAiService) && $openAiService ? $openAiService->getDefaultVoice() : null,
                'speeds' => $openAiService->getSpeeds(),
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
        $permissions['manage_greeting'] = userCheckPermission('ring_group_prompt');
        $permissions['is_superadmin'] = isSuperAdmin();

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
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $ringGroup = RingGroups::create(array_merge($validated, [
                'domain_uuid' => session('domain_uuid'),
                'ring_group_context' => session('domain_name'),
                'ring_group_enabled' => 'true',
                'ring_group_strategy' => 'enterprise',
                'ring_group_ringback' => '${us-ring}',
                'ring_group_call_forward_enabled' => get_domain_setting('honor_member_cfwd'),
                'ring_group_follow_me_enabled' => get_domain_setting('honor_member_followme'),
                'dialplan_uuid' => Str::uuid(),
            ]));

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Ring group saved']],
                'ring_group_uuid' => $ringGroup->ring_group_uuid,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('RingGroup update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while saving.']]
            ], 500);
        }
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
        $domain_name = session('domain_name');

        try {
            DB::transaction(function () use ($validated, $domain_uuid, $domain_name, $ringGroup) {

                // Build model update payload (includes derived fields)
                $updateData = app(RingGroupService::class)->buildUpdateData($validated, $domain_name);

                $ringGroup->update($updateData);

                /**
                 * Sync destinations only if the client sent "members"
                 * - members omitted => no change
                 * - members: [] => delete all
                 * - members: [...] => replace
                 */
                if (array_key_exists('members', $validated)) {
                    $ringGroup->destinations()->delete();

                    $members = is_array($validated['members'] ?? null) ? $validated['members'] : [];

                    // For sequence ring, encode drag/drop order as 0,5,10,...
                    if (($validated['ring_group_strategy'] ?? null) === 'sequence') {
                        foreach ($members as $i => &$m) {
                            $m['destination_delay'] = (string) ($i * 5);
                        }
                        unset($m);
                    }

                    $now = now();

                    $rows = [];
                    foreach ($members as $member) {
                        $rows[] = [
                            'ring_group_destination_uuid' => (string) Str::uuid(),
                            'domain_uuid'                 => $domain_uuid,
                            'ring_group_uuid'             => $ringGroup->ring_group_uuid,
                            'destination_number'          => $member['destination_number'] ?? null,
                            'destination_delay'           => isset($member['destination_delay']) ? (float) $member['destination_delay'] : 0,
                            'destination_timeout'         => isset($member['destination_timeout']) ? (float) $member['destination_timeout'] : 0,
                            'destination_enabled'         => !empty($member['destination_enabled']),
                            'destination_prompt'          => !empty($member['destination_prompt']) ? 1 : null,
                            'update_date'                 => $now,
                        ];
                    }

                    if (!empty($rows)) {
                        $ringGroup->destinations()->insert($rows);
                    }
                }

            });

            return response()->json([
                'messages' => ['success' => ['Ring group updated']]
            ]);
        } catch (\Throwable $e) {
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
            }

            // Reload XML from FreeSWITCH
            $freeSwitchService = new FreeswitchEslService();
            $command = 'bgapi reloadxml';
            $result = $freeSwitchService->executeCommand($command);

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
     * Duplicate the specified Ring Group.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function duplicate(Request $request)
    {
        // 1. Validate Input
        $request->validate([
            'uuid' => 'required|uuid|exists:v_ring_groups,ring_group_uuid',
        ]);

        // 2. Permission Check
        if (!userCheckPermission('ring_group_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        try {
            DB::beginTransaction();

            // 3. Fetch Original with destinations
            $original = $this->model::where('ring_group_uuid', $request->uuid)
                ->where('domain_uuid', session('domain_uuid'))
                ->with(['destinations'])
                ->firstOrFail();

            // 4. Replicate Parent
            $newRingGroup = $original->replicate();
            $newRingGroup->ring_group_uuid = Str::uuid();
            $newRingGroup->ring_group_name = $original->ring_group_name . ' (Copy)';
            $newRingGroup->dialplan_uuid = Str::uuid();

            // Generate unique extension (Increment based on settings)
            $newRingGroup->ring_group_extension = $this->model->generateUniqueSequenceNumber();

            $newRingGroup->save();

            // 5. Replicate Destinations
            foreach ($original->destinations as $destination) {
                $newDestination = $destination->replicate();
                $newDestination->ring_group_destination_uuid = Str::uuid();
                $newDestination->ring_group_uuid = $newRingGroup->ring_group_uuid;
                $newDestination->save();
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Ring Group duplicated successfully', 'New Extension: ' . $newRingGroup->ring_group_extension]],
                'ring_group_uuid' => $newRingGroup->ring_group_uuid
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to duplicate ring group.']]
            ], 500);
        }
    }
}
