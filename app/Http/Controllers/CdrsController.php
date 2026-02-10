<?php

namespace App\Http\Controllers;

use App\Models\CDR;
use Inertia\Inertia;
use App\Jobs\ExportCdrs;
use App\Models\Dialplans;
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

            // $callFlowData = collect(json_decode($item->call_flow, true));

            // Get the main call flow
            $mainCallFlowData = collect(json_decode($item->call_flow, true));

            // Initialize a collection to hold the combined call flow data
            $combinedCallFlowData = $mainCallFlowData;

            // Check if the call is a queue call (call_center_queue_uuid is not null)
            if (!empty($item->call_center_queue_uuid)) {
                // Fetch related queue calls and their call_flow if this is a queue call
                $relatedCalls = $item->relatedQueueCalls()
                    ->where('domain_uuid', $item->domain_uuid)
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
                    ->get();

                // Loop through each related queue call and merge its call_flow into the combined call flow data
                foreach ($relatedCalls as $relatedCall) {
                    $relatedCallFlow = collect(json_decode($relatedCall->call_flow, true));
                    // Iterate through each flow step to insert the call_disposition
                    $relatedCallFlow = $relatedCallFlow->map(function ($flow) use ($relatedCall) {
                        // Ensure the 'times' array exists before adding call_disposition
                        if (isset($flow['times'])) {
                            $flow['times']['call_disposition'] = $relatedCall->call_disposition;
                        }
                        return $flow;
                    });

                    // logger($relatedCallFlow->toArray());
                    $combinedCallFlowData = $combinedCallFlowData->merge($relatedCallFlow);
                }
            }

            // Check if there are any other related calls 
            // Fetch related calls and their call_flow

            $relatedCalls = $item->relatedRingGroupCalls()
                ->where('domain_uuid', $item->domain_uuid)
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
                ->get();

            // Loop through each related call and merge its call_flow into the combined call flow data
            foreach ($relatedCalls as $relatedCall) {
                $relatedCallFlow = collect(json_decode($relatedCall->call_flow, true));
                // Iterate through each flow step to insert the call_disposition
                $relatedCallFlow = $relatedCallFlow->map(function ($flow) use ($relatedCall) {
                    // Ensure the 'times' array exists before adding call_disposition
                    if (isset($flow['times'])) {
                        $flow['times']['call_disposition'] = $relatedCall->call_disposition;
                    }
                    return $flow;
                });

                // logger($relatedCallFlow->toArray());
                $combinedCallFlowData = $combinedCallFlowData->merge($relatedCallFlow);
            }

            // logger($combinedCallFlowData->toArray());

            // Add new rows for transfers
            $combinedCallFlowData = $this->handleCallFlowSteps($combinedCallFlowData);

            // Build the call flow summary
            $callFlowSummary = $combinedCallFlowData->map(function ($row) {
                return $this->buildSummaryItem($row);
            });

            // Sort the call flow summary by profile_created_time
            $callFlowSummary = $callFlowSummary->sortBy('profile_created_time')->values();

            // logger($callFlowSummary->toArray());

            //calculate the time line and format it
            $startEpoch = $item->start_epoch;
            $direction = $item->direction;
            $callFlowSummary = $callFlowSummary->map(function ($row) use ($startEpoch, $direction) {
                $timeDifference = $row['profile_created_time'] - $startEpoch;
                $row['time_line'] = sprintf('%02d:%02d', floor($timeDifference / 60), $timeDifference % 60); // Human-readable format
                if ($direction == "outbound") {
                    $row['dialplan_app'] = "Outbound Call";
                }
                return $row;
            });

            // Format times
            $callFlowSummary = $this->formatTimes($callFlowSummary);

            // logger($callFlowSummary->toArray());

            // Get Dialplan App details
            $callFlowSummary = $callFlowSummary->map(function ($row) {
                $row = $this->getAppDetails($row);

                return $row;
            });

            // logger($callFlowSummary->toArray());

            $item->call_flow = $callFlowSummary;

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

    /**
     * Get app details associated with call flow step
     *
     */
    public function getAppDetails($row)
    {
        // Convert to E164 format if this is a valid number
        $destination = formatPhoneNumber($row['destination_number'], "US", 0); // 0 is E164 format

        // Check if the number starts with '+1' and remove it if present
        if (strpos($destination, '+1') === 0) {
            $bareNumber = substr($destination, 2);
        } else {
            $bareNumber = $destination;
        }

        $dialplan = Dialplans::where('dialplan_context', $row['context'])
            ->where(function ($query) use ($destination, $bareNumber) {
                $query->where('dialplan_number', $destination)
                    ->orWhere('dialplan_number', '=', $bareNumber)
                    ->orWhere('dialplan_number', '=', '1' . $bareNumber);
            })
            ->where('dialplan_enabled', 'true')
            ->select(
                'dialplan_uuid',
                'dialplan_name',
                'dialplan_number',
                'dialplan_xml',
                'dialplan_description',
            )
            ->first();

        if ($dialplan) {
            $patterns = [
                'ring_group_uuid' => [
                    'pattern' => '/ring_group_uuid=([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})/',
                    'app' => 'Ring Group',
                ],
                'ivr_menu_uuid' => [
                    'pattern' => '/ivr_menu_uuid=([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})/',
                    'app' => 'Auto Receptionist',
                ],
                'call_center_queue_uuid' => [
                    'pattern' => '/call_center_queue_uuid=([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})/',
                    'app' => 'Contact Center Queue',
                ],
                'call_direction_inbound' => [
                    'pattern' => '/call_direction=inbound/',
                    'app' => 'Inbound Call',
                ],
                'date_time' => [
                    'pattern' => '/\b(?:year|yday|mon|mday|week|mweek|wday|hour|minute|minute-of-day|time-of-day|date-time)=/',
                    'app' => 'Schedule',
                ],
                'application_rxfax' => [
                    'pattern' => '/application="rxfax"/',
                    'app' => 'Virtual Fax',
                ],
                'call_flow_uuid' => [
                    'pattern' => '/call_flow_uuid=([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})/',
                    'app' => 'Call Flow',
                ],
            ];

            foreach ($patterns as $key => $info) {
                if (preg_match($info['pattern'], $dialplan->dialplan_xml, $matches)) {
                    $row['dialplan_app'] = $info['app'];
                    $row['dialplan_name'] = $dialplan->dialplan_name;
                    $row['dialplan_description'] = $dialplan->dialplan_description;
                    break; // Stop checking after the first match
                }
            }

            return $row;
        }

        // Check if destination is Park
        if (strpos($row['destination_number'], "park+") !== false) {
            $row['dialplan_app'] = "Park";
            $row['dialplan_name'] = substr($row['destination_number'], 6);
            $row['dialplan_description'] = '';
            return $row;
        }

        // Check if destination is voicemail
        if ((substr($row['destination_number'], 0, 3) == '*99') !== false) {
            $row['dialplan_app'] = "Voicemail";
            $row['dialplan_name'] = substr($row['destination_number'], 3);
            $row['dialplan_description'] = '';
            return $row;
        }

        // Check if destination is intercept
        if ((substr($row['destination_number'], 0, 3) == '*97') !== false) {
            // Use regex to capture the digits after *97 up to ^ and everything after ^
            if (preg_match('/\*97(\d+)\^(.+)/', $row['destination_number'], $matches)) {
                $interceptedExt = $matches[1];
                $intereceptedByExt = $matches[2];

                $row['dialplan_app'] = "Call Intercept " . $interceptedExt;

                // Check if intereceptedByExt is extension
                $extension = Extensions::where('domain_uuid', $this->item_domain_uuid)
                    ->where('extension', $intereceptedByExt)
                    ->first();

                if ($extension) {
                    $row['dialplan_name'] = $extension->effective_caller_id_name . ' (' . $intereceptedByExt .  ')';
                } else {
                    $row['dialplan_name'] = null;
                }
                $row['dialplan_description'] = '';

                return $row;
            }
        }

        // Check if destination is extension
        $extension = Extensions::where('domain_uuid', $this->item_domain_uuid)
            ->where('extension', $row['destination_number'])
            ->first();

        if ($extension) {
            $row['dialplan_app'] = "Extension";
            $row['dialplan_name'] = $extension->effective_caller_id_name;
            $row['dialplan_description'] = $extension->description;
            return $row;
        }

        $row['dialplan_app'] = "Misc. Destination";
        $row['dialplan_name'] = $row['destination_number'];
        $row['dialplan_description'] = null;
        return $row;
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

            // Check if 'ring_group_uuid' exists in the 'application' array
            if (isset($row['extension']['application'])) {
                foreach ($row['extension']['application'] as $application) {
                    if (isset($application['@attributes']['app_data']) && strpos($application['@attributes']['app_data'], 'ring_group_uuid') !== false) {
                        // Extract the ring_group_uuid value
                        preg_match('/ring_group_uuid=([a-f0-9\-]+)/', $application['@attributes']['app_data'], $matches);
                        if (isset($matches[1]) && $row['times']['bridged_time'] != '0') {

                            $newRow = [
                                'caller_profile' => [
                                    'destination_number' => $row['caller_profile']['destination_number'],
                                    'context' => !empty($row['caller_profile']['context']) ? $row['caller_profile']['context'] : '',
                                    'caller_id_name' => $row['caller_profile']['callee_id_name'],
                                    'caller_id_number' => $row['caller_profile']['caller_id_number'],
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

                            // Adjust created time for current row
                            $row['times']['profile_created_time'] = $row['times']['bridged_time'] != '0' ? $row['times']['bridged_time'] : $row['times']['transfer_time'];
                            $row['times']['progress_media_time'] = $row['times']['bridged_time'] != '0' ? $row['times']['bridged_time'] : $row['times']['transfer_time'];
                        } else {
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
     * @param Collection $callFlowSummary
     * @return Collection
     */
    protected function formatTimes($callFlowSummary)
    {
        return $callFlowSummary->map(function ($item) {
            // Define the keys that need to be formatted
            $timeKeys = [
                'created_time',
                'answered_time',
                'progress_time',
                'bridged_time',
                'transfer_time',
                'profile_created_time',
                'profile_end_time',
                'progress_media_time',
                'hangup_time'
            ];

            // Loop through each key and format the time
            foreach ($timeKeys as $key) {
                if (isset($item[$key]) && $item[$key] != 0) {
                    $item[$key] = Carbon::createFromTimestamp($item[$key])->toDateTimeString();
                }
            }

            return $item;
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


        // logger($row);

        if (!empty($row["caller_profile"]["destination_number"]) && (substr($row["caller_profile"]["destination_number"], 0, 4) == 'park' || (substr($row["caller_profile"]["destination_number"], 0, 3) == '*59' && strlen($row["caller_profile"]["destination_number"]) > 3))) {
            if (strpos($row['caller_profile']['transfer_source'], "park+") !== false) {
                $destinationNumber = $row['caller_profile']['destination_number'];
            } else {
                $destinationNumber = $row['caller_profile']['callee_id_number'];
            }
        }
        //check if this is intercept
        else if (
            isset($row["caller_profile"]["originator"]["originator_caller_profile"]["destination_number"]) &&
            (substr($row["caller_profile"]["originator"]["originator_caller_profile"]["destination_number"], 0, 3) == '*97' &&
                strlen($row["caller_profile"]["originator"]["originator_caller_profile"]["destination_number"]) > 3)
        ) {

            $destinationNumber = $row["caller_profile"]["originator"]["originator_caller_profile"]["destination_number"] . "^" . $row["caller_profile"]["originator"]["originator_caller_profile"]["caller_id_number"];
        }
        // all other cases
        else {
            $destinationNumber = !empty($row['caller_profile']['callee_id_number']) ? $row['caller_profile']['callee_id_number'] : $row['caller_profile']['destination_number'];
        }

        $durationInSeconds = $profileEndEpoch - $profileCreatedEpoch;
        $minutes = floor($durationInSeconds / 60);
        $seconds = $durationInSeconds % 60;

        if ($minutes > 0) {
            $durationFormatted = sprintf('%d min %02d s', $minutes, $seconds);
        } else {
            $durationFormatted = sprintf('%02d s', $seconds);
        }

        return [
            'destination_number' => $destinationNumber,
            // 'destination_number' => !empty($row['caller_profile']['callee_id_number']) ? $row['caller_profile']['callee_id_number'] : $row['caller_profile']['destination_number'],
            'context' => !empty($row['caller_profile']['context']) ? $row['caller_profile']['context'] : '',
            'bridged_time' => $row['times']['bridged_time'] == 0 ? 0 : $this->formatTime($row['times']['bridged_time']),
            'created_time' => $row['times']['created_time'] == 0 ? 0 : $this->formatTime($row['times']['created_time']),
            'answered_time' => $row['times']['answered_time'] == 0 ? 0 : $this->formatTime($row['times']['answered_time']),
            'progress_time' => $row['times']['progress_time'] == 0 ? 0 : $this->formatTime($row['times']['progress_time']),
            'transfer_time' => $row['times']['transfer_time'] == 0 ? 0 : $this->formatTime($row['times']['transfer_time']),
            'profile_created_time' => $row['times']['profile_created_time'] == 0 ? 0 : $this->formatTime($row['times']['profile_created_time']),
            'profile_end_time' => $row['times']['profile_end_time'] == 0 ? 0 : $this->formatTime($row['times']['profile_end_time']),
            'progress_media_time' => $row['times']['progress_media_time'] == 0 ? 0 : $this->formatTime($row['times']['progress_media_time']),
            'hangup_time' => $row['times']['hangup_time'] == 0 ? 0 : $this->formatTime($row['times']['hangup_time']),
            'duration_seconds' => $durationInSeconds,
            'duration_formatted' => $durationFormatted,
            'call_disposition' =>  isset($row['times']['call_disposition']) ? $row['times']['call_disposition'] : null,
        ];
    }

    private function formatTime($time)
    {
        return (int) round($time / 1000000);
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

                $s = trim((string) $raw);

                // If it contains any letters, take no action
                if (preg_match('/[A-Za-z]/', $s)) {
                    break; // leave as-is
                }

                // Only numbers/spaces/specials: strip all non-digits
                $digits = preg_replace('/\D+/', '', $s);

                // If 11 digits and starts with 1 (covers +1xxx after stripping), drop the leading 1
                if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
                    $digits = substr($digits, 1);
                }

                // Write back normalized value
                data_set($params, $sk, $digits);
                break; // only handle the first populated key
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
 //           $params['paginate'] = 50;

            // --- Normalize search term (numbers-only cases) ---
            foreach (['filter.search', 'filter.searchTerm', 'filterData.search'] as $sk) {
                $raw = data_get($params, $sk);
                if ($raw === null || $raw === '') continue;

                $s = trim((string) $raw);

                // If it contains any letters, take no action
                if (preg_match('/[A-Za-z]/', $s)) {
                    break; // leave as-is
                }

                // Only numbers/spaces/specials: strip all non-digits
                $digits = preg_replace('/\D+/', '', $s);

                // If 11 digits and starts with 1 (covers +1xxx after stripping), drop the leading 1
                if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
                    $digits = substr($digits, 1);
                }

                // Write back normalized value
                data_set($params, $sk, $digits);
                break; // only handle the first populated key
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
