<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\IvrMenus;
use App\Models\Dialplans;
use App\Models\Recordings;
use App\Models\FusionCache;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\IvrMenuOptions;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\CallRoutingOptionsService;
use App\Http\Requests\StoreVirtualReceptionistRequest;
use App\Http\Requests\UpdateVirtualReceptionistRequest;
use App\Http\Requests\CreateVirtualReceptionistKeyRequest;
use App\Http\Requests\UpdateVirtualReceptionistKeyRequest;
use App\Traits\ChecksLimits;

class VirtualReceptionistController extends Controller
{
    use ChecksLimits;

    public $model;
    protected $viewName = 'VirtualReceptionists';

    public function __construct()
    {
        $this->model = new IvrMenus();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        // Check permissions
        if (!userCheckPermission("ivr_menu_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'routes' => [
                    'current_page' => route('virtual-receptionists.index'),
                    'data_route' => route('virtual-receptionists.data'),
                    'store' => route('virtual-receptionists.store'),
                    'item_options' => route('virtual-receptionists.item.options'),
                    'select_all' => route('virtual-receptionists.select.all'),
                    'bulk_delete' => route('virtual-receptionists.bulk.delete'),
                    'duplicate_virtual_receptionist' => route('virtual-receptionists.duplicate'),
                ],
                'permissions' => $this->getUserPermissions(),
            ]
        );
    }

    /**
     *  Get data via Spatie Query Builder
     */
    public function getData()
    {
        $perPage = 50;
        $currentDomain = session('domain_uuid');

        $items = QueryBuilder::for(IvrMenus::class)
            // only items in the current domain
            ->where('domain_uuid', $currentDomain)
            ->select([
                'ivr_menu_uuid',
                'ivr_menu_name',
                'ivr_menu_extension',
                'ivr_menu_enabled',
                'ivr_menu_description',
            ])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('ivr_menu_name', 'ilike', "%{$value}%")
                          ->orWhere('ivr_menu_extension', 'ilike', "%{$value}%")
                          ->orWhere('ivr_menu_description', 'ilike', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('ivr_menu_enabled'), // Allows filtering like ?filter[ivr_menu_enabled]=true
            ])
            ->allowedSorts(['ivr_menu_extension', 'ivr_menu_name'])
            ->defaultSort('ivr_menu_extension')
            ->paginate($perPage);

        return $items;
    }

    public function store(StoreVirtualReceptionistRequest $request)
    {
        $inputs = $request->validated();

        try {
            $instance = $this->model;

            $instance->fill([
                'domain_uuid' => session('domain_uuid'),
                'dialplan_uuid' => Str::uuid(),
                'ivr_menu_name' => $inputs['ivr_menu_name'],
                'ivr_menu_description' => $inputs['ivr_menu_description'] ?? null,
                'ivr_menu_extension' => $inputs['ivr_menu_extension'],
                'ivr_menu_enabled' => $inputs['ivr_menu_enabled'],
                'ivr_menu_digit_len' => $inputs['digit_length'],
                'ivr_menu_timeout' => $inputs['prompt_timeout'],
                'ivr_menu_pin_number' => $inputs['pin'] ?? null,
                'ivr_menu_ringback' => $inputs['ring_back_tone'],
                'ivr_menu_invalid_sound' => $inputs['invalid_input_message'],
                'ivr_menu_exit_sound' => $inputs['exit_message'],
                'ivr_menu_direct_dial' => $inputs['direct_dial'],
                'ivr_menu_context' => session('domain_name'),
                'ivr_menu_max_failures' => '3',
                'ivr_menu_max_timeouts' => '3',
                'ivr_menu_cid_prefix' => $inputs['caller_id_prefix'] ?? '',
            ]);

            $instance->save();

            $this->generateDialPlanXML($instance);

            if (isset($_SESSION['destinations']['array'])) {
                unset($_SESSION['destinations']['array']);
            }

            return response()->json([
                'item_uuid' => $instance->ivr_menu_uuid,
                'messages' => ['success' => ['Virtual receptionist created successfully.']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to create the virtual receptionist. Please try again.']]
            ], 500);
        }
    }

    public function update(UpdateVirtualReceptionistRequest $request)
    {
        $inputs = $request->validated();

        try {
            // Retrieve the IVR menu by UUID
            $instance = $this->model::where('ivr_menu_uuid', $inputs['ivr_menu_uuid'])->firstOrFail();

            $exit_data = $this->buildExitDestinationAction($inputs);

            // Update basic IVR menu fields
            $instance->fill([
                'ivr_menu_name' => $inputs['ivr_menu_name'],
                'ivr_menu_extension' => $inputs['ivr_menu_extension'],
                'ivr_menu_greet_long' => $inputs['ivr_menu_greet_long'] ?? null,
                'ivr_menu_description' => $inputs['ivr_menu_description'] ?? null,
                'ivr_menu_enabled' => $inputs['ivr_menu_enabled'],
                'ivr_menu_digit_len' => $inputs['digit_length'],
                'ivr_menu_timeout' => $inputs['prompt_timeout'],
                'ivr_menu_pin_number' => $inputs['pin'] ?? null,
                'ivr_menu_ringback' => $inputs['ring_back_tone'],
                'ivr_menu_invalid_sound' => $inputs['invalid_input_message'],
                'ivr_menu_exit_sound' => $inputs['exit_message'],
                'ivr_menu_direct_dial' => $inputs['direct_dial'],
                'ivr_menu_max_failures' => $inputs['repeat_prompt'],
                'ivr_menu_max_timeouts' => $inputs['repeat_prompt'],
                'ivr_menu_exit_app' => $exit_data['action'],
                'ivr_menu_exit_data' => $exit_data['data'],
                'ivr_menu_cid_prefix' => $inputs['caller_id_prefix'],
            ]);

            // Save the updated IVR menu
            $instance->save();

            $this->generateDialPlanXML($instance);

            //clear the destinations session array
            if (isset($_SESSION['destinations']['array'])) {
                unset($_SESSION['destinations']['array']);
            }

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Virtual receptionist settings have been updated successfully.']]
            ], 200);  // 200 OK for successful update
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to update the virtual receptionist settings. Please try again.']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Remove the specified resource from storage.
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete()
    {
        try {
            // Begin Transaction
            DB::beginTransaction();

            // Retrieve all items at once
            $items = $this->model::whereIn('ivr_menu_uuid', request('items'))
                ->get(['ivr_menu_uuid', 'dialplan_uuid']);

            foreach ($items as $item) {
                // Delete related IVR menu options (keys)
                $item->options()->delete();

                // Delete related Dialplan entry
                Dialplans::where('dialplan_uuid', $item->dialplan_uuid)->delete();

                // Clear cache
                $this->clearCache($item);

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
     * Duplicate the specified Virtual Receptionist and its options.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function duplicate(Request $request)
    {
        $request->validate([
            'ivr_menu_uuid' => 'required|uuid|exists:v_ivr_menus,ivr_menu_uuid',
        ]);

        if ($resp = $this->enforceLimit('ivr_menus', \App\Models\IvrMenus::class, 'domain_uuid', 'ivr_limit_error')) {
            return $resp;
        }

        try {
            DB::beginTransaction();

            $original = $this->model::where('ivr_menu_uuid', $request->ivr_menu_uuid)
                ->where('domain_uuid', session('domain_uuid'))
                ->with('options')
                ->firstOrFail();

            $newIvr = $original->replicate();
            $newIvr->ivr_menu_uuid = Str::uuid();
            $newIvr->dialplan_uuid = Str::uuid();
            $newIvr->ivr_menu_name = $original->ivr_menu_name . ' (Copy)';
            $newIvr->ivr_menu_extension = $this->model->generateUniqueSequenceNumber();

            $newIvr->save();

            foreach ($original->options as $option) {
                $newOption = $option->replicate();
                $newOption->ivr_menu_option_uuid = Str::uuid();
                $newOption->ivr_menu_uuid = $newIvr->ivr_menu_uuid;
                $newOption->save();
            }

            $this->generateDialPlanXML($newIvr);

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Virtual Receptionist duplicated successfully', 'New Extension: ' . $newIvr->ivr_menu_extension]],
                'ivr_menu_uuid' => $newIvr->ivr_menu_uuid
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to duplicate the virtual receptionist.']]
            ], 500);
        }
    }

    private function generateDialPlanXML(IvrMenus $ivr): void
    {
        $data = [
            'ivr' => $ivr,
            'dialplan_continue' => 'false',
        ];

        $xml = trim(view('layouts.xml.ivr-dial-plan-template', $data)->render());

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);
        $dom->formatOutput = true;
        $xml = $dom->saveXML($dom->documentElement);

        $dialPlan = Dialplans::where('dialplan_uuid', $ivr->dialplan_uuid)->first();

        if (!$dialPlan) {
            $newDialplanUuid = Str::uuid();

            $dialPlan = new Dialplans();
            $dialPlan->dialplan_uuid = $newDialplanUuid;
            $dialPlan->app_uuid = 'a5788e9b-58bc-bd1b-df59-fff5d51253ab';
            $dialPlan->domain_uuid = session('domain_uuid');
            $dialPlan->dialplan_context = session('domain_name');
            $dialPlan->dialplan_name = $ivr->ivr_menu_name;
            $dialPlan->dialplan_number = $ivr->ivr_menu_extension;
            $dialPlan->dialplan_continue = $data['dialplan_continue'];
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_order = 101;
            $dialPlan->dialplan_enabled = $ivr->ivr_menu_enabled;
            $dialPlan->dialplan_description = $ivr->ivr_menu_description;
            $dialPlan->insert_date = date('Y-m-d H:i:s');
            $dialPlan->insert_user = session('user_uuid');

            $ivr->dialplan_uuid = $newDialplanUuid;
            $ivr->save();
        } else {
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_name = $ivr->ivr_menu_name;
            $dialPlan->dialplan_number = $ivr->ivr_menu_extension;
            $dialPlan->dialplan_enabled = $ivr->ivr_menu_enabled;
            $dialPlan->dialplan_description = $ivr->ivr_menu_description;
            $dialPlan->update_date = date('Y-m-d H:i:s');
            $dialPlan->update_user = session('user_uuid');
        }

        $dialPlan->save();

        $this->clearCache($ivr);
    }

    private function clearCache($ivr): void
    {
        FusionCache::clear("dialplan." . session('domain_name'));
        FusionCache::clear("configuration.ivr.conf." . $ivr->ivr_menu_uuid);
    }

    public function getItemOptions(Request $request)
    {
        try {
            $domainUuid = $request->input('domain_uuid') ?? session('domain_uuid');
            $itemUuid = $request->input('item_uuid');

            $routes = [
                'get_routing_options' => route('routing.options'),
                'create_key_route' => route('virtual-receptionist.key.create'),
                'update_key_route' => route('virtual-receptionist.key.update'),
                'delete_key_route' => route('virtual-receptionist.key.destroy'),
                'ivr_message_route' => route('ivr.message.url'),
                'text_to_speech_route' => route('greetings.textToSpeech'),
                'greeting_route' => route('greeting.url'),
                'delete_greeting_route' => route('greetings.file.delete'),
                'update_greeting_route' => route('greetings.file.update'),
                'upload_greeting_route' => route('greetings.file.upload'),
                'get_greetings_route' => route('virtual-receptionists.getGreetings'),
                'store_route' => route('virtual-receptionists.store'),
            ];

            $routingOptionsService = new CallRoutingOptionsService;
            $routingTypes = $routingOptionsService->routingTypes;

            if ($itemUuid) {
                $ivr = $this->model::with([
                    'options' => function ($query) {
                        $query->select(
                            'ivr_menu_option_uuid',
                            'ivr_menu_uuid',
                            'ivr_menu_option_digits',
                            'ivr_menu_option_action',
                            'ivr_menu_option_param',
                            'ivr_menu_option_order',
                            'ivr_menu_option_description',
                            'ivr_menu_option_enabled'
                        )->orderByRaw("
                        CASE WHEN ivr_menu_option_digits ~ '^[0-9]+$'
                             THEN ivr_menu_option_digits::integer
                             ELSE NULL END ASC,
                        ivr_menu_option_digits ASC
                    ");
                    },
                ])
                    ->where('domain_uuid', $domainUuid)
                    ->where('ivr_menu_uuid', $itemUuid)
                    ->firstOrFail();

                $routes = array_merge($routes, [
                    'update_route' => route('virtual-receptionists.update', $ivr),
                    'apply_greeting_route' => route('virtual-receptionist.greeting.apply'),
                ]);

                $exitAction = null;
                $exitTargetUuid = null;
                $exitTargetExtension = null;
                $exitTargetName = null;

                if (!empty($ivr->ivr_menu_exit_app) || !empty($ivr->ivr_menu_exit_data)) {
                    $parsedExit = $this->parseExitDestinationAction(
                        $ivr->ivr_menu_exit_app,
                        $ivr->ivr_menu_exit_data
                    );

                    $exitAction = $parsedExit['action'] ?? null;
                    $exitTargetUuid = $parsedExit['target_uuid'] ?? null;
                    $exitTargetExtension = $parsedExit['target_extension'] ?? null;
                    $exitTargetName = $parsedExit['target_name'] ?? null;
                }

                $ivr->exit_action = $exitAction;
                $ivr->exit_target_uuid = $exitTargetUuid;
                $ivr->exit_target_extension = $exitTargetExtension;
                $ivr->exit_target_name = $exitTargetName;
                $ivr->repeat_prompt = $ivr->ivr_menu_max_timeouts;
            } else {
                if ($resp = $this->enforceLimit('ivr_menus', \App\Models\IvrMenus::class, 'domain_uuid', 'ivr_limit_error')) {
                    return $resp;
                }

                $ivr = new IvrMenus();
                $ivr->ivr_menu_uuid = '';
                $ivr->ivr_menu_name = '';
                $ivr->ivr_menu_extension = $ivr->generateUniqueSequenceNumber();
                $ivr->ivr_menu_description = '';
                $ivr->ivr_menu_greet_long = null;
                $ivr->ivr_menu_enabled = 'true';
                $ivr->ivr_menu_digit_len = '5';
                $ivr->ivr_menu_timeout = '3000';
                $ivr->ivr_menu_ringback = '${us-ring}';
                $ivr->ivr_menu_invalid_sound = 'ivr/ivr-that_was_an_invalid_entry.wav';
                $ivr->ivr_menu_exit_sound = 'silence_stream://100';
                $ivr->ivr_menu_direct_dial = 'false';
                $ivr->ivr_menu_max_failures = '3';
                $ivr->ivr_menu_max_timeouts = '3';
                $ivr->ivr_menu_cid_prefix = '';
                $ivr->ivr_menu_pin_number = '';
                $ivr->options = collect();

                $ivr->exit_action = null;
                $ivr->exit_target_uuid = null;
                $ivr->exit_target_extension = null;
                $ivr->exit_target_name = null;
                $ivr->repeat_prompt = '3';
                $ivr->voicemail_play_recording_instructions = 'false';
            }

            $permissions = $this->getUserPermissions();

            $phoneCallInstructions = [
                'Dial <strong>*732</strong> from your phone.',
                'Enter the virtual receptionist extension number when prompted and press <strong>#</strong>.',
                'Follow the prompts to record your greeting.',
            ];

            $sampleMessage = 'Thank you for calling. For Sales, press 1. For Support, press 2. To repeat this menu, press 9.';

            $promptRepeatOptions = [
                ['value' => '1', 'label' => '1 Time'],
                ['value' => '2', 'label' => '2 Times'],
                ['value' => '3', 'label' => '3 Times'],
                ['value' => '4', 'label' => '4 Times'],
                ['value' => '5', 'label' => '5 Times'],
            ];

            $ringBackTones = getRingBackTonesCollectionGrouped($domainUuid);
            $sounds = getSoundsCollectionGrouped($domainUuid);

            $openAiService = app(\App\Services\OpenAIService::class);

            return response()->json([
                'item' => $ivr,
                'permissions' => $permissions,
                'routes' => $routes,
                'routing_types' => $routingTypes,
                'voices' => $openAiService ? $openAiService->getVoices() : null,
                'default_voice' => $openAiService ? $openAiService->getDefaultVoice() : null,
                'speeds' => $openAiService ? $openAiService->getSpeeds() : null,
                'phone_call_instructions' => $phoneCallInstructions,
                'sample_message' => $sampleMessage,
                'prompt_repeat_options' => $promptRepeatOptions,
                'ring_back_tones' => $ringBackTones,
                'sounds' => $sounds,
            ]);
        } catch (\Throwable $e) {
            logger('Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to fetch item details.']]
            ], 500);
        }
    }

    public function getGreetings()
    {
        try {
            $greetingsArray = Recordings::where('domain_uuid', session('domain_uuid'))
                ->orderBy('recording_name')
                ->get()
                ->map(function ($greeting) {
                    return [
                        'value' => (string) $greeting->recording_filename,
                        'label' => $greeting->recording_name,
                        'description' => html_entity_decode(
                            $greeting->recording_description ?? '',
                            ENT_QUOTES | ENT_HTML5,
                            'UTF-8'
                        ),
                    ];
                })->values()->toArray();

            return response()->json($greetingsArray);
        } catch (\Exception $e) {
            logger('Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([]);
        }
    }

    public function getUserPermissions()
    {
        $permissions = [];
        $permissions['virtual_receptionist_create'] = userCheckPermission('ivr_menu_add');
        $permissions['virtual_receptionist_update'] = userCheckPermission('ivr_menu_edit');
        $permissions['virtual_receptionist_destroy'] = userCheckPermission('ivr_menu_delete');
        $permissions['is_superadmin'] = isSuperAdmin();

        return $permissions;
    }

    public function applyGreeting()
    {
        try {
            $ivrMenu = IvrMenus::findOrFail(request('ivr'));
            $ivrMenu->ivr_menu_greet_long = request('file_name');
            $ivrMenu->save();

            return response()->json([
                'success' => true,
                'messages' => ['success' => ['Your AI-generated greeting has been saved and successfully activated.']]
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function createKey(CreateVirtualReceptionistKeyRequest $request)
    {
        $inputs = $request->validated();

        try {
            $ivrMenuOption = IvrMenuOptions::create([
                'ivr_menu_option_uuid' => $inputs['option_uuid'] ?? (string) Str::uuid(),
                'ivr_menu_uuid' => $inputs['menu_uuid'],
                'ivr_menu_option_digits' => $inputs['key'],
                'ivr_menu_option_action' => 'menu-exec-app',
                'ivr_menu_option_param' => $this->buildKeyDestinationAction($inputs),
                'ivr_menu_option_description' => $inputs['description'],
                'ivr_menu_option_enabled' => $inputs['status'],
            ]);

            $this->clearCache($ivrMenuOption->ivrMenu);

            return response()->json([
                'messages' => ['success' => ['Virtual Receptionist Key successfully created']],
                'data' => $ivrMenuOption,
            ], 201);
        } catch (\Exception $e) {
            logger('VirtualReceptioninstController@createKey error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to create Virtual Receptionist Key.']]
            ], 500);
        }
    }

    public function updateKey(UpdateVirtualReceptionistKeyRequest $request)
    {
        $inputs = $request->validated();

        try {
            $ivrMenuOption = IvrMenuOptions::where('ivr_menu_option_uuid', $inputs['option_uuid'])
                ->with('IvrMenu')
                ->first();

            if (!$ivrMenuOption) {
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['Virtual Receptionist Key not found.']],
                ], 404);
            }

            $ivrMenuOption->update([
                'ivr_menu_option_digits' => $inputs['key'],
                'ivr_menu_option_action' => 'menu-exec-app',
                'ivr_menu_option_param' => $this->buildKeyDestinationAction($inputs),
                'ivr_menu_option_description' => $inputs['description'],
                'ivr_menu_option_enabled' => $inputs['status'],
            ]);

            $this->clearCache($ivrMenuOption->ivrMenu);

            return response()->json([
                'messages' => ['success' => ['Virtual Receptionist Key successfully updated']],
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to update Virtual Receptionist Key.']]
            ], 500);
        }
    }

    public function destroyKey()
    {
        $inputs = request()->all();

        try {
            $ivrMenuOption = IvrMenuOptions::where('ivr_menu_option_uuid', $inputs['ivr_menu_option_uuid'])
                ->with('ivrMenu')
                ->first();

            if (!$ivrMenuOption) {
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['Virtual Receptionist Key not found.']],
                ], 404);
            }

            $ivrMenuOption->delete();
            $this->clearCache($ivrMenuOption->ivrMenu);

            return response()->json([
                'messages' => ['success' => ['Virtual Receptionist Key successfully deleted']],
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to delete Virtual Receptionist Key.']]
            ], 500);
        }
    }

    protected function buildKeyDestinationAction($key)
    {
        switch ($key['action']) {
            case 'extensions':
            case 'ring_groups':
            case 'ivrs':
            case 'business_hours':
            case 'time_conditions':
            case 'contact_centers':
            case 'conferences':
            case 'faxes':
            case 'call_flows':
                return 'transfer ' . $key['extension'] . ' XML ' . session('domain_name');
            case 'voicemails':
                return 'transfer *99' . $key['extension'] . ' XML ' . session('domain_name');
            case 'recordings':
                return 'lua streamfile.lua ' . $key['extension'];
            case 'check_voicemail':
                return 'transfer *98 XML ' . session('domain_name');
            case 'company_directory':
                return 'transfer *411 XML ' . session('domain_name');
            case 'hangup':
                return 'hangup';
            default:
                return [];
        }
    }

    protected function buildExitDestinationAction($inputs)
    {
        $target = $inputs['exit_target'] ?? null;

        switch ($inputs['exit_action']) {
            case 'extensions':
            case 'ring_groups':
            case 'ivrs':
            case 'business_hours':
            case 'time_conditions':
            case 'contact_centers':
            case 'faxes':
            case 'call_flows':
                return ['action' => 'transfer', 'data' => $target . ' XML ' . session('domain_name')];
            case 'voicemails':
                return ['action' => 'transfer', 'data' => '*99' . $target . ' XML ' . session('domain_name')];
            case 'recordings':
                return ['action' => 'lua', 'data' => 'streamfile.lua ' . $target];
            case 'check_voicemail':
                return ['action' => 'transfer', 'data' => '*98 XML ' . session('domain_name')];
            case 'company_directory':
                return ['action' => 'transfer', 'data' => '*411 XML ' . session('domain_name')];
            case 'hangup':
                return ['action' => 'hangup', 'data' => ''];
            default:
                return ['action' => null, 'data' => null];
        }
    }

    protected function parseExitDestinationAction(?string $app, ?string $data): array
    {
        if (blank($app) && blank($data)) {
            return [
                'action' => null,
                'target_uuid' => null,
                'target_extension' => null,
                'target_name' => null,
            ];
        }

        $domainUuid = session('domain_uuid');
        $domainName = session('domain_name');

        if ($app === 'hangup') {
            return [
                'action' => 'hangup',
                'target_uuid' => null,
                'target_extension' => null,
                'target_name' => null,
            ];
        }

        if ($app === 'lua' && str_starts_with((string) $data, 'streamfile.lua ')) {
            $file = trim(str_replace('streamfile.lua ', '', (string) $data));

            return [
                'action' => 'recordings',
                'target_uuid' => $file,
                'target_extension' => $file,
                'target_name' => $file,
            ];
        }

        if ($app === 'transfer') {
            $normalized = trim((string) $data);

            if ($normalized === '*98 XML ' . $domainName) {
                return ['action' => 'check_voicemail', 'target_uuid' => null, 'target_extension' => null, 'target_name' => null];
            }

            if ($normalized === '*411 XML ' . $domainName) {
                return ['action' => 'company_directory', 'target_uuid' => null, 'target_extension' => null, 'target_name' => null];
            }

            if (preg_match('/^\*99(.+)\s+XML\s+.+$/', $normalized, $matches)) {
                $extensionNumber = trim($matches[1]);
                $voicemail = \App\Models\Voicemails::where('domain_uuid', $domainUuid)->where('voicemail_id', $extensionNumber)->first();
                return ['action' => 'voicemails', 'target_uuid' => $voicemail?->voicemail_uuid, 'target_extension' => $extensionNumber, 'target_name' => $extensionNumber];
            }

            if (preg_match('/^(.+)\s+XML\s+.+$/', $normalized, $matches)) {
                $extensionNumber = trim($matches[1]);

                $extension = \App\Models\Extensions::where('domain_uuid', $domainUuid)->where('extension', $extensionNumber)->first();
                if ($extension) return ['action' => 'extensions', 'target_uuid' => $extension->extension_uuid, 'target_extension' => $extension->extension, 'target_name' => $extension->name_formatted ?? $extension->extension];

                $ringGroup = \App\Models\RingGroups::where('domain_uuid', $domainUuid)->where('ring_group_extension', $extensionNumber)->first();
                if ($ringGroup) return ['action' => 'ring_groups', 'target_uuid' => $ringGroup->ring_group_uuid, 'target_extension' => $ringGroup->ring_group_extension, 'target_name' => $ringGroup->name_formatted ?? $ringGroup->ring_group_extension];

                $ivr = \App\Models\IvrMenus::where('domain_uuid', $domainUuid)->where('ivr_menu_extension', $extensionNumber)->first();
                if ($ivr) return ['action' => 'ivrs', 'target_uuid' => $ivr->ivr_menu_uuid, 'target_extension' => $ivr->ivr_menu_extension, 'target_name' => $ivr->ivr_menu_name];

                return ['action' => null, 'target_uuid' => null, 'target_extension' => $extensionNumber, 'target_name' => $extensionNumber];
            }
        }

        return ['action' => null, 'target_uuid' => null, 'target_extension' => null, 'target_name' => null];
    }

    public function selectAll()
    {
        try {
            $uuids = $this->model::where('domain_uuid', session('domain_uuid'))
                ->get($this->model->getKeyName())->pluck($this->model->getKeyName());

            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $uuids,
            ], 200);
        } catch (\Exception $e) {
            logger($e);
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500);
        }
    }
}