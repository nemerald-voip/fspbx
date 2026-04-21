<?php

namespace App\Http\Controllers;

use App\Models\CDR;
use Inertia\Inertia;
use App\Jobs\ExportCdrs;
use App\Models\Extensions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\CallCenterQueues;
use App\Services\CdrDataService;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use App\Services\CallRecordingUrlService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\CallTranscription\CallTranscriptionService;

class CdrsController extends Controller
{

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Cdrs';
    protected $searchable = ['caller_id_name', 'caller_id_number', 'caller_destination', 'destination_number', 'sip_call_id', 'cc_member_session_uuid', 'status'];
    public $item_domain_uuid;
    protected $cdrDataService;

    public function __construct(CdrDataService $cdrDataService)
    {
        $this->cdrDataService = $cdrDataService;
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

        $domain_uuid = session('domain_uuid');
        $startPeriod = Carbon::now(get_local_time_zone($domain_uuid))->startOfDay()->setTimeZone('UTC');
        $endPeriod = Carbon::now(get_local_time_zone($domain_uuid))->endOfDay()->setTimeZone('UTC');

        if (!empty(request('filter.dateRange'))) {
            $startPeriod = Carbon::parse(request('filter.dateRange')[0])->setTimeZone('UTC');
            $endPeriod = Carbon::parse(request('filter.dateRange')[1])->setTimeZone('UTC');
        }

        return Inertia::render(
            $this->viewName,
            [
                'startPeriod' => function () use ($startPeriod) {
                    return $startPeriod;
                },
                'endPeriod' => function ()  use ($endPeriod) {
                    return $endPeriod;
                },
                'timezone' => function () {
                    return get_local_time_zone(session('domain_uuid'));
                },
                'recordingUrl' => Inertia::lazy(
                    fn() =>
                    $this->getRecordingUrl($callUuid)
                ),

                'routes' => [
                    'current_page' => route('cdrs.index'),
                    'export' => route('cdrs.export'),
                    'item_options' => route('cdrs.item.options'),
                    'data_route' => route('cdrs.data'),
                    'entities_route' => route('cdrs.entities'),
                    'call_recording_route' => route('cdrs.recording.options'),
                ],
                'permissions' => function () {
                    return $this->getPermissions();
                },

            ]
        );
    }

