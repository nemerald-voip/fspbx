<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Domain;
use Illuminate\Http\Request;
use App\Services\ActiveCallService;
use App\Data\Api\V1\ActiveCallData;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Data\Api\V1\DeletedResponseData;
use App\Services\FreeswitchEslService;

class ActiveCallController extends Controller
{
    /**
     * List active calls
     *
     * Returns active calls for the specified domain.
     *
     * Pagination (snapshot cursor-based):
     * - Both `limit` and `starting_after` are optional.
     * - If `limit` is not provided, it defaults to 50.
     * - If `starting_after` is not provided, results start from the beginning.
     *
     * @group Active Calls
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @queryParam limit integer Optional. Number of results to return (min 1, max 100). Defaults to 50. Example: 50
     * @queryParam starting_after string Optional. Return results after this active call UUID in the current snapshot. Example: c0ec8113-aa15-40ac-8437-47185dd9dcf4
     * @queryParam search string Optional. Search caller, destination, app, codec, or secure fields. Example: 1001
     * @queryParam sort string Optional. Sort field. One of: context, created_epoch, duration, cid_name, cid_num, dest, application, read_codec, secure. Defaults to created_epoch. Example: created_epoch
     * @queryParam order string Optional. Sort direction: asc or desc. Defaults to desc. Example: desc
     *
     * @response 200 scenario="Success" {
     *   "object": "list",
     *   "url": "/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/active-calls",
     *   "has_more": false,
     *   "data": [
     *     {
     *       "uuid": "c0ec8113-aa15-40ac-8437-47185dd9dcf4",
     *       "object": "active_call",
     *       "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *       "direction": "inbound",
     *       "created_epoch": "1774920000",
     *       "created_display": "2026-04-30 14:00:00",
     *       "duration_seconds": 42,
     *       "cid_name": "Alice Johnson",
     *       "cid_num": "1001",
     *       "dest": "1002",
     *       "application": "bridge",
     *       "application_data": "sofia/internal/1002@pbx.example.com",
     *       "secure": "true",
     *       "context": "pbx.example.com"
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid starting_after UUID" {"error":{"type":"invalid_request_error","message":"Invalid starting_after UUID.","code":"invalid_request","param":"starting_after"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     */
    public function index(
        Request $request,
        FreeswitchEslService $eslService,
        ActiveCallService $activeCallService,
        string $domain_uuid
    ) {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->first(['domain_uuid', 'domain_name']);

        if (! $domain) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        $limit = (int) $request->input('limit', 50);
        $limit = max(1, min(100, $limit));

        $startingAfter = (string) $request->input('starting_after', '');
        if ($startingAfter !== '' && ! preg_match('/^[0-9a-fA-F-]{36}$/', $startingAfter)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid starting_after UUID.', 'invalid_request', 'starting_after');
        }

        $calls = $activeCallService->getCalls($eslService, [
            'domain_uuid' => $domain_uuid,
            'domain_name' => (string) $domain->domain_name,
            'viewer_timezone' => $user->time_zone ?? 'UTC',
            'search' => (string) $request->input('search', ''),
            'sortField' => (string) $request->input('sort', 'created_epoch'),
            'sortOrder' => (string) $request->input('order', 'desc'),
            'showGlobal' => false,
        ]);

        if ($startingAfter !== '') {
            $position = $calls->search(fn ($call) => ($call['uuid'] ?? null) === $startingAfter);
            $calls = $position === false ? collect() : $calls->slice($position + 1)->values();
        }

        $hasMore = $calls->count() > $limit;
        $calls = $calls->take($limit);

        $data = $calls->map(function ($call) use ($domain_uuid) {
            return [
                'uuid' => (string) ($call['uuid'] ?? ''),
                'object' => 'active_call',
                'domain_uuid' => $domain_uuid,
                'direction' => $call['direction'] ?? null,
                'created_epoch' => isset($call['created_epoch']) ? (string) $call['created_epoch'] : null,
                'created_display' => $call['created_display'] ?? null,
                'duration_seconds' => isset($call['duration_seconds']) ? (int) $call['duration_seconds'] : null,
                'cid_name' => $call['cid_name'] ?? null,
                'cid_num' => $call['cid_num'] ?? null,
                'dest' => $call['dest'] ?? null,
                'application' => $call['application'] ?? null,
                'application_data' => $call['application_data'] ?? null,
                'secure' => isset($call['secure']) ? (string) $call['secure'] : null,
                'context' => $call['context'] ?? null,
            ];
        })->all();

        return response()->json([
            'object' => 'list',
            'url' => "/api/v1/domains/{$domain_uuid}/active-calls",
            'has_more' => $hasMore,
            'data' => $data,
        ], 200);
    }

