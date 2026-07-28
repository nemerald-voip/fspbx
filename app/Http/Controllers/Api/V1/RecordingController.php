<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Api\V1\DeletedResponseData;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRecordingRequest;
use App\Http\Requests\Api\V1\UpdateRecordingRequest;
use App\Models\Domain;
use App\Models\Recordings;
use App\Services\RecordingService;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class RecordingController extends Controller
{
    public function __construct(private RecordingService $recordings) {}

    /**
     * List recordings
     *
     * Returns recordings for the specified domain the caller is allowed to access.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `recording_view` permission.
     *
     * Pagination (cursor-based):
     * - Both `limit` and `starting_after` are optional.
     * - If `limit` is not provided, it defaults to 50.
     * - If `starting_after` is not provided, results start from the beginning.
     * - If `has_more` is true, request the next page by passing `starting_after`
     *   equal to the last item's `recording_uuid` from the previous response.
     *
     * Examples:
     * - First page: `GET /api/v1/domains/{domain_uuid}/recordings`
     * - Next page: `GET /api/v1/domains/{domain_uuid}/recordings?starting_after={last_recording_uuid}`
     * - Custom size: `GET /api/v1/domains/{domain_uuid}/recordings?limit=50`
     *
     * @group Recordings
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @queryParam limit integer Optional. Number of results to return (min 1, max 100). Defaults to 50. Example: 50
     * @queryParam starting_after string Optional. Return results after this recording UUID (cursor). Example: c0ec8113-aa15-40ac-8437-47185dd9dcf4
     *
     * @response 200 scenario="Success" {
     *   "object": "list",
     *   "url": "/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/recordings",
     *   "has_more": true,
     *   "data": [
     *     {
     *       "recording_uuid": "c0ec8113-aa15-40ac-8437-47185dd9dcf4",
     *       "object": "recording",
     *       "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *       "recording_name": "Main Menu Greeting",
     *       "recording_description": "Primary virtual receptionist greeting",
     *       "recording_filename": "uploaded_abc123.wav",
     *       "audio_available": true,
     *       "audio_replaced": false,
     *       "bytes": null,
     *       "sha256": null
     *     }
     *   ]
     * }
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid cursor UUID" {"error":{"type":"invalid_request_error","message":"Invalid starting_after UUID.","code":"invalid_request","param":"starting_after"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 scenario="Forbidden" {"success":false,"message":"Forbidden (missing permission).","error":{"code":"forbidden_permission","permission":"recording_view"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     */
    public function index(Request $request, string $domain_uuid)
    {
        $domain = $this->domain($domain_uuid);
        $limit = max(1, min(100, (int) $request->input('limit', 50)));
        $startingAfter = (string) $request->input('starting_after', '');

        if ($startingAfter !== '' && ! $this->isUuid($startingAfter)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid starting_after UUID.', 'invalid_request', 'starting_after');
        }

        $rows = $this->recordings->list($domain_uuid, $limit + 1, $startingAfter ?: null);
        $hasMore = $rows->count() > $limit;

        return response()->json([
            'object' => 'list',
            'url' => "/api/v1/domains/{$domain_uuid}/recordings",
            'has_more' => $hasMore,
            'data' => $rows->take($limit)->map(fn (Recordings $recording) => $this->payload($recording, $domain, null, false))->values(),
        ]);
    }

    /**
     * Create a recording
     *
     * Creates a recording in the specified domain and converts the uploaded audio
     * to mono, 16 kHz, 16-bit PCM WAV for FreeSWITCH playback.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `recording_upload` permission.
     *
     * Send this request as `multipart/form-data`.
     *
     * @group Recordings
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @bodyParam recording_name string required The recording display name. Example: Main Menu Greeting
     * @bodyParam recording_description string Optional description. Example: Primary virtual receptionist greeting
     * @bodyParam file file required WAV, MP3, M4A/MP4, OGG, or FLAC audio file. Maximum size: 50 MB.
     *
     * @response 201 scenario="Created" {
     *   "recording_uuid": "c0ec8113-aa15-40ac-8437-47185dd9dcf4",
     *   "object": "recording",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "recording_name": "Main Menu Greeting",
     *   "recording_description": "Primary virtual receptionist greeting",
     *   "recording_filename": "uploaded_abc123.wav",
     *   "audio_available": true,
     *   "audio_replaced": true,
     *   "bytes": 384044,
     *   "sha256": "6d27ccf17b7106932c46d299f908f793b52cb168f4cc47b70ef661755adb59f4"
     * }
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 scenario="Forbidden" {"success":false,"message":"Forbidden (missing permission).","error":{"code":"forbidden_permission","permission":"recording_upload"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 422 scenario="Validation error" {"message":"The file field is required.","errors":{"file":["The file field is required."]}}
     * @response 422 scenario="Audio processing failed" {"error":{"type":"invalid_request_error","message":"The uploaded audio could not be converted to a valid recording.","code":"audio_processing_failed","param":"file"}}
     * @response 500 scenario="Internal error" {"error":{"type":"api_error","message":"The recording operation failed.","code":"internal_error"}}
     */
    public function store(StoreRecordingRequest $request, string $domain_uuid)
    {
        $domain = $this->domain($domain_uuid);

        try {
            $result = $this->recordings->create(
                $domain,
                (string) $request->input('recording_name'),
                $request->input('recording_description'),
                $request->file('file'),
                $request->user()?->user_uuid
            );
        } catch (Throwable $e) {
            $this->audioException($e);
        }

        return response()
            ->json($this->payload($result['recording'], $domain, $result['audio']), 201)
            ->header('Location', "/api/v1/domains/{$domain_uuid}/recordings/{$result['recording']->recording_uuid}");
    }

    /**
     * Retrieve a recording
     *
     * Returns one recording and details about its stored audio file.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `recording_view` permission.
     *
     * @group Recordings
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam recording_uuid string required The recording UUID. Example: c0ec8113-aa15-40ac-8437-47185dd9dcf4
     *
     * @response 200 scenario="Success" {
     *   "recording_uuid": "c0ec8113-aa15-40ac-8437-47185dd9dcf4",
     *   "object": "recording",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "recording_name": "Main Menu Greeting",
     *   "recording_description": "Primary virtual receptionist greeting",
     *   "recording_filename": "uploaded_abc123.wav",
     *   "audio_available": true,
     *   "audio_replaced": false,
     *   "bytes": 384044,
     *   "sha256": "6d27ccf17b7106932c46d299f908f793b52cb168f4cc47b70ef661755adb59f4"
     * }
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid recording UUID" {"error":{"type":"invalid_request_error","message":"Invalid recording UUID.","code":"invalid_request","param":"recording_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 scenario="Forbidden" {"success":false,"message":"Forbidden (missing permission).","error":{"code":"forbidden_permission","permission":"recording_view"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="Recording not found" {"error":{"type":"invalid_request_error","message":"Recording not found.","code":"resource_missing","param":"recording_uuid"}}
     */
    public function show(Request $request, string $domain_uuid, string $recording_uuid)
    {
        $domain = $this->domain($domain_uuid);
        $recording = $this->recording($domain_uuid, $recording_uuid);

        return response()->json($this->payload($recording, $domain));
    }

    /**
     * Update a recording
     *
     * Updates the name, description, audio file, or any combination of those
     * fields. Supplying `file` converts and atomically updates the stored audio
     * while retaining the recording UUID and filename, so existing call-routing
     * references continue to use the updated audio.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `recording_upload` permission.
     *
     * For metadata-only updates, send `PATCH` normally. For an audio upload, send
     * `multipart/form-data` as POST with `_method=PATCH` so PHP parses the file.
     * At least one update field is required.
     *
     * @group Recordings
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam recording_uuid string required The recording UUID. Example: c0ec8113-aa15-40ac-8437-47185dd9dcf4
     * @bodyParam recording_name string Optional recording display name. Example: Updated Main Menu Greeting
     * @bodyParam recording_description string Optional description; may be null. Example: Updated automatically
     * @bodyParam file file Optional WAV, MP3, M4A/MP4, OGG, or FLAC audio file. Maximum size: 50 MB.
     * @bodyParam _method string Required only for multipart audio uploads. Must be `PATCH`. Example: PATCH
     *
     * @response 200 scenario="Audio and metadata updated" {
     *   "recording_uuid": "c0ec8113-aa15-40ac-8437-47185dd9dcf4",
     *   "object": "recording",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "recording_name": "Updated Main Menu Greeting",
     *   "recording_description": "Updated automatically",
     *   "recording_filename": "uploaded_abc123.wav",
     *   "audio_available": true,
     *   "audio_replaced": true,
     *   "bytes": 512044,
     *   "sha256": "9ecf58932d0d11dc64e30b6f96f24c49ecaffdf8ce739dfa0e2e73198c331d2e"
     * }
     * @response 200 scenario="Metadata updated" {
     *   "recording_uuid": "c0ec8113-aa15-40ac-8437-47185dd9dcf4",
     *   "object": "recording",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "recording_name": "Updated Main Menu Greeting",
     *   "recording_description": "Updated automatically",
     *   "recording_filename": "uploaded_abc123.wav",
     *   "audio_available": true,
     *   "audio_replaced": false,
     *   "bytes": 384044,
     *   "sha256": "6d27ccf17b7106932c46d299f908f793b52cb168f4cc47b70ef661755adb59f4"
     * }
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid recording UUID" {"error":{"type":"invalid_request_error","message":"Invalid recording UUID.","code":"invalid_request","param":"recording_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 scenario="Forbidden" {"success":false,"message":"Forbidden (missing permission).","error":{"code":"forbidden_permission","permission":"recording_upload"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="Recording not found" {"error":{"type":"invalid_request_error","message":"Recording not found.","code":"resource_missing","param":"recording_uuid"}}
     * @response 422 scenario="No update fields" {"error":{"type":"invalid_request_error","message":"Provide a name, description, or audio file to update.","code":"validation_error"}}
     * @response 422 scenario="Audio processing failed" {"error":{"type":"invalid_request_error","message":"The uploaded audio could not be converted to a valid recording.","code":"audio_processing_failed","param":"file"}}
     * @response 500 scenario="Internal error" {"error":{"type":"api_error","message":"The recording operation failed.","code":"internal_error"}}
     */
    public function update(UpdateRecordingRequest $request, string $domain_uuid, string $recording_uuid)
    {
        $domain = $this->domain($domain_uuid);
        $recording = $this->recording($domain_uuid, $recording_uuid);
        $validated = $request->validated();

        if (! array_key_exists('recording_name', $validated)
            && ! array_key_exists('recording_description', $validated)
            && ! $request->hasFile('file')) {
            throw new ApiException(422, 'invalid_request_error', 'Provide a name, description, or audio file to update.', 'validation_error');
        }

        $attributes = [];
        if (array_key_exists('recording_name', $validated)) {
            $attributes['recording_name'] = $validated['recording_name'];
        }
        if (array_key_exists('recording_description', $validated)) {
            $attributes['recording_description'] = $validated['recording_description'];
        }

        try {
            $result = $this->recordings->update(
                $recording,
                $domain,
                $attributes,
                $request->file('file'),
                $request->user()?->user_uuid
            );
        } catch (Throwable $e) {
            $this->audioException($e);
        }

        return response()->json($this->payload($result['recording'], $domain, $result['audio']));
    }

    /**
     * Delete a recording
     *
     * Deletes the recording database row and its stored audio file.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `recording_delete` permission.
     *
     * Deleting a recording may clear references from features that use it. Use
     * update when the goal is to replace audio without changing call routing.
     *
     * @group Recordings
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam recording_uuid string required The recording UUID. Example: c0ec8113-aa15-40ac-8437-47185dd9dcf4
     *
     * @response 200 scenario="Deleted" {
     *   "uuid": "c0ec8113-aa15-40ac-8437-47185dd9dcf4",
     *   "object": "recording",
     *   "deleted": true
     * }
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid recording UUID" {"error":{"type":"invalid_request_error","message":"Invalid recording UUID.","code":"invalid_request","param":"recording_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 scenario="Forbidden" {"success":false,"message":"Forbidden (missing permission).","error":{"code":"forbidden_permission","permission":"recording_delete"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="Recording not found" {"error":{"type":"invalid_request_error","message":"Recording not found.","code":"resource_missing","param":"recording_uuid"}}
     * @response 500 scenario="Delete failed" {"error":{"type":"api_error","message":"The recording could not be deleted.","code":"internal_error"}}
     */
    public function destroy(Request $request, string $domain_uuid, string $recording_uuid)
    {
        $domain = $this->domain($domain_uuid);
        $recording = $this->recording($domain_uuid, $recording_uuid);

        if (! $this->recordings->delete($recording, $domain)) {
            throw new ApiException(500, 'api_error', 'The recording could not be deleted.', 'internal_error');
        }

        return response()->json(DeletedResponseData::from([
            'uuid' => $recording_uuid,
            'object' => 'recording',
            'deleted' => true,
        ])->toArray());
    }

    private function domain(string $domainUuid): Domain
    {
        if (! $this->isUuid($domainUuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        return Domain::query()->whereKey($domainUuid)->first()
            ?? throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
    }

    private function recording(string $domainUuid, string $recordingUuid): Recordings
    {
        if (! $this->isUuid($recordingUuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid recording UUID.', 'invalid_request', 'recording_uuid');
        }

        return $this->recordings->find($domainUuid, $recordingUuid)
            ?? throw new ApiException(404, 'invalid_request_error', 'Recording not found.', 'resource_missing', 'recording_uuid');
    }

    private function payload(Recordings $recording, Domain $domain, ?array $audio = null, bool $includeAudioDetails = true): array
    {
        $audioReplaced = $audio !== null;

        if ($includeAudioDetails) {
            $audio ??= $this->recordings->audioDetails($recording, $domain);
            $audioAvailable = $audio !== null;
        } else {
            $audioAvailable = $this->recordings->audioExists($recording, $domain);
        }

        return [
            'recording_uuid' => (string) $recording->recording_uuid,
            'object' => 'recording',
            'domain_uuid' => (string) $recording->domain_uuid,
            'recording_name' => (string) $recording->recording_name,
            'recording_description' => $recording->recording_description,
            'recording_filename' => (string) $recording->recording_filename,
            'audio_available' => $audioAvailable,
            'audio_replaced' => $audioReplaced,
            'bytes' => $audio['bytes'] ?? null,
            'sha256' => $audio['sha256'] ?? null,
        ];
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-fA-F-]{36}$/', $value) === 1;
    }

    private function audioException(Throwable $e): never
    {
        logger('API recording operation error: '.$e->getMessage());
        if ($e instanceof RuntimeException) {
            throw new ApiException(422, 'invalid_request_error', $e->getMessage(), 'audio_processing_failed', 'file');
        }

        throw new ApiException(500, 'api_error', 'The recording operation failed.', 'internal_error');
    }
}