    public function getItemOptions()
    {
        try {

            // Get item data
            $item = $this->model::where($this->model->getKeyName(), request('item_uuid'))
                ->select([
                    'xml_cdr_uuid',
                    'domain_uuid',
                    'sip_call_id',
                    'extension_uuid',
                    'direction',
                    'caller_id_name',
                    'caller_id_number',
                    'caller_destination',
                    'start_epoch',
                    'answer_epoch',
                    'end_epoch',
                    'duration',
                    'billsec',
                    'waitsec',
                    'call_flow',
                    'voicemail_message',
                    'missed_call',
                    'hangup_cause',
                    'hangup_cause_q850',
                    'call_center_queue_uuid',
                    'cc_cancel_reason',
                    'cc_cause',
                    'sip_hangup_disposition',
                    'status',

                ])
                ->first();

            // logger($itemData);

            // If item doesn't exist throw and error 
            if (!$item) {
                throw new \Exception("Failed to fetch item details. Item not found");
            }

            $this->item_domain_uuid = $item->domain_uuid;

            $item->call_flow = $this->cdrDataService->buildCallFlowSummary($item);

            // logger($callFlowSummary->all());

            // Construct the itemOptions object
            $itemOptions = [
                'item' => $item,
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

    public function getRecordingOptions(CallRecordingUrlService $urlService)
    {
        try {
            // Get item data
            $item = $this->model::where($this->model->getKeyName(), request('item_uuid'))
                ->select([
                    'xml_cdr_uuid',
                    'extension_uuid',
                    'domain_uuid',
                    'sip_call_id',
                    'direction',
                    'caller_id_name',
                    'caller_id_number',
                    'caller_destination',
                    'start_epoch',
                    'duration',
                    'status',
                    'record_path',
                    'record_name',
                    'record_length',
                ])
                ->with([
                    'extension:extension_uuid,extension,effective_caller_id_name',
                ])
                ->with([
                    'callTranscription:uuid,xml_cdr_uuid,status,error_message,result_payload,summary_status,summary_error,summary_payload'
                ])
                ->first();

            // logger($item);

            // If item doesn't exist throw and error 
            if (!$item) {
                throw new \Exception("Failed to fetch item details. Item not found");
            }

            // Add a temporary URL for the audio file (S3 or Local)
            $urls = $urlService->urlsForCdr($item->xml_cdr_uuid, 600); // 10 minutes

            $routes = [
                'transcribe_route' => route('cdrs.recording.transcribe'),
                'summarize_route' => route('cdrs.recording.summarize'),
            ];

            // Is call transcription service enabled for this account
            $transcriptionService = app(CallTranscriptionService::class);
            $config = $transcriptionService->getCachedConfig($item->domain_uuid ?? null);
            $isCallTranscriptionServiceEnabled = (bool) ($config['enabled'] ?? false);

            // Build a lean transcription payload
            $transcription = null;
            if ($isCallTranscriptionServiceEnabled && $item->callTranscription) {
                $transcription = [
                    'uuid'         => $item->callTranscription->uuid,
                    'status'       => $item->callTranscription->status,
                    'error_message' => $item->callTranscription->error_message,
                    'text'         => data_get($item->callTranscription->result_payload, 'text'),
                    'utterances'   => data_get($item->callTranscription->result_payload, 'utterances', []),
                    'summary_status'       => $item->callTranscription->summary_status,
                    'summary'         => data_get($item->callTranscription->summary_payload, 'summary'),
                    'key_points'         => data_get($item->callTranscription->summary_payload, 'key_points'),
                    'action_items'         => data_get($item->callTranscription->summary_payload, 'action_items'),
                    'decisions_made'         => data_get($item->callTranscription->summary_payload, 'decisions_made'),
                    'compliance_flags'         => data_get($item->callTranscription->summary_payload, 'compliance_flags'),
                    'sentiment_overall'         => data_get($item->callTranscription->summary_payload, 'sentiment_overall'),

                ];

                // keep big blob out of the response entirely
                unset($item->callTranscription->result_payload);
            }

            // Construct the itemOptions object
            return response()->json([
                'item'        => $item,
                'audio_url'   => $urls['audio_url'],
                'download_url' => $urls['download_url'],
                'isCallTranscriptionServiceEnabled'       => $isCallTranscriptionServiceEnabled,
                'transcription' => $transcription,
                'filename'    => $urls['filename'],
                'routes' => $routes,
                'permissions' => $this->getUserPermissions(),
            ]);

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


    public function getEntities()
    {
        $domain_uuid = session('domain_uuid');
        $extensions = Extensions::where('domain_uuid', $domain_uuid)
            ->select('extension_uuid', 'extension', 'effective_caller_id_name')
            ->orderBy('extension', 'asc')
            ->get();


        $queues = CallCenterQueues::where('domain_uuid', $domain_uuid)
            ->select('call_center_queue_uuid', 'queue_extension', 'queue_name')
            ->orderBy('queue_extension', 'asc')
            ->get();

        $entities = [
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
                'groupLabel' => 'Contact Centers',
                'groupOptions' => $queues->map(function ($queue) {
                    return [
                        'value' => $queue->call_center_queue_uuid,
                        'label' => $queue->queue_name,
                        'destination' => $queue->queue_extension,
                        'type' => 'queue',
                    ];
                })->toArray(),
            ]
        ];
        // logger($entities);

        return $entities;
    }

    public function getRecordingUrl($callUuid)
    {
        try {
            $recording = CDR::where('xml_cdr_uuid', $callUuid)
                ->select('xml_cdr_uuid', 'record_path', 'record_name', 'domain_uuid')
                ->with('archive_recording')
                ->firstOrFail();
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
            // Efficient AWS settings retrieval (domain first, fallback to default)
            $requiredKeys = ['access_key', 'bucket_name', 'region', 'secret_key'];
            $domainUuid = $recording->domain_uuid;

            // Try domain settings first
            $domainSettings = \App\Models\DomainSettings::where('domain_uuid', $domainUuid)
                ->where('domain_setting_category', 'aws')
                ->whereIn('domain_setting_subcategory', $requiredKeys)
                ->where('domain_setting_enabled', true)
                ->get()
                ->pluck('domain_setting_value', 'domain_setting_subcategory')
                ->toArray();

            if (count(array_intersect(array_keys($domainSettings), $requiredKeys)) === count($requiredKeys)) {
                $s3Config = [
                    'driver' => 's3',
                    'key'    => $domainSettings['access_key'],
                    'secret' => $domainSettings['secret_key'],
                    'region' => $domainSettings['region'],
                    'bucket' => $domainSettings['bucket_name'],
                ];
            } else {
                // Fallback to default settings
                $defaultSettings = \App\Models\DefaultSettings::where('default_setting_category', 'aws')
                    ->whereIn('default_setting_subcategory', $requiredKeys)
                    ->where('default_setting_enabled', true)
                    ->get()
                    ->pluck('default_setting_value', 'default_setting_subcategory')
                    ->toArray();

                if (count(array_intersect(array_keys($defaultSettings), $requiredKeys)) === count($requiredKeys)) {
                    $s3Config = [
                        'driver' => 's3',
                        'key'    => $defaultSettings['access_key'],
                        'secret' => $defaultSettings['secret_key'],
                        'region' => $defaultSettings['region'],
                        'bucket' => $defaultSettings['bucket_name'],
                    ];
                } else {
                    // No valid S3 config found
                    return null;
                }
            }

            $disk = Storage::build($s3Config);

            // Try archive_recording object_key first if record_name is empty
            if (empty($recording->record_name) && $recording->archive_recording) {
                $objectKey = $recording->archive_recording->object_key;
                $fileName = basename($objectKey);
            } else {
                $objectKey = $recording->record_name;
                $fileName = basename($objectKey);
            }

            if (!empty($objectKey)) {
                $options = [
                    'ResponseContentDisposition' => 'attachment; filename="' . $fileName . '"'
                ];
                $url = $disk->temporaryUrl($objectKey, now()->addMinutes(10), $options);

                return $url;
            }
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


    //Most of this function has been moved to CdrDataService service container
    public function getData()
    {
        $params = request()->all();

        // --- Normalize search term (numbers-only cases) ---
        foreach (['filter.search', 'filter.searchTerm', 'filterData.search'] as $sk) {
            $raw = data_get($params, $sk);
            if ($raw === null || $raw === '') continue;

            data_set($params, $sk, $this->cdrDataService->normalizeSearchTerm($raw));
            break;
        }
        // --- end normalize search ---

        $params['paginate'] = 50;
        $domain_uuid = session('domain_uuid');
        $params['domain_uuid'] = $domain_uuid;

        if (!empty(request('filter.dateRange'))) {
            $startPeriod = Carbon::parse(request('filter.dateRange')[0])->setTimeZone('UTC');
            $endPeriod = Carbon::parse(request('filter.dateRange')[1])->setTimeZone('UTC');
        }

        $params['filter']['startPeriod'] = $startPeriod->getTimestamp();
        $params['filter']['endPeriod'] = $endPeriod->getTimestamp();

        unset(
            $params['filter']['entityType'],
            $params['filter']['dateRange'],
        );

        $this->filters = [
            'startPeriod' => $startPeriod,
            'endPeriod' => $endPeriod,
            'showGlobal' => request('filterData.showGlobal') ?? null,
        ];

        return $this->cdrDataService->getData($params);
    }

    /**
     * Get all items
     *
     * @return \Illuminate\Http\Response
     */
    public function export()
    {
        try {
            $params = request()->all();

            // --- Normalize search term (numbers-only cases) ---
            foreach (['filter.search', 'filter.searchTerm', 'filterData.search'] as $sk) {
                $raw = data_get($params, $sk);
                if ($raw === null || $raw === '') continue;

                data_set($params, $sk, $this->cdrDataService->normalizeSearchTerm($raw));
                break;
            }
            // --- end normalize search ---

            $params['paginate'] = 0;
            $domain_uuid = session('domain_uuid');
            $params['domain_uuid'] = $domain_uuid;

            if (!empty(request('filter.dateRange'))) {
                $startPeriod = Carbon::parse(request('filter.dateRange')[0])->setTimeZone('UTC');
                $endPeriod = Carbon::parse(request('filter.dateRange')[1])->setTimeZone('UTC');
            }

            $params['filter']['startPeriod'] = $startPeriod->getTimestamp();
            $params['filter']['endPeriod'] = $endPeriod->getTimestamp();

            unset(
                $params['filter']['entityType'],
                $params['filter']['dateRange'],
            );
            $params['user_email'] = auth()->user()->user_email;

            // $cdrs = $this->getData(false); // returns lazy collection

            //            ExportCdrs::dispatch($params, $this->cdrDataService);
            ExportCdrs::dispatch($params);

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Report is being generated in the background. We\'ll email you a link when it\'s ready to download.']],
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to export items']]
            ], 500); // 500 Internal Server Error for any other errors
        }

        return response()->json([
            'success' => false,
            'errors' => ['server' => ['Failed to export']]
        ], 500); // 500 Internal Server Error for any other errors
    }

    public function getPermissions()
    {
        $permissions = [];
        $permissions['all_cdr_view'] = userCheckPermission('xml_cdr_domain');
        $permissions['cdr_mos_view'] = userCheckPermission('xml_cdr_mos');
        $permissions['call_recording_play'] = userCheckPermission('call_recording_play');
        $permissions['call_recording_download'] = userCheckPermission('call_recording_download');
        $permissions['transcription_summary'] = userCheckPermission('transcription_summary');

        // Is call transcription service enabled for this account
        $transcriptionService = app(CallTranscriptionService::class);
        $config = $transcriptionService->getCachedConfig(session('domain_uuid') ?? null);
        $isCallTranscriptionServiceEnabled = (bool) ($config['enabled'] ?? false);

        $permissions['search_sentiment'] = userCheckPermission('xml_cdr_search_sentiment') && $isCallTranscriptionServiceEnabled;

        return $permissions;
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

    public function getUserPermissions()
    {
        $permissions = [];
        $permissions['transcription_view'] = userCheckPermission('transcription_view');
        $permissions['transcription_read'] = userCheckPermission('transcription_read');
        $permissions['transcription_create'] = userCheckPermission('transcription_create');
        $permissions['transcription_summary'] = userCheckPermission('transcription_summary');
        $permissions['xml_cdr_search_sentiment'] = userCheckPermission('xml_cdr_search_sentiment');

        return $permissions;
    }
}
