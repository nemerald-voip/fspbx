<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Domain;
use App\Models\Extensions;
use App\Models\Voicemails;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\VoicemailGreetings;
use App\Models\VoicemailDestinations;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\StoreVoicemailRequest;

class VoicemailController extends Controller
{
    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Voicemails';
    protected $searchable = ['source', 'destination', 'message'];

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
                'data' => function () {
                    return $this->getData();
                },

                'routes' => [
                    'current_page' => route('voicemails.index'),
                    'store' => route('voicemails.store'),
                    // 'select_all' => route('messages.select.all'),
                    // 'bulk_delete' => route('messages.bulk.delete'),
                    // 'bulk_update' => route('messages.bulk.update'),
                    'item_options' => route('voicemails.item.options'),
                ]
            ]
        );

        // $data = array();

        // $searchString = $request->get('search');

        // $domain_uuid = Session::get('domain_uuid');

        // $voicemails = Voicemails::where('domain_uuid', $domain_uuid)->orderBy('voicemail_id', 'asc');
        // if ($searchString) {
        //     $voicemails->where(function ($query) use ($searchString) {
        //         $query->where('voicemail_id', 'ilike', '%' . str_replace('-', '', $searchString) . '%')
        //             ->orWhere('voicemail_mail_to', 'ilike', '%' . str_replace('-', '', $searchString) . '%')
        //             ->orWhere('voicemail_description', 'ilike', '%' . str_replace('-', '', $searchString) . '%');
        //     });
        // }

        // $voicemails = $voicemails->paginate(10)->onEachSide(1);

        // $data['searchString'] = $searchString;

        // $data['voicemails'] = $voicemails;
        // return view('layouts.voicemails.list')
        //     ->with($data)
        //     ->with('permissions', $permissions);
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
        $this->sortField = request()->get('sortField', 'voicemail_id'); // Default to 'voicemail_id'
        $this->sortOrder = request()->get('sortOrder', 'asc'); // Default to descending

        $data = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $data = $data->paginate($paginate);
        } else {
            $data = $data->get(); // This will return a collection
        }

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
        $data->with(['extension' => function ($query) use ($domainUuid) {
            $query->select('extension_uuid', 'extension', 'effective_caller_id_name')
                ->where('domain_uuid', $domainUuid);
        }]);


        $data->select(
            'voicemail_uuid',
            'voicemail_id',
            'voicemail_mail_to',
            'voicemail_enabled',
            'voicemail_description',

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
     * Show the create voicemail form.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */

    public function create()
    {
        if (!userCheckPermission('voicemail_add') || !userCheckPermission('voicemail_edit')) {
            return redirect('/');
        }

        $voicemail = new Voicemails();
        $voicemail->voicemail_enabled = "true";
        $voicemail->voicemail_transcription_enabled = get_domain_setting('transcription_enabled_default');

        $vm_unavailable_file_exists = Storage::disk('voicemail')
            ->exists(Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/greeting_1.wav');

        $vm_name_file_exists = Storage::disk('voicemail')
            ->exists(Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/recorded_name.wav');


        $data = [];
        $data['voicemail'] = $voicemail;
        $data['vm_unavailable_file_exists'] = $vm_unavailable_file_exists;
        $data['vm_name_file_exists'] = $vm_name_file_exists;


        return view('layouts.voicemails.createOrUpdate')->with($data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  guid  $user
     * @return \Illuminate\Http\Response
     */
    public function edit(Voicemails $voicemail)
    {
        //check permissions
        if (!userCheckPermission('voicemail_edit')) {
            return redirect('/');
        }

        $vm_unavailable_file_exists = Storage::disk('voicemail')
            ->exists(Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/greeting_1.wav');

        $vm_name_file_exists = Storage::disk('voicemail')
            ->exists(Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/recorded_name.wav');

        $data = array();
        $data['voicemail'] = $voicemail;
        $data['vm_unavailable_file_exists'] = $vm_unavailable_file_exists;
        $data['vm_name_file_exists'] = $vm_name_file_exists;
        $data['domain_voicemails'] = $voicemail->domain->voicemails;
        $data['voicemail_destinations'] = $voicemail->voicemail_destinations;

        return view('layouts.voicemails.createOrUpdate')->with($data);
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

    function update(Request $request, Voicemails $voicemail)
    {

        if (!userCheckPermission('voicemail_add') || !userCheckPermission('voicemail_edit')) {
            return redirect('/');
        }

        $attributes = [
            'voicemail_id' => 'voicemail extension number',
            'voicemail_password' => 'voicemail PIN',
            'greeting_id' => 'extension number',
            'voicemail_mail_to' => 'email address',
            'voicemail_enabled' => 'enabled',
            'voicemail_description' => 'description',
        ];

        $validator = Validator::make($request->all(), [
            'voicemail_id' => [
                'required',
                'numeric',
                Rule::unique('App\Models\Voicemails', 'voicemail_id')
                    ->ignore($request->voicemail_id, 'voicemail_id')
                    ->where('domain_uuid', Session::get('domain_uuid')),
            ],
            'voicemail_password' => 'numeric|digits_between:3,10',
            'voicemail_mail_to' => 'nullable|email:rfc,dns',
            'voicemail_enabled' => 'present',
            'voicemail_tutorial' => 'present',
            'voicemail_alternate_greet_id' => 'nullable|numeric',
            'voicemail_description' => 'nullable|string|max:100',
            'voicemail_transcription_enabled' => 'nullable',
            // 'voicemail_attach_file' => 'present',
            'voicemail_file' => 'present',
            'voicemail_local_after_email' => 'present',
            'extension' => "uuid",

        ], [], $attributes);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        // Retrieve the validated input assign all attributes
        $attributes = $validator->validated();
        if (isset($attributes['voicemail_local_after_email']) && $attributes['voicemail_local_after_email'] == "fasle") $attributes['voicemail_local_after_email'] = "true";
        // $attributes['domain_uuid'] = Session::get('domain_uuid');

        $attributes['update_date'] = date("Y-m-d H:i:s");
        $attributes['update_user'] = Session::get('user_uuid');
        $voicemail->update($attributes);


        //delete destinations before updating
        $voicemail->voicemail_destinations()->delete();

        if ($request->has('voicemail_destinations')) {
            $voicemail_destinations = $request->voicemail_destinations;
            //updating destinations
            foreach ($voicemail_destinations as $des) {
                $vm_des = new VoicemailDestinations();
                $vm_des->domain_uuid = Session::get('domain_uuid');
                // $vm_des->voicemail_destination_uuid=$des;
                $vm_des->voicemail_uuid_copy = $des;
                $vm_des->update_date = date("Y-m-d H:i:s");
                $vm_des->update_user = Session::get('user_uuid');
                $voicemail->voicemail_destinations()->save($vm_des);
            }
        }

        //clear the destinations session array
        if (isset($_SESSION['destinations']['array'])) {
            unset($_SESSION['destinations']['array']);
        }

        return response()->json([
            'voicemail' => $voicemail->voicemail_id,
            //'request' => $attributes,
            'status' => 'success',
            'message' => 'Voicemail has been updated'
        ]);
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
    public function getVoicemailGreeting(Voicemails $voicemail, string $filename)
    {
        $path = Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/' . $filename;

        if (!Storage::disk('voicemail')->exists($path)) abort(404);

        $file = Storage::disk('voicemail')->path($path);
        $type = Storage::disk('voicemail')->mimeType($path);

        $response = Response::make(file_get_contents($file), 200);
        $response->header("Content-Type", $type);
        return $response;
    }

    /**
     * Get voicemail greeting.
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadVoicemailGreeting(Voicemails $voicemail, string $filename)
    {

        $path = Session::get('domain_name') . '/' . $voicemail->voicemail_id . '/' . $filename;

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
    public function destroy($id)
    {
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
        $domain_uuid = request('domain_uuid') ?? session('domain_uuid');

        $navigation = [
            [
                'name' => 'Settings',
                'icon' => 'Cog6ToothIcon',
                'slug' => 'settings',
            ],
            [
                'name' => 'Greetings',
                'icon' => 'MusicalNoteIcon',
                'slug' => 'greetings',
            ],
            [
                'name' => 'Advanced',
                'icon' => 'AdjustmentsHorizontalIcon',
                'slug' => 'advanced',
            ],

        ];

        // Define the options for the 'extensions' field
        $extensions = Extensions::where('domain_uuid', $domain_uuid)
            ->get([
                'extension_uuid',
                'extension',
                'effective_caller_id_name',
            ]);

        $extensionOptions = [];
        // Loop through each extension and create an option
        foreach ($extensions as $extension) {
            $extensionOptions[] = [
                'value' => $extension->extension_uuid,
                'name' => $extension->name_formatted,
            ];
        }

        $voicemail = new Voicemails();
        $voicemail->voicemail_id = $voicemail->generateUniqueSequenceNumber();
        $voicemail->voicemail_password = $voicemail->voicemail_id;
        $voicemail->voicemail_file = get_domain_setting('voicemail_file');
        $voicemail->voicemail_local_after_email = get_domain_setting('keep_local');
        $voicemail->voicemail_transcription_enabled = get_domain_setting('transcription_enabled_default');
        $voicemail->voicemail_tutorial = 'false';
        $voicemail->voicemail_enabled = 'true';
        // logger($voicemail);

        $permissions = $this->getUserPermissions();
        // logger($permissions);

        // Construct the itemOptions object
        $itemOptions = [
            'navigation' => $navigation,
            'extensions' => $extensionOptions,
            'voicemail' => $voicemail,
            'permissions' => $permissions,
            // Define options for other fields as needed
        ];
        return $itemOptions;

        // $domainOptions = [];
        // // Loop through each domain and create an option
        // if (session('domains')) {
        //     foreach (session('domains') as $domain) {
        //         $domainOptions[] = [
        //             'value' => $domain->domain_uuid,
        //             'name' => $domain->domain_description,
        //         ];
        //     }
        // }

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
}