    /**
     * Retrieve an active call
     *
     * Returns a single active call for the specified domain.
     *
     * @group Active Calls
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam call_uuid string required The active call UUID. Example: c0ec8113-aa15-40ac-8437-47185dd9dcf4
     *
     * @response 200 scenario="Success" {
     *   "uuid": "c0ec8113-aa15-40ac-8437-47185dd9dcf4",
     *   "object": "active_call",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "direction": "inbound",
     *   "created_epoch": "1774920000",
     *   "created_display": "2026-04-30 14:00:00",
     *   "duration_seconds": 42,
     *   "cid_name": "Alice Johnson",
     *   "cid_num": "1001",
     *   "dest": "1002",
     *   "application": "bridge",
     *   "application_data": "sofia/internal/1002@pbx.example.com",
     *   "secure": "true",
     *   "context": "pbx.example.com"
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid call UUID" {"error":{"type":"invalid_request_error","message":"Invalid call UUID.","code":"invalid_request","param":"call_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="Active call not found" {"error":{"type":"invalid_request_error","message":"Active call not found.","code":"resource_missing","param":"call_uuid"}}
     */
    public function show(
        Request $request,
        FreeswitchEslService $eslService,
        ActiveCallService $activeCallService,
        string $domain_uuid,
        string $call_uuid
    ) {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $call_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid call UUID.', 'invalid_request', 'call_uuid');
        }

        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->first(['domain_uuid', 'domain_name']);

        if (! $domain) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        $call = $activeCallService->findCallByUuid($eslService, $call_uuid, [
            'domain_uuid' => $domain_uuid,
            'domain_name' => (string) $domain->domain_name,
            'viewer_timezone' => $user->time_zone ?? 'UTC',
            'showGlobal' => false,
        ]);

        if (! $call) {
            throw new ApiException(404, 'invalid_request_error', 'Active call not found.', 'resource_missing', 'call_uuid');
        }

