<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\VoicemailMessages;
use Illuminate\Support\Facades\DB;
use App\Services\FreeswitchEslService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use libphonenumber\NumberParseException;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class VoicemailMessagesController extends Controller
{
    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'VoicemailMessages';
    protected $searchable = ['caller_id_name', 'caller_id_number'];

    public function __construct()
    {
        $this->model = new VoicemailMessages();
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Check permissions
        if (!userCheckPermission("voicemail_message_view")) {
            return redirect('/');
        }

        $voicemail_uuid = request()->route('voicemail');

        return Inertia::render(
            $this->viewName,
            [
                'voicemail_uuid' => $voicemail_uuid,
                'data' => function () {
                    return $this->getData();
                },

                'routes' => [
                    'current_page' => route('voicemails.messages.index', request()->route('voicemail')),
                    'get_message_url' => route('voicemail.message.url'),
                    'select_all' => route('voicemails.messages.select.all'),
                    'bulk_delete' => route('voicemails.messages.bulk.delete'),
                ],
                'permissions' => function () {
                    return $this->getUserPermissions();
                },
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
        $this->sortField = request()->get('sortField', 'created_epoch'); // Default to 'created_epoch'
        $this->sortOrder = request()->get('sortOrder', 'desc'); // Default to descending

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
        $voicemail_uuid = request()->route('voicemail');

        $data =  $this->model::query();
        $domainUuid = session('domain_uuid');
        $data = $data->where($this->model->getTable() . '.domain_uuid', $domainUuid)
            ->where('voicemail_uuid', $voicemail_uuid);

        // $data->with(['voicemail' => function ($query) {
        //     $query->select('voicemail_uuid', 'voicemail_id');
        // }]);


        $data->select(
            'voicemail_message_uuid',
            'voicemail_uuid',
            'created_epoch',
            'read_epoch',
            'caller_id_name',
            'caller_id_number',
            'message_length',
            'message_status',
            'message_priority',
            'message_transcription',

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

    /**
     * Get voicemail message.
     *
     * @return \Illuminate\Http\Response
     */
    public function getVoicemailMessage(VoicemailMessages $message)
    {
        $path = Session::get('domain_name') . '/' . $message->voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.wav';

        if (!Storage::disk('voicemail')->exists($path)) {
            $path = Session::get('domain_name') . '/' . $message->voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.mp3';
            if (!Storage::disk('voicemail')->exists($path)) {
                abort(404);
            }
        }

        $file = Storage::disk('voicemail')->path($path);
        $type = Storage::disk('voicemail')->mimeType($path);

        $response = Response::make(file_get_contents($file), 200);
        $response->header("Content-Type", $type);
        return $response;
    }

    /**
     * Download voicemail message.
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadVoicemailMessage(VoicemailMessages $message)
    {

        $path = Session::get('domain_name') . '/' . $message->voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.wav';

        if (!Storage::disk('voicemail')->exists($path)) {
            $path = Session::get('domain_name') . '/' . $message->voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.mp3';
            if (!Storage::disk('voicemail')->exists($path)) {
                abort(404);
            }
        }

        $file = Storage::disk('voicemail')->path($path);
        $type = Storage::disk('voicemail')->mimeType($path);
        $headers = array(
            'Content-Type: ' . $type,
        );

        $response = Response::download($file, basename($file), $headers);

        return $response;
    }


    public function getVoicemailMessageUrl()
    {
        try {
            // Step 1: Get the voicemail_message_uuid from the request
            $message = VoicemailMessages::with(['voicemail' => function ($query) {
                $query->select('voicemail_uuid', 'voicemail_id');
            }])
                ->find(request('voicemail_message_uuid'));

            // Check if the greeting exists
            if (!$message) {
                throw new \Exception('File not found');
            }


            // Step 2: Check for the existence of .wav and .mp3 files
            $domainName = session('domain_name');
            $voicemailId = $message->voicemail->voicemail_id;
            $messageUuid = $message->voicemail_message_uuid;

            $wavPath = $domainName . '/' . $voicemailId . '/msg_' . $messageUuid . '.wav';
            $mp3Path = $domainName . '/' . $voicemailId . '/msg_' . $messageUuid . '.mp3';

            if (Storage::disk('voicemail')->exists($wavPath)) {
                $fileName = 'msg_' . $messageUuid . '.wav';
            } elseif (Storage::disk('voicemail')->exists($mp3Path)) {
                $fileName = 'msg_' . $messageUuid . '.mp3';
            } else {
                throw new \Exception('No file found');
            }

            // Generate the file URL using the defined route
            $fileUrl = route('voicemail.file.serve', [
                'domain' => session('domain_name'),
                'voicemail_id' => $message->voicemail->voicemail_id,
                'file' => $fileName,
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


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    public function destroy(VoicemailMessages $message)
    {

        try {
            // throw new \Exception;

            // Start a database transaction to ensure atomic operations
            DB::beginTransaction();

            $voicemail = $message->voicemail;

            // Define the path to the voicemail file
            $path = session('domain_name') . '/' . $voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.wav';
            // Check if the file exists and delete it
            if (Storage::disk('voicemail')->exists($path)) {
                Storage::disk('voicemail')->delete($path);
            }

            $path = session('domain_name') . '/' . $voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.mp3';
            // Check if the file exists and delete it
            if (Storage::disk('voicemail')->exists($path)) {
                Storage::disk('voicemail')->delete($path);
            }
            // Finally, delete the voicemail itself
            $message->delete();

            // Commit the transaction
            DB::commit();

            // Update WMI subscription
            $freeSwitchService = new FreeswitchEslService();
            $command = sprintf(
                "bgapi luarun app.lua voicemail mwi '%s'@'%s'",
                $message->voicemail->voicemail_id,
                session('domain_name')
            );
            $result = $freeSwitchService->executeCommand($command);


            return redirect()->back()->with('message', ['server' => ['Item deleted']]);
        } catch (\Exception $e) {
            // Rollback the transaction if an error occurs
            DB::rollBack();
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return redirect()->back()->with('error', ['server' => ['Server returned an error while deleting this item']]);
        }
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy_old($id)
    {
        $message = VoicemailMessages::findOrFail($id);

        if (isset($message)) {
            $deleted = $message->delete();

            $path = Session::get('domain_name') . '/' . $message->voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.wav';

            if (!Storage::disk('voicemail')->exists($path)) {
                $path = Session::get('domain_name') . '/' . $message->voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.mp3';
            }

            $file = Storage::disk('voicemail')->delete($path);

            // Send notifications to subscribes phones
            $fp = event_socket_create(
                config('eventsocket.ip'),
                config('eventsocket.port'),
                config('eventsocket.password')
            );
            if ($fp) {
                $switch_cmd = "luarun app.lua voicemail mwi " . $message->voicemail->voicemail_id . "@" . Session::get('domain_name');
                $switch_result = event_socket_request($fp, 'api ' . $switch_cmd);
            }

            if ($deleted) {
                return response()->json([
                    'status' => 200,
                    'success' => [
                        'message' => 'Selected vocemail messages have been deleted'
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => 'There was an error deleting selected voicemail messages'
                    ]
                ]);
            }
        }
    }

    public function selectAll()
    {
        try {
            $params = request()->all();

            $domain_uuid = session('domain_uuid');
            $params['domain_uuid'] = $domain_uuid;

            if (!empty(data_get($params, 'filter.dateRange'))) {
                $startTs = Carbon::parse(data_get($params, 'filter.dateRange.0'))
                    ->getTimestamp();

                $endTs = Carbon::parse(data_get($params, 'filter.dateRange.1'))
                    ->getTimestamp();

                $params['filter']['startPeriod'] = $startTs;
                $params['filter']['endPeriod']   = $endTs;

                unset($params['filter']['dateRange']);
            }

            $data = QueryBuilder::for(VoicemailMessages::class, request()->merge($params))
                ->select([
                    'voicemail_message_uuid',
                    'domain_uuid',

                ])
                ->allowedFilters([
                    AllowedFilter::exact('voicemail_uuid'),
                    // AllowedFilter::callback('startPeriod', function ($query, $value) {
                    //     $query->where('fax_epoch', '>=', $value);
                    // }),
                    // AllowedFilter::callback('endPeriod', function ($query, $value) {
                    //     $query->where('fax_epoch', '<=', $value);
                    // }),

                    AllowedFilter::callback('search', function ($query, $value) {
                        $query->where(function ($q) use ($value) {
                            $q->where('caller_id_name', 'ilike', "%{$value}%")
                                ->orWhere('caller_id_number', 'ilike', "%{$value}%");
                        });
                    }),
                ])
                ->pluck('voicemail_message_uuid');

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $data,
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500); // 500 Internal Server Error for any other errors
        }

        return response()->json([
            'success' => false,
            'errors' => ['server' => ['Failed to select all items']]
        ], 500); // 500 Internal Server Error for any other errors
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  Devices  $device
     * 
     */
    public function bulkDelete()
    {
        try {
            // Begin Transaction
            DB::beginTransaction();

            // Retrieve all items at once 
            $items = $this->model::whereIn($this->model->getKeyName(), request('items'))
                ->with('voicemail') // Ensure to load the related voicemail data
                ->get([$this->model->getKeyName(), 'voicemail_uuid']);

            $voicemailId = $items->first()->voicemail->voicemail_id;

            foreach ($items as $item) {
                $voicemail = $item->voicemail;

                // Define the paths to the voicemail files
                $wavPath = session('domain_name') . '/' . $voicemail->voicemail_id . '/msg_' . $item->voicemail_message_uuid . '.wav';
                $mp3Path = session('domain_name') . '/' . $voicemail->voicemail_id . '/msg_' . $item->voicemail_message_uuid . '.mp3';

                // Check if the .wav file exists and delete it
                if (Storage::disk('voicemail')->exists($wavPath)) {
                    Storage::disk('voicemail')->delete($wavPath);
                }

                // Check if the .mp3 file exists and delete it
                if (Storage::disk('voicemail')->exists($mp3Path)) {
                    Storage::disk('voicemail')->delete($mp3Path);
                }
                // Delete the item 
                $item->delete();
            }

            // Commit Transaction
            DB::commit();

            // Update WMI subscription
            $freeSwitchService = new FreeswitchEslService();
            $command = sprintf(
                "bgapi luarun app.lua voicemail mwi '%s'@'%s'",
                $voicemailId,
                session('domain_name')
            );
            $result = $freeSwitchService->executeCommand($command);


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

    public function getUserPermissions()
    {
        $permissions = [];
        $permissions['voicemail_message_destroy'] = userCheckPermission('voicemail_message_delete');

        return $permissions;
    }
}
