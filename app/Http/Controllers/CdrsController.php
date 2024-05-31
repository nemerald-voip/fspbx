<?php

namespace App\Http\Controllers;

use App\Models\CDR;
use Inertia\Inertia;
use App\Models\Extensions;
use App\Exports\CdrsExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\CallCenterQueues;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CdrsController extends Controller
{

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Cdrs';
    protected $searchable = ['caller_id_name', 'caller_id_number', 'caller_destination', 'destination_number', 'call_uuid', 'cc_member_session_uuid'];

    public function __construct()
    {
        $this->model = new CDR();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // logger($request->all());
        // Check permissions
        if (!userCheckPermission("xml_cdr_view")) {
            return redirect('/');
        }


        if ($request->callUuid) {
            $callUuid = $request->callUuid;
        }


        if (isset($request->filterData['download']) && $request->filterData['download'] === 'true') {
            $cdrs = $this->getData(false);
            $export = new CdrsExport($cdrs);

            return Excel::download($export, 'call-detail-records.csv');
        }


        // return view('layouts.cdrs.index')->with($data);


        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },
                'startPeriod' => function () {
                    return $this->filters['startPeriod'];
                },
                'endPeriod' => function () {
                    return $this->filters['endPeriod'];
                },
                'timezone' => function () {
                    return $this->getTimezone();
                },
                'direction' => function () {
                    return isset($this->filters['direction']) ? $this->filters['direction'] : null;
                },
                'selectedEntity' => function () {
                    return isset($this->filters['entity']) ? $this->filters['entity'] : null;
                },
                'selectedEntityType' => function () {
                    return isset($this->filters['entityType']) ? $this->filters['entityType'] : null;
                },
                'recordingUrl' => Inertia::lazy(
                    fn () =>
                    $this->getRecordingUrl($callUuid)
                ),
                'entities' => Inertia::lazy(
                    fn () =>
                    $this->getEntities()
                ),
                'itemData' => Inertia::lazy(
                    fn () =>
                    $this->getItemData()
                ),
                'routes' => [
                    'current_page' => route('cdrs.index'),
                ]

            ]
        );
    }

    public function getItemData()
    {
        // Get item data
        $itemData = $this->model::where($this->model->getKeyName(), request('itemUuid'))
            ->select([
                'xml_cdr_uuid',
                'domain_uuid',
                'extension_uuid',
                'caller_id_name',
                'caller_id_number',
                'caller_destination',
                'start_epoch',
                'answer_epoch',
                'end_epoch',
                'duration',
                'call_flow',

            ])
            ->first();

        // logger($itemData);

        if (!$itemData) {
            return null;
        }

        $callFlowData = collect(json_decode($itemData->call_flow, true));

        // Add new rows for transfers
        $callFlowData = $this->handleCallFlowSteps($callFlowData);

        // logger($callFlowData->toArray());

        // Build the call flow summary
        $callFlowSummary = $callFlowData->map(function ($row) {
            return $this->buildSummaryItem($row);
        });

        // Format times
        $callFlowSummary = $this->formatTimes($callFlowSummary);


        logger($callFlowSummary);
    }

    /**
     * Handle transfers in the call flow array
     *
     * @param Collection $callFlowData
     * @return Collection
     */
    protected function handleCallFlowSteps($callFlowData)
    {
        $newRows = collect();

        $callFlowData->reduce(function ($carry, $row) use ($newRows) {
            $insertedNewRow = false;
        
            // Check if 'ring_group_uuid' exists in the 'application' array
            if (isset($row['extension']['application'])) {
                foreach ($row['extension']['application'] as $application) {
                    if (isset($application['@attributes']['app_data']) && strpos($application['@attributes']['app_data'], 'ring_group_uuid') !== false) {
                        // Extract the ring_group_uuid value
                        preg_match('/ring_group_uuid=([a-f0-9\-]+)/', $application['@attributes']['app_data'], $matches);
                        if (isset($matches[1]) && $row['times']['bridged_time'] != '0') {
                            $ringGroupUuid = $matches[1];
        
                            $newRow = [
                                'caller_profile' => [
                                    'destination_number' => $row['caller_profile']['destination_number'],
                                    'caller_id_name' => $row['caller_profile']['callee_id_name'],
                                    'caller_id_number' => $row['caller_profile']['caller_id_number']
                                ],
                                'times' => [
                                    'bridged_time' => '0',
                                    'created_time' => $row['times']['profile_created_time'],
                                    'answered_time' => '0',
                                    'progress_time' => $row['times']['profile_created_time'],
                                    'transfer_time' => $row['times']['answered_time'],
                                    'progress_media_time' => $row['times']['profile_created_time'],
                                    'hangup_time' => 0,
                                    'profile_created_time' => $row['times']['profile_created_time'],
                                    'profile_end_time' => $row['times']['bridged_time'] != '0' ? $row['times']['bridged_time'] : $row['times']['profile_end_time']
                                ]
                            ];
        
                            // Insert the new row right before the current row
                            $newRows->push($newRow);
                            $insertedNewRow = true;
        
                            // Adjust created time for current row
                            $row['times']['profile_created_time'] = $row['times']['bridged_time'] != '0' ? $row['times']['bridged_time'] : $row['times']['transfer_time'];
                            $row['times']['progress_media_time'] = $row['times']['bridged_time'] != '0' ? $row['times']['bridged_time'] : $row['times']['transfer_time'];
                        }
                        else {
                            $row['caller_profile']['callee_id_number'] = $row['caller_profile']['destination_number'];
                        }
                    }
                }
            }
        
            // Push the current row (updated or not) to the new collection
            $newRows->push($row);
        
            // Return the carry for reduce
            return $carry;
        }, $callFlowData);
        
        return $newRows;
        
    }



    /**
     * Format the times in the call flow array
     *
     * @param Collection $callFlowData
     * @return Collection
     */
    protected function formatTimes($callFlowData)
    {
        return $callFlowData->map(function ($row) {
            foreach ($row as $name => $value) {
                if (is_numeric($value) && $value > 0) {
                    $row[$name . '_stamp'] = Carbon::createFromTimestamp($value)->toDateTimeString();
                }
            }
            return $row;
        });
    }


    /**
     * Build a summary item for the call flow
     *
     * @param array $row
     * @return array
     */
    protected function buildSummaryItem(array $row): array
    {
        // $app = $this->findApp($row['caller_profile']['destination_number']);

        $profileCreatedEpoch = $this->formatTime($row['times']['profile_created_time']);
        $profileEndEpoch = $this->formatTime($row['times']['profile_end_time']);
        $profileTransferEpoch = $this->formatTime($row['times']['transfer_time']);

        return [
            // 'application_name' => $app['application'] ?? '',
            // 'application_label' => $this->getApplicationLabel($app['application'] ?? ''),
            // 'destination_uuid' => $app['uuid'] ?? '',
            // 'destination_name' => $app['name'] ?? '',
            'destination_number' => !empty($row['caller_profile']['callee_id_number']) ? $row['caller_profile']['callee_id_number'] : $row['caller_profile']['destination_number'],
            // 'destination_label' => $app['label'] ?? '',
            // 'destination_status' => $app['status'] ?? '',
            // 'destination_description' => $app['description'] ?? '',
            // 'start_epoch' => $profileCreatedEpoch,
            // 'end_epoch' => $profileEndEpoch,
            'bridged_time' => $row['times']['bridged_time'] == 0 ? 0 : $this->formatTime($row['times']['bridged_time']),
            'created_time' => $row['times']['created_time'] == 0 ? 0 : $this->formatTime($row['times']['created_time']),
            'answered_time' => $row['times']['answered_time'] == 0 ? 0 : $this->formatTime($row['times']['answered_time']),
            'progress_time' => $row['times']['progress_time'] == 0 ? 0 : $this->formatTime($row['times']['progress_time']),
            'transfer_time' => $row['times']['transfer_time'] == 0 ? 0 : $this->formatTime($row['times']['transfer_time']),
            'profile_created_time' => $row['times']['profile_created_time'] == 0 ? 0 : $this->formatTime($row['times']['profile_created_time']),
            'profile_end_time' => $row['times']['profile_end_time'] == 0 ? 0 : $this->formatTime($row['times']['profile_end_time']),
            'progress_media_time' => $row['times']['progress_media_time'] == 0 ? 0 : $this->formatTime($row['times']['progress_media_time']),
            'hangup_time' => $row['times']['hangup_time'] == 0 ? 0 : $this->formatTime($row['times']['hangup_time']),
            // 'start_stamp' => Carbon::createFromTimestamp($profileCreatedEpoch)->toDateTimeString(),
            // 'transfer_stamp' => Carbon::createFromTimestamp($profileTransferEpoch)->toDateTimeString(),
            // 'end_stamp' => Carbon::createFromTimestamp($profileEndEpoch)->toDateTimeString(),
            'duration_seconds' => $profileEndEpoch - $profileCreatedEpoch,
            'duration_formatted' => gmdate('G:i:s', $profileEndEpoch - $profileCreatedEpoch),
        ];
    }

    private function formatTime($time) {
        return (int) round($time / 1000000);
    }

    /**
     * Find the application details from the destination number
     *
     * @param string $destinationNumber
     * @return array
     */
    protected function findApp(string $destinationNumber): array
    {
        $destination = Destination::where('destination_number', $destinationNumber)->first();

        if ($destination) {
            return $destination->toArray();
        }

        return [];
    }

    /**
     * Get the application label
     *
     * @param string $application
     * @return string
     */
    protected function getApplicationLabel(string $application): string
    {

        return 'label';
    }




    public function getEntities()
    {
        $extensions = Extensions::where('domain_uuid', Session::get('domain_uuid'))
            ->selectRaw("
            extension_uuid as value, 
            CASE
                WHEN directory_first_name IS NOT NULL AND TRIM(directory_first_name) != '' 
                     AND directory_last_name IS NOT NULL AND TRIM(directory_last_name) != '' THEN CONCAT(directory_first_name, ' ', directory_last_name, ' - ', extension)
                WHEN directory_first_name IS NOT NULL AND TRIM(directory_first_name) != '' THEN CONCAT(directory_first_name, ' - ', extension)
                WHEN description IS NOT NULL AND TRIM(description) != '' THEN CONCAT(description, ' - ', extension)
                ELSE CONCAT(extension, ' - ', extension)
            END as name,
            'extension' as type
        ")
            ->get();


        $contactCenters = CallCenterQueues::where('domain_uuid', Session::get('domain_uuid'))
            ->select([
                'call_center_queue_uuid as value',
                'queue_name as name'
            ])
            ->selectRaw("'queue' as type")
            ->get();

        // Initialize an empty collection for entities
        $entities = collect();

        // Merge extensions into entities if extensions is not empty
        if (!$extensions->isEmpty()) {
            $entities = $entities->merge($extensions);
        }

        // Merge contactCenters into entities if contactCenters is not empty
        if (!$contactCenters->isEmpty()) {
            $entities = $entities->merge($contactCenters);
        }

        return $entities;
    }


    public function getRecordingUrl($callUuid)
    {
        try {
            $recording = CDR::where('xml_cdr_uuid', $callUuid)->select('xml_cdr_uuid', 'record_path', 'record_name')->firstOrFail();
            // You can use $call here
        } catch (ModelNotFoundException $e) {
            // Handle the case when the model is not found
            // For example, return a response or redirect
            return response()->json(['error' => 'Record not found'], 404);
        }

        //-----For local files------
        if ($recording->record_path != 'S3') {

            // $filePath = str_replace('/var/lib/freeswitch/recordings/', '', $recording->record_path . '/' . $recording->record_name);
            $filePath = $recording->record_path;
            $fileName = $recording->record_name;

            // Encrypt the file path
            $encryptedFilePath = encrypt($filePath);
            // Encrypt the file name
            $encryptedFileName = encrypt($fileName);

            // logger($encryptedFilePath);
            // logger($encryptedFileName);

            // Generate the URL
            $url = route('serve.recording', [
                'filePath' => $encryptedFilePath,
                'fileName' => $encryptedFileName,
            ]);

            if (isset($url)) return $url;
        }
        // -----End for local files----

        // -----For S3 files-----
        if ($recording->record_path == 'S3') {
            $setting = getS3Setting(Session::get('domain_uuid'));


            $disk = Storage::build([
                'driver' => 's3',
                'key' => $setting['key'],
                'secret' => $setting['secret'],
                'region' => $setting['region'],
                'bucket' => $setting['bucket'],
            ]);

            //Special case when recording name is empty. 
            if (empty($recording->record_name)) {
                // Check if archive recording is set
                if ($recording->archive_recording) {
                    $options = [
                        'ResponseContentDisposition' => 'attachment; filename="' . basename($recording->archive_recording->object_key) . '"'
                    ];
                    $url = $disk->temporaryUrl($recording->archive_recording->object_key, now()->addMinutes(10), $options);
                }
                if (isset($url)) return $url;
            }

            if (!empty($recording->record_name)) {
                $options = [
                    'ResponseContentDisposition' => 'attachment; filename="' . basename($recording->record_name) . '"'
                ];
                $url = $disk->temporaryUrl($recording->record_name, now()->addMinutes(10), $options);
                if (isset($url)) return $url;
            }

            // logger($url);
            if (isset($url)) return $url;
        }

        return null;
    }


    public function serveRecording($filePath, $fileName)
    {
        $filePath = decrypt($filePath); // Assuming the path is encrypted for security
        $fileName = decrypt($fileName); // Assuming the name is encrypted for security

        $disk = Storage::build([
            'driver' => 'local',
            'root' => $filePath,
        ]);

        if (!$disk->exists($fileName)) {
            return null;
        }

        // return response($fileContent, 200)->header('Content-Type', $mimeType);
        return response()->file($disk->path($fileName));
    }


    public function getData($paginate = 50)
    {
        // request('filterData.search')
        if (!empty(request('filterData.dateRange'))) {
            $startPeriod = Carbon::parse(request('filterData.dateRange')[0])->setTimeZone('UTC');
            $endPeriod = Carbon::parse(request('filterData.dateRange')[1])->setTimeZone('UTC');
        } else {
            $startPeriod = Carbon::now($this->getTimezone())->startOfDay()->setTimeZone('UTC');
            $endPeriod = Carbon::now($this->getTimezone())->endOfDay()->setTimeZone('UTC');
        }

        $this->filters = [
            'startPeriod' => $startPeriod,
            'endPeriod' => $endPeriod,
        ];

        // Check if showGlobal parameter is present and not empty
        if (!empty(request('filterData.showGlobal'))) {
            $this->filters['showGlobal'] = request('filterData.showGlobal') === 'true';
        } else {
            $this->filters['showGlobal'] = null;
        }

        // Check if direction parameter is present and not empty
        if (!empty(request('filterData.direction'))) {
            $this->filters['direction'] = request('filterData.direction');
        }

        // Check if search parameter is present and not empty
        if (!empty(request('filterData.search'))) {
            $this->filters['search'] = request('filterData.search');
        }

        // Check if search parameter is present and not empty

        // Check if search parameter is present and not empty
        if (!empty(request('filterData.entity'))) {
            $this->filters['entity'] = request('filterData.entity');
        }

        // Check if search parameter is present and not empty
        if (!empty(request('filterData.entityType'))) {
            $this->filters['entityType'] = request('filterData.entityType');
        }

        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'start_epoch'); // Default to 'start_epoch'
        $this->sortOrder = request()->get('sortOrder', 'desc'); // Default to ascending


        $cdrs = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $cdrs = $cdrs->paginate($paginate);
        } else {
            $cdrs = $cdrs->get(); // This will return a collection
        }

        $cdrs->transform(function ($cdr) {
            $cdr->start_date = $cdr->start_date;
            $cdr->start_time = $cdr->start_time;

            return $cdr;
        });
        return $cdrs;
    }

    public function builder($filters = [])
    {

        $data =  $this->model::query();

        if (isset($filters['showGlobal']) and $filters['showGlobal']) {
            $data->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description'); // Specify the fields you need
            }]);
            // Access domains through the session and filter by those domains
            $domainUuids = Session::get('domains')->pluck('domain_uuid');
            $data->whereHas('domain', function ($query) use ($domainUuids) {
                $query->whereIn($this->model->getTable() . '.domain_uuid', $domainUuids);
            });
        } else {
            // Directly filter by the session's domain_uuid
            $domainUuid = Session::get('domain_uuid');
            $data = $data->where($this->model->getTable() . '.domain_uuid', $domainUuid);
        }

        $data->select(
            'xml_cdr_uuid',
            'direction',
            'caller_id_name',
            'caller_id_number',
            'caller_destination',
            'destination_number',
            'domain_uuid',
            'extension_uuid',
            // 'sip_call_id',
            'source_number',
            // 'start_stamp',
            'start_epoch',
            // 'answer_stamp',
            // 'answer_epoch',
            'end_epoch',
            // 'end_stamp',
            'duration',
            'record_path',
            'record_name',
            // 'leg',
            // 'voicemail_message',
            // 'missed_call',
            // 'call_center_queue_uuid',
            // 'cc_side',
            // 'cc_queue_joined_epoch',
            // 'cc_queue',
            // 'cc_agent',
            // 'cc_agent_bridged',
            // 'cc_queue_answered_epoch',
            // 'cc_queue_terminated_epoch',
            // 'cc_queue_canceled_epoch',
            'cc_cancel_reason',
            'cc_cause',
            // 'waitsec',
            'hangup_cause',
            'hangup_cause_q850',
            'sip_hangup_disposition',
            'status'
        );

        //exclude legs that were not answered
        if (!userCheckPermission('xml_cdr_lose_race')) {
            $data->where('hangup_cause', '!=', 'LOSE_RACE');
        }

        foreach ($filters as $field => $value) {
            if (method_exists($this, $method = "filter" . ucfirst($field))) {
                $this->$method($data, $value);
            }
        }

        // Apply sorting
        $data->orderBy($this->sortField, $this->sortOrder);

        return $data;
    }

    protected function getTimezone()
    {

        if (!Cache::has(auth()->user()->user_uuid . '_' . Session::get('domain_uuid') . '_timeZone')) {
            $timezone = get_local_time_zone(Session::get('domain_uuid'));
            Cache::put(auth()->user()->user_uuid . Session::get('domain_uuid') .  '_timeZone', $timezone, 600);
        } else {
            $timezone = Cache::get(auth()->user()->user_uuid . '_' . Session::get('domain_uuid') . '_timeZone');
        }
        return $timezone;
    }

    protected function filterStartPeriod($query, $value)
    {
        $query->where('start_epoch', '>=', $value->getTimestamp());
    }

    protected function filterEndPeriod($query, $value)
    {
        $query->where('start_epoch', '<=', $value->getTimestamp());
    }

    protected function filterDirection($query, $value)
    {
        $query->where('direction', 'ilike', '%' . $value . '%');
    }

    protected function filterSearch($query, $value)
    {
        // Case-insensitive partial string search in the specified fields
        $searchable = $this->searchable;
        // Case-insensitive partial string search in the specified fields
        $query->where(function ($query) use ($value, $searchable) {
            foreach ($searchable as $field) {
                $query->orWhere($field, 'ilike', '%' . $value . '%');
            }
        });
    }

    protected function filterEntity($query, $value)
    {
        if (!isset($this->filters['entityType'])) {
            return;
        }
        switch ($this->filters['entityType']) {
            case 'queue':
                $query->where('call_center_queue_uuid', 'ilike', '%' . $value . '%');
                break;
            case 'extension':

                $extention = Extensions::find($value);
                // logger($extention);

                $query->where(function ($query) use ($extention) {
                    $query->where('extension_uuid', 'ilike', '%' . $extention->extension_uuid . '%')
                        ->orWhere('caller_id_number', $extention->extension)
                        ->orWhere('caller_destination', $extention->extension)
                        ->orWhere('source_number', $extention->extension)
                        ->orWhere('destination_number', $extention->extension);
                });


                break;
                // case 2:
                //     echo "i equals 2";
                //     break;
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
     * @param  \App\Models\CDR  $cDR
     * @return \Illuminate\Http\Response
     */
    public function show(CDR $cDR)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CDR  $cDR
     * @return \Illuminate\Http\Response
     */
    public function edit(CDR $cDR)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\CDR  $cDR
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CDR $cDR)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CDR  $cDR
     * @return \Illuminate\Http\Response
     */
    public function destroy(CDR $cDR)
    {
        //
    }
}
