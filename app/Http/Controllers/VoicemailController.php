<?php

namespace App\Http\Controllers;

use App\Data\VoicemailData;
use Inertia\Inertia;
use App\Models\Domain;
use App\Models\Voicemails;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\OpenAIService;
use App\Models\VoicemailGreetings;
use Illuminate\Support\Facades\DB;
use App\Models\VoicemailDestinations;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use App\Http\Requests\TextToSpeechRequest;
use App\Http\Requests\StoreVoicemailRequest;
use App\Http\Requests\UpdateVoicemailRequest;

class VoicemailController extends Controller
{
    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Voicemails';

    public function __construct()
    {
        $this->model = new Voicemails();
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
        if (!userCheckPermission("voicemail_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'routes' => [
                    'current_page' => route('voicemails.index'),
                    'data_route' => route('voicemails.data'),
                    'store' => route('voicemails.store'),
                    'item_options' => route('voicemails.item.options'),
                    'bulk_delete' => route('voicemails.bulk.delete'),
                    'select_all' => route('voicemails.select.all'),
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

        $items = QueryBuilder::for(Voicemails::class)
            // only voicemails in the current domain
            ->where('domain_uuid', $currentDomain)
            ->select([
                'voicemail_uuid',
                'domain_uuid',         
                'voicemail_id',
                'voicemail_mail_to',
                'voicemail_enabled',
                'voicemail_description',
            ])
            ->with([
                'extension' => function ($q) use ($currentDomain) {
                    $q->select([
                        'extension_uuid',
                        'domain_uuid',      // include if you filter by it
                        'extension',
                        'effective_caller_id_name',
                    ])->where('domain_uuid', $currentDomain);
                }
            ])
            ->withCount('messages')
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value)  use ($currentDomain) {
                    $query->where(function ($q) use ($value, $currentDomain) {
                        $q->where('voicemail_id', 'ilike', "%{$value}%")
                            ->orWhere('voicemail_mail_to', 'ilike', "%{$value}%")
                            ->orWhere('voicemail_description', 'ilike', "%{$value}%")
                            // Search related extenion
                            ->orWhereHas('extension', function ($q2) use ($value, $currentDomain) {
                                $q2->where('domain_uuid', $currentDomain)
                                    ->where('extension', 'ilike', "%{$value}%")
                                    ->orWhere('effective_caller_id_name', 'ilike', "%{$value}%");
                            });
                        // Add more fields if needed
                    });
                }),
                AllowedFilter::exact('voicemail_enabled'), // Example: filter[enabled]=true
            ])
            ->allowedSorts(['voicemail_id'])
            ->defaultSort('voicemail_id')
            ->paginate($perPage);

        // // wrap in your DTO
        // $voiemailsDto = VoicemailData::collect($items);

        // logger( $items);

        return $items;
    }




    public function store(StoreVoicemailRequest $request)
    {
        $inputs = $request->validated();

        // If blank, generate
        if (empty($inputs['voicemail_password'])) {
            if (get_domain_setting('password_complexity') == 'true') {
                $inputs['voicemail_password'] = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
            } else {
                $inputs['voicemail_password'] = $inputs['voicemail_id'] ?? '0000';
            }
        }

        // If it was prefilled to mailbox number, override to random when complexity is on
        if (
            get_domain_setting('password_complexity') == 'true'
            && !empty($inputs['voicemail_id'])
            && isset($inputs['voicemail_password'])
            && (string)$inputs['voicemail_password'] === (string)$inputs['voicemail_id']
        ) {
            $inputs['voicemail_password'] = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        }


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

    function update(UpdateVoicemailRequest $request, $uuid)
    {
        $inputs = $request->validated();

        try {
            // Retrieve the item by ID from the route parameter
            $voicemail = $this->model->findOrFail($uuid);

            // Set system fields
            $voicemail->insert_date = date('Y-m-d H:i:s');
            $voicemail->insert_user = session('user_uuid');

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
            $greeting->domain_uuid = session('domain_uuid');
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

        $path = session('domain_name') . '/' . $voicemail->voicemail_id . '/' . $filename;

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
     * @return JsonResponse
     */
    public function bulkDelete()
    {
        try {
            // Begin Transaction
            DB::beginTransaction();

            // Retrieve all items at once
            $items = $this->model::whereIn('voicemail_uuid', request('items'))
                ->get(['voicemail_uuid']);

            foreach ($items as $item) {
                // Delete related voicemail destinations
                $item->voicemail_destinations()->delete();

                // Delete related voicemail messages
                $item->messages()->delete();

                // Delete related voicemail greetings
                $item->greetings()->delete();

                // Define the path to the voicemail folder
                $path = session('domain_name') . '/' . $item->voicemail_id;

                // Check if the directory exists and delete it
                if (Storage::disk('voicemail')->exists($path)) {
                    Storage::disk('voicemail')->deleteDirectory($path);
                }

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

            // Only add the Greetings tab if item_uuid exists and insert it in the second position
            if ($item_uuid) {
                $greetingsTab = [
                    'name' => 'Greetings',
                    'icon' => 'MusicalNoteIcon',
                    'slug' => 'greetings',
                ];

                // Insert Greetings tab at the second position (index 1)
                array_splice($navigation, 1, 0, [$greetingsTab]);
            }

            $voicemails =  $this->model::where($this->model->getTable() . '.domain_uuid', $domain_uuid)
                ->with(['extension' => function ($query) use ($domain_uuid) {
                    $query->select('extension_uuid', 'extension', 'effective_caller_id_name')
                        ->where('domain_uuid', $domain_uuid);
                }])
                ->select(
                    'voicemail_uuid',
                    'voicemail_id',
                    'voicemail_description',

                )
                ->orderBy('voicemail_id', 'asc')
                ->get();

            // Transform the collection into the desired array format
            $voicemailOptions = $voicemails->map(function ($voicemail) {
                return [
                    'value' => $voicemail->voicemail_uuid,
                    'name' => $voicemail->extension ? $voicemail->extension->name_formatted : $voicemail->voicemail_id . ' - Team Voicemail',
                ];
            })->toArray();


            $routes = [];

            // Check if item_uuid exists to find an existing model
            if ($item_uuid) {
                // Find existing voicemail by item_uuid
                $voicemail = Voicemails::with([
                    'voicemail_destinations' => function ($query) {
                        $query->select('voicemail_destination_uuid', 'voicemail_uuid', 'voicemail_uuid_copy');
                    },
                    'greetings' => function ($query) use ($domain_uuid) {
                        $query->select('voicemail_id', 'greeting_id', 'greeting_name', 'greeting_description')
                            ->where('domain_uuid', $domain_uuid);
                    }
                ])->where('voicemail_uuid', $item_uuid)->first();


                // If voicemail doesn't exist throw an error
                if (!$voicemail) {
                    throw new \Exception("Failed to fetch item details. Item not found");
                }

                // Transform greetings into the desired array format
                $greetingsArray = $voicemail->greetings
                    ->sortBy('greeting_id')
                    ->map(function ($greeting) {
                        return [
                            'value' => $greeting->greeting_id,
                            'name' => $greeting->greeting_name,
                            'description' => $greeting->greeting_description,
                        ];
                    })->toArray();

                // Add the default options at the beginning of the array
                array_unshift(
                    $greetingsArray,
                    ['value' => '0', 'name' => 'None'],
                    ['value' => '-1', 'name' => 'System Default']
                );

                $routes = array_merge($routes, [
                    'text_to_speech_route' => route('voicemails.textToSpeech', $voicemail),
                    'text_to_speech_route_for_name' => route('voicemails.textToSpeechForName', $voicemail),
                    'greeting_route' => route('voicemail.greeting', $voicemail),
                    'delete_greeting_route' => route('voicemails.deleteGreeting', $voicemail),
                    'upload_greeting_route' => route('voicemails.uploadGreeting', $voicemail),
                    'upload_greeting_route_for_name' => route('voicemails.uploadRecordedName', $voicemail),
                    'recorded_name_route' => route('voicemail.recorded_name', $voicemail),
                    'delete_recorded_name_route' => route('voicemails.deleteRecordedName', $voicemail),
                    'upload_recorded_name_route' => route('voicemails.uploadRecordedName', $voicemail),
                    'update_route' => route('voicemails.update', $voicemail),
                ]);
            } else {
                // Create a new voicemail if item_uuid is not provided
                $voicemail = $this->model;
                $voicemail->voicemail_id = $voicemail->generateUniqueSequenceNumber();
                $voicemail->voicemail_password = (get_domain_setting('password_complexity') == 'true')
                    ? str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT)
                    : $voicemail->voicemail_id;
                $voicemail->voicemail_file = get_domain_setting('voicemail_file');
                $voicemail->voicemail_local_after_email = get_domain_setting('keep_local');
                $voicemail->voicemail_transcription_enabled = get_domain_setting('transcription_enabled_default');
                $voicemail->voicemail_tutorial = 'false';
                $voicemail->voicemail_enabled = 'true';
                $voicemail->voicemail_recording_instructions = 'true';
            }

            $permissions = $this->getUserPermissions();
            // logger($permissions);

            // Extract voicemail_destinations and format it for frontend
            $voicemailCopies = [];
            if ($voicemail->voicemail_destinations) {
                $voicemailCopies = $voicemail->voicemail_destinations->map(function ($destination) {
                    return [
                        'value' => $destination->voicemail_uuid_copy, // Set the value to voicemail_uuid_copy
                        'name' => ''
                    ];
                })->toArray();
            }

            // Define the instructions for recording a voicemail greeting using a phone call
            $phoneCallInstructions = [
                'Dial <strong>*98</strong> from your phone.',
                'Enter the mailbox number and press <strong>#</strong>.',
                'Enter the voicemail password and press <strong>#</strong>.',
                'Press <strong>5</strong> for mailbox options.',
                'Press <strong>1</strong> to record an unavailable message.',
                'Choose a greeting number (1-9) to record, then follow the prompts.',
            ];

            // Define the instructions for recording a name using a phone call
            $phoneCallInstructionsForName = [
                'Dial <strong>*98</strong> from your phone.',
                'Enter the mailbox number and press <strong>#</strong>.',
                'Enter the voicemail password and press <strong>#</strong>.',
                'Press <strong>5</strong> for mailbox options.',
                'Press <strong>3</strong> to record your name, then follow the prompts.',
            ];

            $sampleMessage = 'Thank you for calling. Please, leave us a message and will call you back as soon as possible';

            $openAiService = app(\App\Services\OpenAIService::class);

            // Construct the itemOptions object
            $itemOptions = [
                'navigation' => $navigation,
                'all_voicemails' => $voicemailOptions,
                'voicemail' => $voicemail,
                'permissions' => $permissions,
                'voicemail_copies' => $voicemailCopies,
                'greetings' => $greetingsArray ?? null,
                'voices' => $openAiService->getVoices(),
                'default_voice' => isset($openAiService) && $openAiService ? $openAiService->getDefaultVoice() : null,
                'speeds' => $openAiService->getSpeeds(),
                'routes' => $routes,
                'phone_call_instructions' => $phoneCallInstructions,
                'phone_call_instructions_for_name' => $phoneCallInstructionsForName,
                'sample_message' => $sampleMessage,
                'recorded_name' => Storage::disk('voicemail')->exists(session('domain_name') . '/' . $voicemail->voicemail_id . '/recorded_name.wav') ? 'Custom recording' : 'System Default',
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
        $permissions['manage_voicemail_mobile_notifications'] = userCheckPermission('voicemail_sms_edit');
        $permissions['is_superadmin'] = isSuperAdmin();

        return $permissions;
    }

    public function textToSpeech(Voicemails $voicemail, OpenAIService $openAIService, TextToSpeechRequest $request)
    {
        $input = $request->input('input');
        $model = $request->input('model');
        $voice = $request->input('voice');
        $responseFormat = $request->input('response_format');
        $speed = $request->input('speed');

        try {
            $response = $openAIService->textToSpeech($model, $input, $voice, $responseFormat, $speed);

            $domainName = session('domain_name');

            // Delete all temp files
            $this->deleteTempFiles($domainName . '/' . $voicemail->voicemail_id);

            $fileName = 'temp_' . now()->format('Ymd_His') . '.' . $responseFormat; // Generates filename like temp_20240826_153045.wav
            $filePath = $domainName . '/' . $voicemail->voicemail_id . '/' . $fileName;

            // Save file to the voicemail disk with domain folder
            Storage::disk('voicemail')->put($filePath, $response);

            // Generate the file URL using the defined route
            $fileUrl = route('voicemail.file.serve', [
                'domain' => $domainName,
                'voicemail_id' => $voicemail->voicemail_id,
                'file' => $fileName,
            ]);

            // Generate the apply URL using the defined route
            $applyUrl = route('voicemail.file.apply');

            return response()->json([
                'success' => true,
                'file_url' => $fileUrl,
                'apply_url' => $applyUrl,
                'voicemail_uuid' => $voicemail->voicemail_uuid,
                'file_name' => $fileName,
            ]);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    public function textToSpeechForName(Voicemails $voicemail, OpenAIService $openAIService, TextToSpeechRequest $request)
    {
        $input = $request->input('input');
        $model = $request->input('model');
        $voice = $request->input('voice');
        $responseFormat = $request->input('response_format');
        $speed = $request->input('speed');

        try {
            $response = $openAIService->textToSpeech($model, $input, $voice, $responseFormat, $speed);

            $domainName = session('domain_name');

            // Delete all temp files
            $this->deleteTempFiles($domainName . '/' . $voicemail->voicemail_id);

            $fileName = 'temp_' . now()->format('Ymd_His') . '.' . $responseFormat; // Generates filename like temp_20240826_153045.wav
            $filePath = $domainName . '/' . $voicemail->voicemail_id . '/' . $fileName;

            // Save file to the voicemail disk with domain folder
            Storage::disk('voicemail')->put($filePath, $response);

            // Generate the file URL using the defined route
            $fileUrl = route('voicemail.file.serve', [
                'domain' => $domainName,
                'voicemail_id' => $voicemail->voicemail_id,
                'file' => $fileName,
            ]);

            // Generate the file URL using the defined route
            $applyUrl = route('voicemail.file.name.apply', [
                'domain' => $domainName,
                'voicemail' => $voicemail,
                'file' => $fileName,
            ]);

            return response()->json([
                'success' => true,
                'file_url' => $fileUrl,
                'apply_url' => $applyUrl,
            ]);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    public function serveVoicemailFile($domain, $voicemail_id, $file)
    {
        $filePath = "{$domain}/{$voicemail_id}/{$file}";

        if (!Storage::disk('voicemail')->exists($filePath)) {
            // File not found
            return response()->json([
                'success' => false,
                'errors' => ['server' => 'File not found']
            ], 500);  // 500 Internal Server Error for any other errors
        }

        // Check if the 'download' parameter is present and set to true
        $download = request()->query('download', false);

        if ($download) {
            // Serve the file as a download
            return response()->download(Storage::disk('voicemail')->path($filePath));
        }

        // Serve the file inline
        return response()->file(Storage::disk('voicemail')->path($filePath));
    }

    public function applyVoicemailFile()
    {
        try {
            $domain = session('domain_name');

            $voicemail = Voicemails::find(request('voicemail_uuid'));

            $filePath = $domain . "/" . $voicemail->voicemail_id . "/" . request('file_name');

            if (!Storage::disk('voicemail')->exists($filePath)) {
                abort(404); // File not found
            }

            if (!Storage::disk('voicemail')->exists($filePath)) {
                // File not found
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['File not found']]
                ], 500);  // 500 Internal Server Error for any other errors
            }

            // Step 3: Find the next greeting_id to use
            $existingIds = $voicemail->greetings()
                ->pluck('greeting_id')
                ->sort()
                ->toArray();

            $nextId = 1; // Start from 0 or your desired starting ID

            foreach ($existingIds as $id) {
                if ($id == $nextId) {
                    $nextId++;
                } else {
                    break; // Found a gap
                }
            }

            // Step 4: Generate new greeting_id and filename
            $newGreetingId = $nextId;
            $newFileName = "greeting_{$newGreetingId}.wav";

            // Step 5: Construct the new file path
            $newFilePath = "{$domain}/{$voicemail->voicemail_id}/{$newFileName}";

            // Step 6: Store the file with the new name (you might want to copy instead of move)
            if (!Storage::disk('voicemail')->move($filePath, $newFilePath)) {
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['Failed to save the file']]
                ], 500);
            }

            $sanitizedDescription =  preg_replace('/\s+/', ' ', htmlspecialchars(strip_tags(trim(request('input'))), ENT_QUOTES, 'UTF-8'));

            // Step 7: Save greeting info to the database
            $greeting = $voicemail->greetings()->create([
                'domain_uuid' => $voicemail->domain_uuid,
                'voicemail_id' => $voicemail->voicemail_id,
                'greeting_id' => $newGreetingId,
                'greeting_name' => "AI Greeting " . date('Ymd_His'),
                'greeting_filename' => $newFileName,
                'greeting_description' => $sanitizedDescription,

            ]);

            // Step 8: Update the voicemail table with the new greeting_id
            $voicemail->update([
                'greeting_id' => $newGreetingId
            ]);

            return response()->json([
                'success' => true,
                'greeting_id' => $newGreetingId,
                'greeting_name' => "AI Greeting " . date('Ymd_His'),
                'description' => $sanitizedDescription,
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

    public function applyVoicemailFileForName($domain, Voicemails $voicemail, $file)
    {
        try {
            $filePath = "{$domain}/{$voicemail->voicemail_id}/{$file}";

            if (!Storage::disk('voicemail')->exists($filePath)) {
                abort(404); // File not found
            }

            if (!Storage::disk('voicemail')->exists($filePath)) {
                // File not found
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['File not found']]
                ], 500);  // 500 Internal Server Error for any other errors
            }


            // Step 4: Generate new filename
            $newFileName = "recorded_name.wav";

            // Step 5: Construct the new file path
            $newFilePath = "{$domain}/{$voicemail->voicemail_id}/{$newFileName}";

            // Step 6: Store the file with the new name (you might want to copy instead of move)
            if (!Storage::disk('voicemail')->move($filePath, $newFilePath)) {
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['Failed to save the file']]
                ], 500);
            }

            return response()->json([
                'success' => true,
                'messages' => ['success' => 'Your AI-generated recorded name has been saved and successfully activated.']
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

    public function getVoicemailGreeting(Voicemails $voicemail)
    {

        try {
            // Step 1: Get the greeting_id from the request
            $greetingId = request('greeting_id');

            // Step 2: Fetch the greeting info from the database using the greeting_id and voicemail_id
            $greeting = $voicemail->greetings()
                ->where('greeting_id', $greetingId)
                ->first();

            // Check if the greeting exists
            if (!$greeting) {
                throw new \Exception('File not found');
            }

            // Generate the file URL using the defined route
            $fileUrl = route('voicemail.file.serve', [
                'domain' => session('domain_name'),
                'voicemail_id' => $voicemail->voicemail_id,
                'file' => $greeting->greeting_filename,
            ]);

            return response()->json([
                'success' => true,
                'file_url' => $fileUrl,
            ]);
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


    public function deleteGreeting(Voicemails $voicemail, Request $request)
    {
        try {
            $greetingId = $request->input('greeting_id');

            // Fetch the greeting to delete
            $greeting = $voicemail->greetings()->where('greeting_id', $greetingId)->first();

            if (!$greeting) {
                throw new \Exception('Greeting not found');
            }

            $filePath = session('domain_name') . '/' . $voicemail->voicemail_id . '/' . $greeting->greeting_filename;

            // Delete the greeting file from storage
            Storage::disk('voicemail')->delete($filePath);

            // Delete the greeting record from the database
            $greeting->delete();

            // Set voicemail greeting to System Default
            $voicemail->greeting_id = '-1';
            $voicemail->save();

            // Return a successful JSON response
            return response()->json([
                'success' => true,
                'messages' => ['success' => ['Greeting has been removed.']]
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json(['success' => false, 'errors' => ['server' => [$e->getMessage()]]], 500);
        }
    }

    public function uploadGreeting(Voicemails $voicemail, Request $request)
    {
        // Validate the file input
        $request->validate([
            'file' => 'required|mimes:wav,mp3,m4a|max:51200', // Limit to WAV or MP3, M4A files, max size 50MB
        ]);

        $file = $request->file('file');
        $domainName = session('domain_name');

        try {
            // Find the next available greeting_id
            $existingIds = $voicemail->greetings()
                ->pluck('greeting_id')
                ->sort()
                ->toArray();

            $nextId = 1; // Start from 1 or your desired starting ID
            foreach ($existingIds as $id) {
                if ($id == $nextId) {
                    $nextId++;
                } else {
                    break; // Found a gap
                }
            }

            // Generate a unique filename based on the current time
            $originalFileName = 'greeting_' . $nextId . '.' . $file->getClientOriginalExtension();
            $convertedFileName = 'greeting_' . $nextId . '.wav'; // Ensure final file is in WAV format

            // Save the original file to the voicemail directory
            Storage::disk('voicemail')->putFileAs($domainName . '/' . $voicemail->voicemail_id, $file, $originalFileName);

            // Save greeting info to the database
            $voicemail->greetings()->create([
                'domain_uuid' => $voicemail->domain_uuid,
                'voicemail_id' => $voicemail->voicemail_id,
                'greeting_id' => $nextId,
                'greeting_name' => "Uploaded File " . date('Ymd_His'),
                'greeting_filename' => $originalFileName, // Use original file name for the initial entry
                'greeting_description' => "Uploaded greeting {$nextId}",
            ]);

            // Update the voicemail table with the new greeting_id
            $voicemail->update([
                'greeting_id' => $nextId
            ]);

            // Define paths
            $originalFilePath = Storage::disk('voicemail')->path($domainName . '/' . $voicemail->voicemail_id . '/' . $originalFileName);
            $convertedFilePath = Storage::disk('voicemail')->path($domainName . '/' . $voicemail->voicemail_id . '/temp_' . $convertedFileName);

            // Convert the file to the recommended format using ffmpeg
            $process = Process::run([
                'ffmpeg',
                '-i',
                $originalFilePath,
                '-ac',
                '1',
                '-ar',
                '16000',
                '-ab',
                '256k',
                $convertedFilePath
            ]);

            if ($process->successful()) {
                Storage::disk('voicemail')->delete($domainName . '/' . $voicemail->voicemail_id . '/' . $originalFileName);
                Storage::disk('voicemail')->move($domainName . '/' . $voicemail->voicemail_id . '/temp_' . $convertedFileName, $domainName . '/' . $voicemail->voicemail_id . '/' . $convertedFileName);

                // Update the database with the converted file name
                $voicemail->greetings()
                    ->where('voicemail_id', $voicemail->voicemail_id)
                    ->where('greeting_id', $nextId)
                    ->update([
                        'greeting_filename' => $convertedFileName
                    ]);

                // Return a successful JSON response
                return response()->json([
                    'success' => true,
                    'greeting_id' => $nextId,
                    'greeting_name' => "Uploaded File " . date('Ymd_His'),
                    'messages' => ['success' => 'Your greeting has been uploaded and successfully activated.']
                ], 200);
            } else {
                // Log the error message if conversion failed
                logger('File conversion failed: ' . $process->errorOutput());

                // Return a JSON response indicating conversion failure
                return response()->json([
                    'success' => false,
                    'greeting_id' => $nextId,
                    'greeting_name' => "Uploaded File " . date('Ymd_His'),
                    'messages' => ['success' => 'File uploaded, but conversion failed. Original file has been retained.']
                ], 200); // Return 200 to indicate partial success
            }
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }
    }


    public function getRecordedName(Voicemails $voicemail)
    {
        try {
            $filePath = session('domain_name') . '/' . $voicemail->voicemail_id . '/recorded_name.wav';

            if (!Storage::disk('voicemail')->exists($filePath)) {
                throw new \Exception('File not found');
            }

            $fileUrl = route('voicemail.file.serve', [
                'domain' => session('domain_name'),
                'voicemail_id' => $voicemail->voicemail_id,
                'file' => 'recorded_name.wav',
            ]);

            return response()->json([
                'success' => true,
                'file_url' => $fileUrl,
            ]);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json(['success' => false, 'errors' => ['server' => [$e->getMessage()]]], 500);
        }
    }

    public function deleteRecordedName(Voicemails $voicemail)
    {
        try {
            $filePath = session('domain_name') . '/' . $voicemail->voicemail_id . '/recorded_name.wav';

            if (!Storage::disk('voicemail')->exists($filePath)) {
                throw new \Exception('File not found');
            }

            Storage::disk('voicemail')->delete($filePath);

            return response()->json([
                'success' => true,
                'messages' => ['success' => ['Recorded name has been deleted.']]
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json(['success' => false, 'errors' => ['server' => [$e->getMessage()]]], 500);
        }
    }

    public function uploadRecordedName(Request $request, Voicemails $voicemail)
    {
        // Validate the file input
        $request->validate([
            'file' => 'required|mimes:wav,mp3|max:10240', // Limit to WAV or MP3 files, max size 10MB
        ]);

        $file = $request->file('file');
        $domainName = session('domain_name');
        $originalFileName = 'recorded_name.' . $file->getClientOriginalExtension();
        $convertedFileName = 'recorded_name.wav'; // Ensure the final file is in WAV format

        try {
            // Check if the file already exists and delete it if so
            if (Storage::disk('voicemail')->exists($domainName . '/' . $voicemail->voicemail_id . '/' . $originalFileName)) {
                Storage::disk('voicemail')->delete($domainName . '/' . $voicemail->voicemail_id . '/' . $originalFileName);
            }

            // Save the original file to the voicemail directory
            Storage::disk('voicemail')->putFileAs($domainName . '/' . $voicemail->voicemail_id, $file, $originalFileName);

            // Define paths
            $originalFilePath = Storage::disk('voicemail')->path($domainName . '/' . $voicemail->voicemail_id . '/' . $originalFileName);
            $tempConvertedFilePath = Storage::disk('voicemail')->path($domainName . '/' . $voicemail->voicemail_id . '/temp_' . $convertedFileName);

            // Convert the file to the recommended format using ffmpeg
            $process = Process::run([
                'ffmpeg',
                '-y',
                '-i',
                $originalFilePath,
                '-ac',
                '1',
                '-ar',
                '16000',
                '-ab',
                '256k',
                $tempConvertedFilePath
            ]);

            if ($process->successful()) {
                // Rename tne file
                Storage::disk('voicemail')->exists($domainName . '/' . $voicemail->voicemail_id . '/' . $originalFileName);
                Storage::disk('voicemail')->move($domainName . '/' . $voicemail->voicemail_id . '/temp_' . $convertedFileName, $domainName . '/' . $voicemail->voicemail_id . '/' . $convertedFileName);

                // Return a successful JSON response
                return response()->json([
                    'success' => true,
                    'messages' => ['success' => 'Recorded name has been uploaded and successfully activated.']
                ], 200);
            } else {
                // Log the error message if conversion failed
                logger('File conversion failed: ' . $process->errorOutput());

                // Return a JSON response indicating conversion failure
                return response()->json([
                    'success' => true,
                    'messages' => ['success' => 'File uploaded, but conversion failed. Original file has been retained.']
                ], 200); // Return 200 to indicate partial success
            }
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
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
}
