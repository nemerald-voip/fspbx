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
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'VirtualReceptionists';
    protected $searchable = ['ivr_menu_name', 'ivr_menu_extension', 'ivr_menu_description'];

    public function __construct()
    {
        $this->model = new IvrMenus();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
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
                'data' => function () {
                    return $this->getData();
                },

                'routes' => [
                    'current_page' => route('virtual-receptionists.index'),
                    'store' => route('virtual-receptionists.store'),
                    'item_options' => route('virtual-receptionists.item.options'),
                    'select_all' => route('virtual-receptionists.select.all'),
                    'bulk_delete' => route('virtual-receptionists.bulk.delete'),
                    'duplicate_virtual_receptionist' => route('virtual-receptionists.duplicate'),

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
        $this->sortField = request()->get('sortField', 'ivr_menu_extension'); // Default to 'voicemail_id'
        $this->sortOrder = request()->get('sortOrder', 'asc'); // Default to descending

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
        // $data->with(['extension' => function ($query) use ($domainUuid) {
        //     $query->select('extension_uuid', 'extension', 'effective_caller_id_name')
        //         ->where('domain_uuid', $domainUuid);
        // }]);


        $data->select(
            'ivr_menu_uuid',
            'ivr_menu_name',
            'ivr_menu_extension',
            'ivr_menu_enabled',
            'ivr_menu_description',

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


    public function store(StoreVirtualReceptionistRequest $request)
    {
        $inputs = $request->validated();

        try {
            // Create a new model instance
            $instance = $this->model;

            // Fill the model with validated input data
            $instance->fill([
                'domain_uuid' => session('domain_uuid'), // Set domain_uuid from session
                'dialplan_uuid' => Str::uuid(),
                'ivr_menu_name' => $inputs['ivr_menu_name'],
                'ivr_menu_description' => $inputs['ivr_menu_description'],
                'ivr_menu_extension' => $inputs['ivr_menu_extension'],
                'ivr_menu_enabled' => $inputs['ivr_menu_enabled'] === 'true' ? 'true' : 'false',
                'ivr_menu_digit_len' => $inputs['digit_length'],
                'ivr_menu_timeout' => $inputs['prompt_timeout'],
                'ivr_menu_ringback' => $inputs['ring_back_tone'],
                'ivr_menu_invalid_sound' => $inputs['invalid_input_message'],
                'ivr_menu_direct_dial' => $inputs['direct_dial'] ? 'true' : 'false',
                'ivr_menu_context' => session('domain_name'),
                'ivr_menu_max_failures' => '3',
                'ivr_menu_max_timeouts' => '3',
                'ivr_menu_cid_prefix' => $inputs['caller_id_prefix'],
            ]);

            // Save the model to the database
            $instance->save();

            $this->generateDialPlanXML($instance);

            // Clear cached destinations session array
            if (isset($_SESSION['destinations']['array'])) {
                unset($_SESSION['destinations']['array']);
            }

            // Return a JSON response indicating success
            return response()->json([
                'item_uuid' => $instance->ivr_menu_uuid,
                'messages' => ['success' => ['New item created']]
            ], 201);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to create new item']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    function update(UpdateVirtualReceptionistRequest $request)
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
                'ivr_menu_enabled' => $inputs['ivr_menu_enabled'] === 'true' ? 'true' : 'false',
                'ivr_menu_digit_len' => $inputs['digit_length'],
                'ivr_menu_timeout' => $inputs['prompt_timeout'],
                'ivr_menu_ringback' => $inputs['ring_back_tone'],
                'ivr_menu_invalid_sound' => $inputs['invalid_input_message'],
                'ivr_menu_exit_sound' => $inputs['exit_message'],
                'ivr_menu_direct_dial' => $inputs['direct_dial'] ? 'true' : 'false',
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
                'messages' => ['success' => ['Item updated successfully']]
            ], 200);  // 200 OK for successful update
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update item']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Remove the specified Virtual Receptionist (IVR Menu) from storage.
     *
     * @param  IvrMenus $ivrMenu
     * @return \Illuminate\Http\Response
     */
    public function destroy(IvrMenus $virtual_receptionist)
    {
        try {
            // Start a database transaction to ensure atomic operations
            DB::beginTransaction();

            // Delete related IVR menu options (keys)
            $virtual_receptionist->options()->delete();

            // Delete related Dialplan entry
            Dialplans::where('dialplan_uuid', $virtual_receptionist->dialplan_uuid)->delete();

            // Clear FusionCache for the deleted IVR
            $this->clearCache($virtual_receptionist);

            // Finally, delete the IVR menu itself
            $virtual_receptionist->delete();

            // Commit the transaction
            DB::commit();

            // Return success message
            return redirect()->back()->with('message', ['server' => ['Item deleted successfully']]);
        } catch (\Exception $e) {
            // Rollback the transaction if an error occurs
            DB::rollBack();

            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            // Return error message
            return redirect()->back()->with('error', ['server' => ['Server returned an error while deleting this item']]);
        }
    }

    /**
     * Remove the specified resource from storage.
     * @return JsonResponse
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

        // 1. Check Limits
        if ($resp = $this->enforceLimit(
            'ivr_menus',
            \App\Models\IvrMenus::class,
            'domain_uuid',
            'ivr_limit_error'
        )) {
            return $resp;
        }

        try {
            DB::beginTransaction();

            // 2. Fetch the Original
            $original = $this->model::where('ivr_menu_uuid', $request->ivr_menu_uuid)
                ->where('domain_uuid', session('domain_uuid')) // Security check
                ->with('options')
                ->firstOrFail();

            // 3. Replicate the Parent (IVR Menu)
            $newIvr = $original->replicate();
            $newIvr->ivr_menu_uuid = Str::uuid();
            $newIvr->dialplan_uuid = Str::uuid(); // Important: decouple from original dialplan
            $newIvr->ivr_menu_name = $original->ivr_menu_name . ' (Copy)';

            // Generate a new unique extension number
            $newIvr->ivr_menu_extension = $this->model->generateUniqueSequenceNumber();

            $newIvr->save();

            // 4. Replicate the Children (IVR Options/Keys)
            foreach ($original->options as $option) {
                $newOption = $option->replicate();
                $newOption->ivr_menu_option_uuid = Str::uuid();
                $newOption->ivr_menu_uuid = $newIvr->ivr_menu_uuid; // Link to new parent
                $newOption->save();
            }

            // 5. Generate Dialplan XML and FusionPBX configuration
            // This reuses your existing private method to handle the XML logic
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

        // logger($ivr);
        // Data to pass to the Blade template
        $data = [
            'ivr' => $ivr,
            // 'domain_name' => session('domain_name'),
            'dialplan_continue' => 'false',
        ];


        // Render the Blade template and get the XML content as a string
        $xml = trim(view('layouts.xml.ivr-dial-plan-template', $data)->render());

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;  // Removes extra spaces
        $dom->loadXML($xml);
        $dom->formatOutput = true;         // Formats XML properly
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

            // Update IVR with the new dialplan_uuid
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
                $ivr->voicemail_play_recording_instructions = 'false';
            } else {
                if ($resp = $this->enforceLimit(
                    'ivr_menus',
                    \App\Models\IvrMenus::class,
                    'domain_uuid',
                    'ivr_limit_error'
                )) {
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
        $permissions['is_superadmin'] = isSuperAdmin();

        return $permissions;
    }

    public function applyGreeting()
    {
        try {
            // Retrieve the IVR menu by the provided 'ivr' ID
            $ivrMenu = IvrMenus::findOrFail(request('ivr'));

            // Update the 'ivr_menu_greet_long' field with the 'file_name'
            $ivrMenu->ivr_menu_greet_long = request('file_name');

            // Save the changes to the model
            $ivrMenu->save();

            return response()->json([
                'success' => true,
                'messages' => ['success' => ['Your AI-generated greeting has been saved and successfully activated.']]
            ], 200);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    public function createKey(CreateVirtualReceptionistKeyRequest $request)
    {
        $inputs = $request->validated();

        try {
            // Create a new IvrMenuOption
            $ivrMenuOption = IvrMenuOptions::create([
                'ivr_menu_option_uuid' => $inputs['option_uuid'] ?? (string) Str::uuid(),
                'ivr_menu_uuid' => $inputs['menu_uuid'],
                'ivr_menu_option_digits' => $inputs['key'],
                'ivr_menu_option_action' => 'menu-exec-app',
                'ivr_menu_option_param' => $this->buildKeyDestinationAction($inputs),
                'ivr_menu_option_description' => $inputs['description'],
                'ivr_menu_option_enabled' => $inputs['status'],
            ]);

            // Clear FusionCache
            $this->clearCache($ivrMenuOption->ivrMenu);

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Virtual Receptionist Key successfully created']],
                'data' => $ivrMenuOption, // Return the created option for confirmation or further use
            ], 201);
        } catch (\Exception $e) {
            logger('VirtualReceptioninstController@createKey error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to create Virtual Receptionist Key.']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }


    /**
     * Update Virtual Receptionist Key
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateKey(UpdateVirtualReceptionistKeyRequest $request)
    {

        $inputs = $request->validated();

        try {
            // Find the IvrMenuOption by UUID and Menu UUID
            $ivrMenuOption = IvrMenuOptions::where('ivr_menu_option_uuid', $inputs['option_uuid'])
                ->with('IvrMenu')
                ->first();

            if (!$ivrMenuOption) {
                // Handle case where the record is not found
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['Virtual Receptionist Key not found.']],
                ], 404); // 404 Not Found
            }

            // Update the attributes
            $ivrMenuOption->update([
                'ivr_menu_option_digits' => $inputs['key'],
                'ivr_menu_option_action' => 'menu-exec-app',
                'ivr_menu_option_param' => $this->buildKeyDestinationAction($inputs),
                'ivr_menu_option_description' => $inputs['description'],
                'ivr_menu_option_enabled' => $inputs['status'],
            ]);

            // Clear FusionCache
            $this->clearCache($ivrMenuOption->ivrMenu);

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Virtual Receptionist Key successfully updated']],
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to update Virtual Receptionist Key.']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Update Virtual Receptionist Key
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyKey()
    {
        $inputs = request()->all();

        try {
            // Find the IvrMenuOption by UUID
            $ivrMenuOption = IvrMenuOptions::where('ivr_menu_option_uuid', $inputs['ivr_menu_option_uuid'])
                ->with('ivrMenu')
                ->first();

            if (!$ivrMenuOption) {
                // Handle case where the record is not found
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['Virtual Receptionist Key not found.']],
                ], 404); // 404 Not Found
            }

            // Delete the record
            $ivrMenuOption->delete();

            // Clear FusionCache
            $this->clearCache($ivrMenuOption->ivrMenu);

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Virtual Receptionist Key successfully deleted']],
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Unable to delete Virtual Receptionist Key.']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }


    /**
     * Helper function to build destination action based on key action.
     */
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
                // Handle recordings with 'lua' destination app
                return 'lua streamfile.lua ' . $key['extension'];

            case 'check_voicemail':
                return 'transfer *98 XML ' . session('domain_name');

            case 'company_directory':
                return 'transfer *411 XML ' . session('domain_name');

            case 'hangup':
                return 'hangup';

                // Add other cases as necessary for different types
            default:
                return [];
        }
    }

    /**
     * Helper function to build destination action based on exit action.
     */
    protected function buildExitDestinationAction($inputs)
    {
        switch ($inputs['exit_action']) {
            case 'extensions':
            case 'ring_groups':
            case 'ivrs':
            case 'business_hours':
            case 'time_conditions':
            case 'contact_centers':
            case 'faxes':
            case 'call_flows':
                return  ['action' => 'transfer', 'data' => $inputs['exit_target_extension'] . ' XML ' . session('domain_name')];
            case 'voicemails':
                return ['action' => 'transfer', 'data' => '*99' . $inputs['exit_target_extension'] . ' XML ' . session('domain_name')];

            case 'recordings':
                // Handle recordings with 'lua' destination app
                return ['action' => 'lua', 'data' => 'streamfile.lua ' . $inputs['exit_target_extension']];

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
                return [
                    'action' => 'check_voicemail',
                    'target_uuid' => null,
                    'target_extension' => null,
                    'target_name' => null,
                ];
            }

            if ($normalized === '*411 XML ' . $domainName) {
                return [
                    'action' => 'company_directory',
                    'target_uuid' => null,
                    'target_extension' => null,
                    'target_name' => null,
                ];
            }

            if (preg_match('/^\*99(.+)\s+XML\s+.+$/', $normalized, $matches)) {
                $extensionNumber = trim($matches[1]);

                $voicemail = \App\Models\Voicemails::where('domain_uuid', $domainUuid)
                    ->where('voicemail_id', $extensionNumber)
                    ->first();

                return [
                    'action' => 'voicemails',
                    'target_uuid' => $voicemail?->voicemail_uuid,
                    'target_extension' => $extensionNumber,
                    'target_name' => $extensionNumber,
                ];
            }

            if (preg_match('/^(.+)\s+XML\s+.+$/', $normalized, $matches)) {
                $extensionNumber = trim($matches[1]);

                $extension = \App\Models\Extensions::where('domain_uuid', $domainUuid)
                    ->where('extension', $extensionNumber)
                    ->first();

                if ($extension) {
                    return [
                        'action' => 'extensions',
                        'target_uuid' => $extension->extension_uuid,
                        'target_extension' => $extension->extension,
                        'target_name' => $extension->name_formatted ?? $extension->extension,
                    ];
                }

                $ringGroup = \App\Models\RingGroups::where('domain_uuid', $domainUuid)
                    ->where('ring_group_extension', $extensionNumber)
                    ->first();

                if ($ringGroup) {
                    return [
                        'action' => 'ring_groups',
                        'target_uuid' => $ringGroup->ring_group_uuid,
                        'target_extension' => $ringGroup->ring_group_extension,
                        'target_name' => $ringGroup->name_formatted ?? $ringGroup->ring_group_extension,
                    ];
                }

                $ivr = \App\Models\IvrMenus::where('domain_uuid', $domainUuid)
                    ->where('ivr_menu_extension', $extensionNumber)
                    ->first();

                if ($ivr) {
                    return [
                        'action' => 'ivrs',
                        'target_uuid' => $ivr->ivr_menu_uuid,
                        'target_extension' => $ivr->ivr_menu_extension,
                        'target_name' => $ivr->ivr_menu_name,
                    ];
                }

                return [
                    'action' => null,
                    'target_uuid' => null,
                    'target_extension' => $extensionNumber,
                    'target_name' => $extensionNumber,
                ];
            }
        }

        return [
            'action' => null,
            'target_uuid' => null,
            'target_extension' => null,
            'target_name' => null,
        ];
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function selectAll()
    {
        try {
            $uuids = $this->model::where('domain_uuid', session('domain_uuid'))
                ->get($this->model->getKeyName())->pluck($this->model->getKeyName());


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
