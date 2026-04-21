<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CDR;
use App\Models\Domain;
use App\Models\Dialplans;
use App\Models\Extensions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\CdrDataService;

class CdrController extends Controller
{
    public $item_domain_uuid;

    protected CdrDataService $cdrDataService;

    public function __construct(CdrDataService $cdrDataService)
    {
        $this->cdrDataService = $cdrDataService;
    }

    /**
     * List CDRs
     *
     * Returns CDRs for the specified domain the caller is allowed to access.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `xml_cdr_view` permission.
     *
     * Pagination (cursor-based):
     * - Both `limit` and `starting_after` are optional.
     * - If `limit` is not provided, it defaults to 500.
     * - If `starting_after` is not provided, results start from the beginning.
     * - If `has_more` is true, request the next page by passing `starting_after`
     *   equal to the last item's `xml_cdr_uuid` from the previous response.
     *
     * Optional filters:
     * - `search` matches caller/destination fields and status.
     * - `direction` filters by call direction.
     * - `status` filters by call status.
     *   Accepted values: `answered`, `no answer`, `cancelled`, `voicemail`,
     *   `missed call`, `abandoned`.
     * - `extension_uuid` filters by extension UUID.
     * - `call_center_queue_uuid` filters by queue UUID.
     * - `date_from` and `date_to` filter by `start_epoch` in epoch seconds.
     *
     * Examples:
     * - First page: `GET /api/v1/domains/{domain_uuid}/cdrs`
     * - Next page:  `GET /api/v1/domains/{domain_uuid}/cdrs?starting_after={last_xml_cdr_uuid}`
     * - Custom size: `GET /api/v1/domains/{domain_uuid}/cdrs?limit=25`
     * - Search: `GET /api/v1/domains/{domain_uuid}/cdrs?search=2135551212`
     * - Date range (epoch): `GET /api/v1/domains/{domain_uuid}/cdrs?date_from=1775001600&date_to=1775087999`
     *
     * @group CDRs
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @queryParam limit integer Optional. Number of results to return (min 1, max 500). Defaults to 500. Example: 500
     * @queryParam starting_after string Optional. Return results after this CDR UUID (cursor). Example: c0ec8113-aa15-40ac-8437-47185dd9dcf4
     * @queryParam search string Optional. Search caller name, numbers, SIP Call-ID, and status. Example: 2135551212
     * @queryParam direction string Optional. Filter by direction. Example: inbound
     * @queryParam status string Optional. Filter by status. Accepted values: answered, no answer, cancelled, voicemail, missed call, abandoned. Example: answered
     * @queryParam extension_uuid string Optional. Filter by extension UUID. Example: c9a76140-0ca4-4ea3-95af-7e12c2ff0df5
     * @queryParam call_center_queue_uuid string Optional. Filter by queue UUID. Example: 89ea1ec3-44f8-4705-8f2c-f9769486f9f1
     * @queryParam date_from integer Optional. Start of date range in epoch seconds (UTC). Example: 1775001600
     * @queryParam date_to integer Optional. End of date range in epoch seconds (UTC). Example: 1775087999
     *
     * @response 200 scenario="Success" {
     *   "object": "list",
     *   "url": "/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/cdrs",
     *   "has_more": true,
     *   "data": [
     *     {
     *       "xml_cdr_uuid": "c0ec8113-aa15-40ac-8437-47185dd9dcf4",
     *       "object": "cdr",
     *       "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *       "sip_call_id": "0f4b17db-3ef1-476d-b6e7-bcfe07dfd001",
     *       "extension_uuid": "c9a76140-0ca4-4ea3-95af-7e12c2ff0df5",
     *       "direction": "inbound",
     *       "caller_id_name": "John Smith",
     *       "caller_id_number": "2135551212",
     *       "caller_destination": "1001",
     *       "destination_number": "1001",
     *       "start_epoch": 1775787900,
     *       "answer_epoch": 1775787905,
     *       "end_epoch": 1775787960,
     *       "duration": 60,
     *       "hangup_cause": "NORMAL_CLEARING",
     *       "hangup_cause_q850": "16",
     *       "status": "answered"
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid starting_after UUID" {"error":{"type":"invalid_request_error","message":"Invalid starting_after UUID.","code":"invalid_request","param":"starting_after"}}
     * @response 400 scenario="Invalid extension UUID" {"error":{"type":"invalid_request_error","message":"Invalid extension UUID.","code":"invalid_request","param":"extension_uuid"}}
     * @response 400 scenario="Invalid queue UUID" {"error":{"type":"invalid_request_error","message":"Invalid call_center_queue_uuid UUID.","code":"invalid_request","param":"call_center_queue_uuid"}}
     * @response 400 scenario="Invalid date_from" {"error":{"type":"invalid_request_error","message":"Invalid date_from value.","code":"invalid_request","param":"date_from"}}
     * @response 400 scenario="Invalid date_to" {"error":{"type":"invalid_request_error","message":"Invalid date_to value.","code":"invalid_request","param":"date_to"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 scenario="Forbidden" {"error":{"type":"permission_error","message":"You do not have permission to access CDRs.","code":"forbidden"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     */
    public function index(Request $request, string $domain_uuid)
    {
        $user = $request->user();

        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        $domainExists = Domain::query()->where('domain_uuid', $domain_uuid)->exists();
        if (! $domainExists) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        $limit = (int) $request->input('limit', 50);
        $limit = max(1, min(100, $limit));

        $startingAfter = (string) $request->input('starting_after', '');
        if ($startingAfter !== '' && ! preg_match('/^[0-9a-fA-F-]{36}$/', $startingAfter)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid starting_after UUID.', 'invalid_request', 'starting_after');
        }

        $extensionUuid = (string) $request->input('extension_uuid', '');
        if ($extensionUuid !== '' && ! preg_match('/^[0-9a-fA-F-]{36}$/', $extensionUuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid extension UUID.', 'invalid_request', 'extension_uuid');
        }

        $queueUuid = (string) $request->input('call_center_queue_uuid', '');
        if ($queueUuid !== '' && ! preg_match('/^[0-9a-fA-F-]{36}$/', $queueUuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid call_center_queue_uuid UUID.', 'invalid_request', 'call_center_queue_uuid');
        }

        $dateFromEpoch = null;
        if ($request->filled('date_from')) {
            $dateFrom = $request->input('date_from');

            if (!is_numeric($dateFrom)) {
                throw new ApiException(400, 'invalid_request_error', 'Invalid date_from value.', 'invalid_request', 'date_from');
            }

            $dateFromEpoch = (int) $dateFrom;
        }

        $dateToEpoch = null;
        if ($request->filled('date_to')) {
            $dateTo = $request->input('date_to');

            if (!is_numeric($dateTo)) {
                throw new ApiException(400, 'invalid_request_error', 'Invalid date_to value.', 'invalid_request', 'date_to');
            }

            $dateToEpoch = (int) $dateTo;
        }

        $query = $this->cdrDataService->getApiIndexQuery($domain_uuid);

        $this->cdrDataService->applyApiIndexFilters($query, [
            'starting_after' => $startingAfter,
            'search' => $request->input('search'),
            'direction' => $request->input('direction'),
            'status' => $request->input('status'),
            'extension_uuid' => $extensionUuid,
            'call_center_queue_uuid' => $queueUuid,
            'date_from_epoch' => $dateFromEpoch,
            'date_to_epoch' => $dateToEpoch,
        ]);

        $query->limit($limit + 1);

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $data = $this->cdrDataService->buildApiIndexData($rows);

        $url = "/api/v1/domains/{$domain_uuid}/cdrs";

        return response()->json([
            'object' => 'list',
            'url' => $url,
            'has_more' => $hasMore,
            'data' => $data,
        ], 200);
    }

    /**
     * Retrieve a CDR
     *
     * Returns a single CDR for the specified domain the caller is allowed to access.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `xml_cdr_view` permission.
     *
     * Notes:
     * - The response includes a normalized `call_flow` summary.
     * - Related queue and ring group call legs are merged into the returned
     *   `call_flow` timeline when present.
     *
     * @group CDRs
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 7d58342b-2b29-4dcf-92d6-e9a9e002a4e5
     * @urlParam xml_cdr_uuid string required The CDR UUID. Example: 40aec3e8-a572-40da-954b-ddf6a8a65324
     *
     * @response 200 scenario="Success" {
     *   "xml_cdr_uuid": "40aec3e8-a572-40da-954b-ddf6a8a65324",
     *   "object": "cdr",
     *   "domain_uuid": "7d58342b-2b29-4dcf-92d6-e9a9e002a4e5",
     *   "sip_call_id": "0f4b17db-3ef1-476d-b6e7-bcfe07dfd001",
     *   "extension_uuid": "c9a76140-0ca4-4ea3-95af-7e12c2ff0df5",
     *   "call_center_queue_uuid": null,
     *   "direction": "inbound",
     *   "caller_id_name": "John Smith",
     *   "caller_id_number": "2135551212",
     *   "caller_destination": "1001",
     *   "destination_number": "1001",
     *   "start_epoch": 1775787900,
     *   "answer_epoch": 1775787905,
     *   "end_epoch": 1775787960,
     *   "duration": 60,
     *   "hangup_cause": "NORMAL_CLEARING",
     *   "hangup_cause_q850": "16",
     *   "voicemail_message": false,
     *   "cc_cancel_reason": null,
     *   "cc_cause": null,
     *   "sip_hangup_disposition": "recv_bye",
     *   "status": "answered",
     *   "call_disposition": "The caller hung up.",
     *   "call_flow": [
     *     {
     *       "destination_number": "1001",
     *       "context": "example.com",
     *       "bridged_time": "2026-04-01 12:00:05",
     *       "created_time": "2026-04-01 12:00:00",
     *       "answered_time": "2026-04-01 12:00:05",
     *       "progress_time": "2026-04-01 12:00:01",
     *       "transfer_time": 0,
     *       "profile_created_time": "2026-04-01 12:00:00",
     *       "profile_end_time": "2026-04-01 12:01:00",
     *       "progress_media_time": "2026-04-01 12:00:01",
     *       "hangup_time": "2026-04-01 12:01:00",
     *       "duration_seconds": 60,
     *       "duration_formatted": "1 min 00 s",
     *       "call_disposition": "answered",
     *       "time_line": "00:00",
     *       "dialplan_app": "Extension",
     *       "dialplan_name": "John Smith",
     *       "dialplan_description": null
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid CDR UUID" {"error":{"type":"invalid_request_error","message":"Invalid CDR UUID.","code":"invalid_request","param":"xml_cdr_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="CDR not found" {"error":{"type":"invalid_request_error","message":"CDR not found.","code":"resource_missing","param":"xml_cdr_uuid"}}
     */
    public function show(Request $request, string $domain_uuid, string $xml_cdr_uuid)
    {
        $user = $request->user();

        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $xml_cdr_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid CDR UUID.', 'invalid_request', 'xml_cdr_uuid');
        }

        $domainExists = Domain::query()->where('domain_uuid', $domain_uuid)->exists();
        if (! $domainExists) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        $payload = $this->cdrDataService->buildApiShowPayload($domain_uuid, $xml_cdr_uuid);

        return response()->json($payload->toArray(), 200);
    }

    /**
     * Builds response payload as a reusable helper.
     */
    private function buildCdrShowPayload(string $domain_uuid, string $xml_cdr_uuid): array
    {
        $cdr = CDR::query()
            ->where('domain_uuid', $domain_uuid)
            ->where('xml_cdr_uuid', $xml_cdr_uuid)
            ->select([
                'xml_cdr_uuid',
                'domain_uuid',
                'sip_call_id',
                'extension_uuid',
                'direction',
                'caller_id_name',
                'caller_id_number',
                'caller_destination',
                'destination_number',
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

        if (! $cdr) {
            throw new ApiException(404, 'invalid_request_error', 'CDR not found.', 'resource_missing', 'xml_cdr_uuid');
        }

        $this->item_domain_uuid = $cdr->domain_uuid;

        $mainCallFlowData = collect(json_decode($cdr->call_flow, true) ?: []);
        $combinedCallFlowData = $mainCallFlowData;

        if (! empty($cdr->call_center_queue_uuid)) {
            $relatedQueueCalls = $cdr->relatedQueueCalls()
                ->where('domain_uuid', $cdr->domain_uuid)
                ->select([
                    'xml_cdr_uuid',
                    'domain_uuid',
                    'call_flow',
                    'call_disposition',
                ])
                ->get();

            foreach ($relatedQueueCalls as $relatedCall) {
                $relatedCallFlow = collect(json_decode($relatedCall->call_flow, true) ?: [])
                    ->map(function ($flow) use ($relatedCall) {
                        if (isset($flow['times'])) {
                            $flow['times']['call_disposition'] = $relatedCall->call_disposition;
                        }
                        return $flow;
                    });

                $combinedCallFlowData = $combinedCallFlowData->merge($relatedCallFlow);
            }
        }

        $relatedRingGroupCalls = $cdr->relatedRingGroupCalls()
            ->where('domain_uuid', $cdr->domain_uuid)
            ->select([
                'xml_cdr_uuid',
                'domain_uuid',
                'call_flow',
                'call_disposition',
            ])
            ->get();

        foreach ($relatedRingGroupCalls as $relatedCall) {
            $relatedCallFlow = collect(json_decode($relatedCall->call_flow, true) ?: [])
                ->map(function ($flow) use ($relatedCall) {
                    if (isset($flow['times'])) {
                        $flow['times']['call_disposition'] = $relatedCall->call_disposition;
                    }
                    return $flow;
                });

            $combinedCallFlowData = $combinedCallFlowData->merge($relatedCallFlow);
        }

        $combinedCallFlowData = $this->handleCallFlowSteps($combinedCallFlowData);

        $callFlowSummary = $combinedCallFlowData
            ->map(function ($row) {
                return $this->buildSummaryItem($row);
            })
            ->sortBy('profile_created_time')
            ->values()
            ->map(function ($row) use ($cdr) {
                $timeDifference = $row['profile_created_time'] - $cdr->start_epoch;
                $row['time_line'] = sprintf('%02d:%02d', floor($timeDifference / 60), $timeDifference % 60);

                if ($cdr->direction === 'outbound') {
                    $row['dialplan_app'] = 'Outbound Call';
                }

                return $row;
            });

        $callFlowSummary = $this->formatTimes($callFlowSummary)
            ->map(function ($row) {
                return $this->getAppDetails($row);
            })
            ->values()
            ->all();

        return [
            'xml_cdr_uuid' => (string) $cdr->xml_cdr_uuid,
            'object' => 'cdr',
            'domain_uuid' => (string) $cdr->domain_uuid,
            'sip_call_id' => $cdr->sip_call_id,
            'extension_uuid' => $cdr->extension_uuid,
            'direction' => $cdr->direction,
            'caller_id_name' => $cdr->caller_id_name,
            'caller_id_number' => $cdr->caller_id_number,
            'caller_destination' => $cdr->caller_destination,
            'destination_number' => $cdr->destination_number,
            'start_epoch' => $cdr->start_epoch !== null ? (int) $cdr->start_epoch : null,
            'answer_epoch' => $cdr->answer_epoch !== null ? (int) $cdr->answer_epoch : null,
            'end_epoch' => $cdr->end_epoch !== null ? (int) $cdr->end_epoch : null,
            'duration' => $cdr->duration !== null ? (int) $cdr->duration : null,
            'billsec' => $cdr->billsec !== null ? (int) $cdr->billsec : null,
            'waitsec' => $cdr->waitsec !== null ? (int) $cdr->waitsec : null,
            'voicemail_message' => $this->toBool($cdr->voicemail_message),
            'missed_call' => $this->toBool($cdr->missed_call),
            'hangup_cause' => $cdr->hangup_cause,
            'hangup_cause_q850' => $cdr->hangup_cause_q850,
            'call_center_queue_uuid' => $cdr->call_center_queue_uuid,
            'cc_cancel_reason' => $cdr->cc_cancel_reason,
            'cc_cause' => $cdr->cc_cause,
            'sip_hangup_disposition' => $cdr->sip_hangup_disposition,
            'status' => $cdr->status,
            'call_flow' => $callFlowSummary,
        ];
    }

    /**
     * Handle transfers in the call flow array.
     */
    protected function handleCallFlowSteps($callFlowData)
    {
        $newRows = collect();

        $callFlowData->reduce(function ($carry, $row) use ($newRows) {
            if (isset($row['extension']['application'])) {
                foreach ($row['extension']['application'] as $application) {
                    if (
                        isset($application['@attributes']['app_data']) &&
                        strpos($application['@attributes']['app_data'], 'ring_group_uuid') !== false
                    ) {
                        preg_match('/ring_group_uuid=([a-f0-9\-]+)/', $application['@attributes']['app_data'], $matches);

                        if (isset($matches[1]) && ($row['times']['bridged_time'] ?? '0') != '0') {
                            $newRow = [
                                'caller_profile' => [
                                    'destination_number' => $row['caller_profile']['destination_number'] ?? null,
                                    'context' => ! empty($row['caller_profile']['context']) ? $row['caller_profile']['context'] : '',
                                    'caller_id_name' => $row['caller_profile']['callee_id_name'] ?? null,
                                    'caller_id_number' => $row['caller_profile']['caller_id_number'] ?? null,
                                ],
                                'times' => [
                                    'bridged_time' => '0',
                                    'created_time' => $row['times']['profile_created_time'] ?? 0,
                                    'answered_time' => '0',
                                    'progress_time' => $row['times']['profile_created_time'] ?? 0,
                                    'transfer_time' => $row['times']['answered_time'] ?? 0,
                                    'progress_media_time' => $row['times']['profile_created_time'] ?? 0,
                                    'hangup_time' => 0,
                                    'profile_created_time' => $row['times']['profile_created_time'] ?? 0,
                                    'profile_end_time' => ($row['times']['bridged_time'] ?? '0') != '0'
                                        ? $row['times']['bridged_time']
                                        : ($row['times']['profile_end_time'] ?? 0),
                                ],
                            ];

                            $newRows->push($newRow);

                            $row['times']['profile_created_time'] = ($row['times']['bridged_time'] ?? '0') != '0'
                                ? $row['times']['bridged_time']
                                : ($row['times']['transfer_time'] ?? 0);

                            $row['times']['progress_media_time'] = ($row['times']['bridged_time'] ?? '0') != '0'
                                ? $row['times']['bridged_time']
                                : ($row['times']['transfer_time'] ?? 0);
                        } else {
                            $row['caller_profile']['callee_id_number'] = $row['caller_profile']['destination_number'] ?? null;
                        }
                    }
                }
            }

            $newRows->push($row);

            return $carry;
        }, $callFlowData);

        return $newRows;
    }

    /**
     * Format the times in the call flow array.
     */
    protected function formatTimes($callFlowSummary)
    {
        return $callFlowSummary->map(function ($item) {
            $timeKeys = [
                'created_time',
                'answered_time',
                'progress_time',
                'bridged_time',
                'transfer_time',
                'profile_created_time',
                'profile_end_time',
                'progress_media_time',
                'hangup_time',
            ];

            foreach ($timeKeys as $key) {
                if (isset($item[$key]) && $item[$key] != 0) {
                    $item[$key] = Carbon::createFromTimestamp($item[$key])->toDateTimeString();
                }
            }

            return $item;
        });
    }

    /**
     * Build a summary item for the call flow.
     */
    protected function buildSummaryItem(array $row): array
    {
        $profileCreatedEpoch = $this->formatTime($row['times']['profile_created_time'] ?? 0);
        $profileEndEpoch = $this->formatTime($row['times']['profile_end_time'] ?? 0);

        if (
            ! empty($row['caller_profile']['destination_number']) &&
            (
                substr($row['caller_profile']['destination_number'], 0, 4) === 'park' ||
                (
                    substr($row['caller_profile']['destination_number'], 0, 3) === '*59' &&
                    strlen($row['caller_profile']['destination_number']) > 3
                )
            )
        ) {
            if (
                isset($row['caller_profile']['transfer_source']) &&
                strpos($row['caller_profile']['transfer_source'], 'park+') !== false
            ) {
                $destinationNumber = $row['caller_profile']['destination_number'];
            } else {
                $destinationNumber = $row['caller_profile']['callee_id_number'] ?? $row['caller_profile']['destination_number'];
            }
        } elseif (
            isset($row['caller_profile']['originator']['originator_caller_profile']['destination_number']) &&
            substr($row['caller_profile']['originator']['originator_caller_profile']['destination_number'], 0, 3) === '*97' &&
            strlen($row['caller_profile']['originator']['originator_caller_profile']['destination_number']) > 3
        ) {
            $destinationNumber =
                $row['caller_profile']['originator']['originator_caller_profile']['destination_number'] .
                '^' .
                $row['caller_profile']['originator']['originator_caller_profile']['caller_id_number'];
        } else {
            $destinationNumber = ! empty($row['caller_profile']['callee_id_number'])
                ? $row['caller_profile']['callee_id_number']
                : ($row['caller_profile']['destination_number'] ?? null);
        }

        $durationInSeconds = max(0, $profileEndEpoch - $profileCreatedEpoch);
        $minutes = floor($durationInSeconds / 60);
        $seconds = $durationInSeconds % 60;

        $durationFormatted = $minutes > 0
            ? sprintf('%d min %02d s', $minutes, $seconds)
            : sprintf('%02d s', $seconds);

        return [
            'destination_number' => $destinationNumber,
            'context' => ! empty($row['caller_profile']['context']) ? $row['caller_profile']['context'] : '',
            'bridged_time' => ($row['times']['bridged_time'] ?? 0) == 0 ? 0 : $this->formatTime($row['times']['bridged_time']),
            'created_time' => ($row['times']['created_time'] ?? 0) == 0 ? 0 : $this->formatTime($row['times']['created_time']),
            'answered_time' => ($row['times']['answered_time'] ?? 0) == 0 ? 0 : $this->formatTime($row['times']['answered_time']),
            'progress_time' => ($row['times']['progress_time'] ?? 0) == 0 ? 0 : $this->formatTime($row['times']['progress_time']),
            'transfer_time' => ($row['times']['transfer_time'] ?? 0) == 0 ? 0 : $this->formatTime($row['times']['transfer_time']),
            'profile_created_time' => ($row['times']['profile_created_time'] ?? 0) == 0 ? 0 : $this->formatTime($row['times']['profile_created_time']),
            'profile_end_time' => ($row['times']['profile_end_time'] ?? 0) == 0 ? 0 : $this->formatTime($row['times']['profile_end_time']),
            'progress_media_time' => ($row['times']['progress_media_time'] ?? 0) == 0 ? 0 : $this->formatTime($row['times']['progress_media_time']),
            'hangup_time' => ($row['times']['hangup_time'] ?? 0) == 0 ? 0 : $this->formatTime($row['times']['hangup_time']),
            'duration_seconds' => $durationInSeconds,
            'duration_formatted' => $durationFormatted,
            'call_disposition' => $row['times']['call_disposition'] ?? null,
        ];
    }

    /**
     * Get app details associated with call flow step.
     */
    public function getAppDetails($row)
    {
        $destination = formatPhoneNumber($row['destination_number'], 'US', 0);

        if (is_string($destination) && strpos($destination, '+1') === 0) {
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

            foreach ($patterns as $info) {
                if (preg_match($info['pattern'], $dialplan->dialplan_xml)) {
                    $row['dialplan_app'] = $info['app'];
                    $row['dialplan_name'] = $dialplan->dialplan_name;
                    $row['dialplan_description'] = $dialplan->dialplan_description;
                    break;
                }
            }

            return $row;
        }

        if (strpos($row['destination_number'], 'park+') !== false) {
            $row['dialplan_app'] = 'Park';
            $row['dialplan_name'] = substr($row['destination_number'], 6);
            $row['dialplan_description'] = '';
            return $row;
        }

        if (substr($row['destination_number'], 0, 3) === '*99') {
            $row['dialplan_app'] = 'Voicemail';
            $row['dialplan_name'] = substr($row['destination_number'], 3);
            $row['dialplan_description'] = '';
            return $row;
        }

        if (substr($row['destination_number'], 0, 3) === '*97') {
            if (preg_match('/\*97(\d+)\^(.+)/', $row['destination_number'], $matches)) {
                $interceptedExt = $matches[1];
                $interceptedByExt = $matches[2];

                $row['dialplan_app'] = 'Call Intercept ' . $interceptedExt;

                $extension = Extensions::where('domain_uuid', $this->item_domain_uuid)
                    ->where('extension', $interceptedByExt)
                    ->first();

                $row['dialplan_name'] = $extension
                    ? $extension->effective_caller_id_name . ' (' . $interceptedByExt . ')'
                    : null;

                $row['dialplan_description'] = '';

                return $row;
            }
        }

        $extension = Extensions::where('domain_uuid', $this->item_domain_uuid)
            ->where('extension', $row['destination_number'])
            ->first();

        if ($extension) {
            $row['dialplan_app'] = 'Extension';
            $row['dialplan_name'] = $extension->effective_caller_id_name;
            $row['dialplan_description'] = $extension->description;
            return $row;
        }

        $row['dialplan_app'] = 'Misc. Destination';
        $row['dialplan_name'] = $row['destination_number'];
        $row['dialplan_description'] = null;

        return $row;
    }

    /**
     * Normalize search term for numeric-only searches.
     */
    private function normalizeSearchTerm($value): string
    {
        if ($value === null) {
            return '';
        }

        $search = trim((string) $value);
        if ($search === '') {
            return '';
        }

        if (preg_match('/[A-Za-z]/', $search)) {
            return $search;
        }

        $digits = preg_replace('/\D+/', '', $search);

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Convert mixed boolean-ish values to a boolean.
     */
    private function toBool($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Convert microseconds to seconds.
     */
    private function formatTime($time): int
    {
        return (int) round(((int) $time) / 1000000);
    }
}
