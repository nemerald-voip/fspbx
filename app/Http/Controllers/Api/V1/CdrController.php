<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CDR;
use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\CdrDataService;
use App\Services\CallRecordingUrlService;

class CdrController extends Controller
{
    public $item_domain_uuid;

    protected CdrDataService $cdrDataService;
    protected CallRecordingUrlService $callRecordingUrlService;

    public function __construct(
        CdrDataService $cdrDataService,
        CallRecordingUrlService $callRecordingUrlService
    ) {
        $this->cdrDataService = $cdrDataService;
        $this->callRecordingUrlService = $callRecordingUrlService;
    }

    /**
     * List Call Detail Records
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
     *   `missed call`, `abandoned`, `callback_requested`.
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
     * @queryParam status string Optional. Filter by status. Accepted values: answered, no answer, cancelled, voicemail, missed call, abandoned, callback_requested. Example: answered
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
     *       "recording_uuid": "c0ec8113-aa15-40ac-8437-47185dd9dcf4",
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

        $limit = (int) $request->input('limit', 500);
        $limit = max(1, min(500, $limit));

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
     * Retrieve a Call Detail Record
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
     *   "recording_uuid": "40aec3e8-a572-40da-954b-ddf6a8a65324",
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
     * Retrieve a recording URL for a Call Detail Record
     *
     * Returns time-limited URLs (default 10 minutes) for streaming and
     * downloading the recording associated with the given CDR. Works
     * uniformly across both recording-storage backends:
     *
     * - **Local storage** (`cdrs.record_path` is a filesystem path):
     *   Returns Laravel signed routes to the existing `cdrs.recording.stream`
     *   and `cdrs.recording.download` web endpoints. The signature embeds
     *   the TTL; no session cookie required by the consumer.
     * - **S3 / S3-compatible** (`cdrs.record_path === 'S3'`, populated by
     *   the `fs:upload-call-recordings-to-s3-storage` archival command):
     *   Returns presigned object URLs minted via the configured per-domain
     *   S3 disk.
     *
     * Either way the consumer can `GET` the URL with no extra auth and
     * receive the audio bytes (`Content-Disposition: inline` for
     * `audio_url`, `attachment` for `download_url`).
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `xml_cdr_view` permission.
     *
     * @group CDRs
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 7d58342b-2b29-4dcf-92d6-e9a9e002a4e5
     * @urlParam xml_cdr_uuid string required The CDR UUID. Example: 40aec3e8-a572-40da-954b-ddf6a8a65324
     *
     * @response 200 scenario="Success" {
     *   "object": "cdr_recording_url",
     *   "xml_cdr_uuid": "40aec3e8-a572-40da-954b-ddf6a8a65324",
     *   "audio_url": "https://pbx.example.com/call-detail-records/recordings/40aec3e8-a572-40da-954b-ddf6a8a65324/stream?expires=1775788500&signature=...",
     *   "download_url": "https://pbx.example.com/call-detail-records/recordings/40aec3e8-a572-40da-954b-ddf6a8a65324/download?expires=1775788500&signature=...",
     *   "filename": "20260401-120000_2135551212_1001.wav",
     *   "expires_at": 1775788500
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid CDR UUID" {"error":{"type":"invalid_request_error","message":"Invalid CDR UUID.","code":"invalid_request","param":"xml_cdr_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="CDR not found" {"error":{"type":"invalid_request_error","message":"CDR not found.","code":"resource_missing","param":"xml_cdr_uuid"}}
     * @response 404 scenario="No recording for this CDR" {"error":{"type":"invalid_request_error","message":"No recording available for this CDR.","code":"resource_missing","param":"xml_cdr_uuid"}}
     */
    public function recordingUrl(Request $request, string $domain_uuid, string $xml_cdr_uuid)
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

        $cdrExists = CDR::query()
            ->where('domain_uuid', $domain_uuid)
            ->where('xml_cdr_uuid', $xml_cdr_uuid)
            ->exists();
        if (! $cdrExists) {
            throw new ApiException(404, 'invalid_request_error', 'CDR not found.', 'resource_missing', 'xml_cdr_uuid');
        }

        $ttlSeconds = 600;
        $urls = $this->callRecordingUrlService->urlsForCdr($xml_cdr_uuid, $ttlSeconds);

        if (empty($urls['audio_url'])) {
            throw new ApiException(404, 'invalid_request_error', 'No recording available for this CDR.', 'resource_missing', 'xml_cdr_uuid');
        }

        return response()->json([
            'object' => 'cdr_recording_url',
            'xml_cdr_uuid' => $xml_cdr_uuid,
            'audio_url' => $urls['audio_url'],
            'download_url' => $urls['download_url'],
            'filename' => $urls['filename'],
            'expires_at' => Carbon::now()->addSeconds($ttlSeconds)->timestamp,
        ], 200);
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

}