        return response()->json($this->toActiveCallData($call, $domain_uuid)->toArray(), 200);
    }

    /**
     * End an active call
     *
     * Ends a single active call within the specified domain.
     *
     * @group Active Calls
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam call_uuid string required The active call UUID. Example: c0ec8113-aa15-40ac-8437-47185dd9dcf4
     *
     * @response 200 scenario="Success" {"object":"active_call","uuid":"c0ec8113-aa15-40ac-8437-47185dd9dcf4","deleted":true}
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid call UUID" {"error":{"type":"invalid_request_error","message":"Invalid call UUID.","code":"invalid_request","param":"call_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="Active call not found" {"error":{"type":"invalid_request_error","message":"Active call not found.","code":"resource_missing","param":"call_uuid"}}
     * @response 500 scenario="Internal server error" {"error":{"type":"api_error","message":"Internal server error.","code":"internal_error"}}
     */
    public function destroy(
        Request $request,
        FreeswitchEslService $eslService,
        ActiveCallService $activeCallService,
        string $domain_uuid,
        string $call_uuid
    ) {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $call_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid call UUID.', 'invalid_request', 'call_uuid');
        }

        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->first(['domain_uuid', 'domain_name']);

        if (! $domain) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        $call = $activeCallService->findCallByUuid($eslService, $call_uuid, [
            'domain_uuid' => $domain_uuid,
            'domain_name' => (string) $domain->domain_name,
            'viewer_timezone' => $user->time_zone ?? 'UTC',
            'showGlobal' => false,
        ]);

        if (! $call) {
            throw new ApiException(404, 'invalid_request_error', 'Active call not found.', 'resource_missing', 'call_uuid');
        }

        try {
            if (! $eslService->isConnected()) {
                $eslService->reconnect();
            }

            $eslService->killChannel($call_uuid);

            $payload = DeletedResponseData::from([
                'uuid' => $call_uuid,
                'object' => 'active_call',
                'deleted' => true,
            ]);

            return response()->json($payload->toArray(), 200);
        } catch (\Throwable $e) {
            logger('API ActiveCall delete error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }

    private function toActiveCallData(array $call, string $domain_uuid): ActiveCallData
    {
        return new ActiveCallData(
            uuid: (string) ($call['uuid'] ?? ''),
            object: 'active_call',
            domain_uuid: $domain_uuid,
            direction: $call['direction'] ?? null,
            created: $call['created'] ?? null,
            created_epoch: isset($call['created_epoch']) ? (string) $call['created_epoch'] : null,
            created_display: $call['created_display'] ?? null,
            start_epoch: isset($call['start_epoch']) ? (int) $call['start_epoch'] : null,
            duration_seconds: isset($call['duration_seconds']) ? (int) $call['duration_seconds'] : null,
            name: $call['name'] ?? null,
            state: $call['state'] ?? null,
            cid_name: $call['cid_name'] ?? null,
            cid_num: $call['cid_num'] ?? null,
            ip_addr: $call['ip_addr'] ?? null,
            dest: $call['dest'] ?? null,
            application: $call['application'] ?? null,
            application_data: $call['application_data'] ?? null,
            app_full: $call['app_full'] ?? null,
            app_preview: $call['app_preview'] ?? null,
            dialplan: $call['dialplan'] ?? null,
            context: $call['context'] ?? null,
            read_codec: $call['read_codec'] ?? null,
            read_rate: isset($call['read_rate']) ? (string) $call['read_rate'] : null,
            read_bit_rate: isset($call['read_bit_rate']) ? (string) $call['read_bit_rate'] : null,
            write_codec: $call['write_codec'] ?? null,
            write_rate: isset($call['write_rate']) ? (string) $call['write_rate'] : null,
            write_bit_rate: isset($call['write_bit_rate']) ? (string) $call['write_bit_rate'] : null,
            secure: isset($call['secure']) ? (string) $call['secure'] : null,
            hostname: $call['hostname'] ?? null,
            presence_id: $call['presence_id'] ?? null,
            presence_data: $call['presence_data'] ?? null,
            accountcode: $call['accountcode'] ?? null,
            callstate: $call['callstate'] ?? null,
            callee_name: $call['callee_name'] ?? null,
            callee_num: $call['callee_num'] ?? null,
            callee_direction: $call['callee_direction'] ?? null,
            call_uuid: $call['call_uuid'] ?? null,
            sent_callee_name: $call['sent_callee_name'] ?? null,
            sent_callee_num: $call['sent_callee_num'] ?? null,
            initial_cid_name: $call['initial_cid_name'] ?? null,
            initial_cid_num: $call['initial_cid_num'] ?? null,
            initial_ip_addr: $call['initial_ip_addr'] ?? null,
            initial_dest: $call['initial_dest'] ?? null,
            initial_dialplan: $call['initial_dialplan'] ?? null,
            initial_context: $call['initial_context'] ?? null,
            display_timezone: $call['display_timezone'] ?? null,
        );
    }
}
