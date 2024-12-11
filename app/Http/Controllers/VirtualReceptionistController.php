<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Domain;
use App\Models\IvrMenus;
use App\Models\Recordings;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\VoicemailGreetings;
use Illuminate\Support\Facades\DB;
use App\Models\VoicemailDestinations;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use App\Services\CallRoutingOptionsService;
use App\Http\Requests\StoreVoicemailRequest;
use App\Http\Requests\UpdateVirtualReceptionistRequest;

class VirtualReceptionistController extends Controller
{
    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'VirtualReceptionists';
    protected $searchable = ['voicemail_id', 'voicemail_mail_to', 'extension.effective_caller_id_name'];

    public function __construct()
    {
        $this->model = new IvrMenus();
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


    public function store(StoreVoicemailRequest $request)
    {
        $inputs = $request->validated();

        try {
            $this->model->fill($inputs);

            // Save the model instance to the database
            $this->model->save();

            // Check if voicemail_copies is present and is an array
            if (isset($inputs['voicemail_copies']) && is_array($inputs['voicemail_copies'])) {
                // Prepare data for VoicemailDestinations
                foreach ($inputs['voicemail_copies'] as $copyUuid) {
                    // Create a new VoicemailDestinations instance and set the fields
                    $voicemailDestination = new VoicemailDestinations();
                    $voicemailDestination->voicemail_uuid = $this->model->voicemail_uuid; // Set the parent voicemail UUID
                    $voicemailDestination->voicemail_uuid_copy = $copyUuid; // Set the copy UUID

                    // Save the VoicemailDestinations instance
                    $voicemailDestination->save();
                }
            }

            // Return a JSON response indicating success
            return response()->json([
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

    function update(UpdateVirtualReceptionistRequest $request, $uuid)
    {
        $inputs = $request->validated();

        try {
            // Retrieve the item by ID from the route parameter
            $voicemail = $this->model->findOrFail($uuid);

            // Update the voicemail with the new inputs
            $voicemail->fill($inputs);

            // Save the updated voicemail to the database
            $voicemail->save();

            // Check if voicemail_copies is present and is an array
            if (isset($inputs['voicemail_copies']) && is_array($inputs['voicemail_copies'])) {
                // Delete existing voicemail copies for this voicemail
                VoicemailDestinations::where('voicemail_uuid', $voicemail->voicemail_uuid)->delete();

                // Prepare data for new VoicemailDestinations
                foreach ($inputs['voicemail_copies'] as $copyUuid) {
                    // Create a new VoicemailDestinations instance and set the fields
                    $voicemailDestination = new VoicemailDestinations();
                    $voicemailDestination->voicemail_uuid = $voicemail->voicemail_uuid; // Set the parent voicemail UUID
                    $voicemailDestination->voicemail_uuid_copy = $copyUuid; // Set the copy UUID

                    // Save the VoicemailDestinations instance
                    $voicemailDestination->save();
                }
            }

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
     * Upload a voicemail greeting.
     *
     * @return \Illuminate\Http\Response
     */
    public function uploadVoicemailGreeting(Request $request, Voicemails $voicemail)
    {

        $domain = Domain::where('domain_uuid', $voicemail->domain_uuid)->first();

        if ($request->greeting_type == "unavailable") {
            $filename = "greeting_1.wav";
            $path = $request->voicemail_unavailable_upload_file->storeAs(
                $domain->domain_name . '/' . $voicemail->voicemail_id,
                $filename,
                'voicemail'
            );
        } elseif ($request->greeting_type == "name") {
            $filename = "recorded_name.wav";
            $path = $request->voicemail_name_upload_file->storeAs(
                $domain->domain_name . '/' . $voicemail->voicemail_id,
                $filename,
                'voicemail'
            );
        }

        if (!Storage::disk('voicemail')->exists($path)) {
            return response()->json([
                'error' => 401,
                'message' => 'Failed to upload file'
            ]);
        }

        // Remove old greeting
        foreach ($voicemail->greetings as $greeting) {
            if ($greeting->filename = $filename) {
                $greeting->delete();
                break;
            }
        }

        if ($request->greeting_type == "unavailable") {
            // Save new greeting in the database
            $greeting = new VoicemailGreetings();
            $greeting->domain_uuid = Session::get('domain_uuid');
            $greeting->voicemail_id = $voicemail->voicemail_id;
            $greeting->greeting_id = 1;
            $greeting->greeting_name = "Greeting 1";
            $greeting->greeting_filename = $filename;
            $voicemail->greetings()->save($greeting);

            // Save default gretting ID
            $voicemail->greeting_id = 1;
            $voicemail->save();
        }

        return response()->json([
            'status' => "success",
            'voicemail' => $voicemail->voicemail_id,
            'filename' => $filename,
            'message' => 'Greeting uploaded successfully'
        ]);
    }


    /**
     * Get voicemail greeting.
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadVoicemailGreeting(Voicemails $voicemail, string $filename)
    {

        $path = session('domain_name') . '/' . $voicemail->voicemail_id . '/' . $filename;

        if (!Storage::disk('voicemail')->exists($path)) abort(404);

        $file = Storage::disk('voicemail')->path($path);
        $type = Storage::disk('voicemail')->mimeType($path);
        $headers = array(
            'Content-Type: ' . $type,
        );

        $response = Response::download($file, $filename, $headers);

        return $response;
    }

    /**
     * Get voicemail greeting.
     *
     * @return \Illuminate\Http\Response
     */
    public function deleteVoicemailGreeting(Voicemails $voicemail, string $filename)
    {

        $path = Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/' . $filename;

        $file = Storage::disk('voicemail')->delete($path);

        if (Storage::disk('voicemail')->exists($path)) {
            return response()->json([
                'error' => 401,
                'message' => 'Failed to delete file'
            ]);
        }

        // Remove greeting from database
        foreach ($voicemail->greetings as $greeting) {
            if ($greeting->filename = "greeting_1.wav") {
                $greeting->delete();
                break;
            }
        }

        // Update default gretting ID
        $voicemail->greeting_id = null;
        $voicemail->save();

        return response()->json([
            'status' => "success",
            'voicemail' => $voicemail->voicemail_id,
            'filename' => 'greeting_1.wav',
            'message' => 'Greeting deleted successfully'
        ]);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Voicemails $voicemail)
    {

        try {
            // throw new \Exception;

            // Start a database transaction to ensure atomic operations
            DB::beginTransaction();

            // Delete related voicemail destinations
            $voicemail->voicemail_destinations()->delete();

            // Delete related voicemail messages
            $voicemail->messages()->delete();

            // Delete related voicemail greetings
            $voicemail->greetings()->delete();

            // Define the path to the voicemail folder
            $path = session('domain_name') . '/' . $voicemail->voicemail_id;

            // Check if the directory exists and delete it
            if (Storage::disk('voicemail')->exists($path)) {
                Storage::disk('voicemail')->deleteDirectory($path);
            }

            // Finally, delete the voicemail itself
            $voicemail->delete();

            // Commit the transaction
            DB::commit();

            return redirect()->back()->with('message', ['server' => ['Item deleted']]);
        } catch (\Exception $e) {
            // Rollback the transaction if an error occurs
            DB::rollBack();
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return redirect()->back()->with('error', ['server' => ['Server returned an error while deleting this item']]);
        }

        $voicemail = Voicemails::findOrFail($id);

        if (isset($voicemail)) {
            $deleted = $voicemail->delete();
            $filename = "recorded_name.wav";
            $path = Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/' . $filename;
            $file = Storage::disk('voicemail')->delete($path);
            $filename = "greeting_1.wav";
            $path = Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/' . $filename;
            $file = Storage::disk('voicemail')->delete($path);

            if ($deleted) {
                return response()->json([
                    'status' => 200,
                    'success' => [
                        'message' => 'Selected vocemail extensions have been deleted'
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => 'There was an error deleting selected voicemail extensions'
                    ]
                ]);
            }
        }
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

            // Only add the Keys tab if item_uuid exists and insert it in the second position
            if ($item_uuid) {
                $greetingsTab = [
                    'name' => 'Keys',
                    'icon' => 'DialpadIcon',
                    'slug' => 'keys',
                ];

                // Insert Greetings tab at the second position (index 1)
                array_splice($navigation, 1, 0, [$greetingsTab]);
            }

            $ivrs =  $this->model::where($this->model->getTable() . '.domain_uuid', $domain_uuid)
                // ->with(['extension' => function ($query) use ($domain_uuid) {
                //     $query->select('extension_uuid', 'extension', 'effective_caller_id_name')
                //         ->where('domain_uuid', $domain_uuid);
                // }])
                ->select(
                    'ivr_menu_uuid',
                    'ivr_menu_name',
                    'ivr_menu_extension',

                )
                ->orderBy('ivr_menu_extension', 'asc')
                ->get();

            // Transform the collection into the desired array format
            $ivrOptions = $ivrs->map(function ($ivr) {
                return [
                    'value' => $ivr->ivr_menu_uuid,
                    'name' => $ivr->ivr_menu_extension . " - " . $ivr->ivr_menu_name,
                ];
            })->toArray();


            $routes = [
                'get_routing_options' => route('routing.options'),
            ];

            // Check if item_uuid exists to find an existing voicemail
            if ($item_uuid) {
                // Find existing ivr by item_uuid
                $ivr = $this->model::
                with([
                    'options' => function ($query) {
                        $query->select(
                            'ivr_menu_option_uuid', 
                            'ivr_menu_uuid', 
                            'ivr_menu_option_digits',
                            'ivr_menu_option_action',
                            'ivr_menu_option_param',
                            'ivr_menu_option_order',
                            'ivr_menu_option_description'
                        );
                    },
                ])->where('ivr_menu_uuid', $item_uuid)->first();

                // If a voicemail exists, use it; otherwise, create a new one
                if (!$ivr) {
                    throw new \Exception("Failed to fetch item details. Item not found");
                }

                // Transform greetings into the desired array format
                $greetingsArray = Recordings::where('domain_uuid', session('domain_uuid'))
                    ->orderBy('recording_name')
                    ->get()
                    ->map(function ($greeting) {
                        return [
                            'value' => $greeting->recording_filename,
                            'name' => $greeting->recording_name,
                        ];
                    })->toArray();

                // Add the default options at the beginning of the array
                array_unshift(
                    $greetingsArray,
                    ['value' => '', 'name' => 'None'],
                );

                $routes = array_merge($routes, [
                    'text_to_speech_route' => route('greetings.textToSpeech'),
                    'greeting_route' => route('greeting.url'),
                    'delete_greeting_route' => route('greetings.file.delete'),
                    'update_greeting_route' => route('greetings.file.update'),
                    'upload_greeting_route' => route('greetings.file.upload'),
                    'update_route' => route('virtual-receptionists.update', $ivr),
                    'apply_greeting_route' => route('virtual-receptionist.greeting.apply'),

                ]);
            } else {
                // Create a new voicemail if item_uuid is not provided
                $ivr = $this->model;
                $ivr->ivr_menu_extension = $ivr->generateUniqueSequenceNumber();
                $ivr->ivr_menu_invalid_sound = 'ivr/ivr-that_was_an_invalid_entry.wav';
                $ivr->ivr_menu_confirm_attempts = 1;
                $ivr->ivr_menu_timeout = 3000;
                $ivr->ivr_menu_inter_digit_timeout = 2000;
                $ivr->ivr_menu_max_failures = 3;
                $ivr->ivr_menu_max_timeouts = 3;
                $ivr->ivr_menu_digit_len = 5;
                $ivr->ivr_menu_direct_dial = false;
            }

            $permissions = $this->getUserPermissions();
            // logger($permissions);

            $openAiVoices = [
                ['value' => 'alloy', 'name' => 'Alloy'],
                ['value' => 'echo', 'name' => 'Echo'],
                ['value' => 'fable', 'name' => 'Fable'],
                ['value' => 'onyx', 'name' => 'Onyx'],
                ['value' => 'nova', 'name' => 'Nova'],
                ['value' => 'shimmer', 'name' => 'Shimmer'],
            ];

            $openAiSpeeds = [];

            for ($i = 0.25; $i <= 4.0; $i += 0.25) {
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
                'Enter the virtual receptionist extension number when prompted and press <strong>#</strong>.',
                'Follow the prompts to record your greeting.',
            ];

            $sampleMessage = 'Thank you for calling. For Sales, press 1. For Support, press 2. To repeat this menu, press 9.';

            // Construct the itemOptions object
            $itemOptions = [
                'navigation' => $navigation,
                'all_ivrs' => $ivrOptions,
                'ivr' => $ivr,
                'permissions' => $permissions,
                'greetings' => $greetingsArray ?? null,
                'voices' => $openAiVoices,
                'speeds' => $openAiSpeeds,
                'routes' => $routes,
                'routing_types' => $routingTypes,
                'phone_call_instructions' => $phoneCallInstructions,
                'sample_message' => $sampleMessage,
                // Define options for other fields as needed
            ];

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
        $permissions['manage_voicemail_copies'] = userCheckPermission('voicemail_forward');
        $permissions['manage_voicemail_transcription'] = userCheckPermission('voicemail_transcription_enabled');
        $permissions['manage_voicemail_auto_delete'] = userCheckPermission('voicemail_local_after_email');
        $permissions['manage_voicemail_recording_instructions'] = userCheckPermission('voicemail_recording_instructions');

        // $permissions['manage_voicemail_copies'] = false;
        // $permissions['manage_voicemail_transcription'] = false;
        // $permissions['manage_voicemail_auto_delete'] = false;
        // $permissions['manage_voicemail_recording_instructions'] = false;

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
                'message' => ['success' => 'Your AI-generated greeting has been saved and successfully activated.']
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


    public function deleteTempFiles($folderPath)
    {
        $files = Storage::disk('voicemail')->files($folderPath);
        foreach ($files as $file) {
            if (Str::startsWith(basename($file), 'temp')) {
                Storage::disk('voicemail')->delete($file);
            }
        }
    }

}
