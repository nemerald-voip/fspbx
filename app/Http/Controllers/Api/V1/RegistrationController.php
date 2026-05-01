<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Domain;
use Illuminate\Http\Request;
use App\Services\DeviceActionService;
use App\Data\Api\V1\RegistrationData;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\RegistrationService;
use App\Data\Api\V1\DeletedResponseData;
use App\Services\FreeswitchEslService;

class RegistrationController extends Controller
{
    /**
     * List registrations
     *
     * Returns SIP registrations for the specified domain.
     *
     * Pagination (snapshot cursor-based):
     * - Both `limit` and `starting_after` are optional.
     * - If `limit` is not provided, it defaults to 50.
     * - If `starting_after` is not provided, results start from the beginning.
     *
     * @group Registrations
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @queryParam limit integer Optional. Number of results to return (min 1, max 100). Defaults to 50. Example: 50
     * @queryParam starting_after string Optional. Return results after this registration call-id in the current snapshot. Example: 8434b4e1-4f5d-4a85-9cab-2f31a8cb7010
     * @queryParam search string Optional. Search IP, agent, transport, profile, auth user, or auth realm. Example: 101
     * @queryParam sort string Optional. Sort field. One of: sip_auth_user, sip_auth_realm, agent, lan_ip, wan_ip, port, status, expsecs, ping_time, sip_profile_name. Defaults to sip_auth_user. Example: sip_auth_user
     * @queryParam order string Optional. Sort direction: asc or desc. Defaults to asc. Example: asc
     *
     * @response 200 scenario="Success" {
     *   "object": "list",
     *   "url": "/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/registrations",
     *   "has_more": false,
     *   "data": [
     *     {
     *       "call_id": "8434b4e1-4f5d-4a85-9cab-2f31a8cb7010",
     *       "object": "registration",
     *       "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *       "user": "101@pbx.example.com",
     *       "agent": "Yealink SIP-T46U 108.86.0.20",
     *       "lan_ip": "192.0.2.10",
     *       "wan_ip": "203.0.113.42",
     *       "port": "5060",
     *       "status": "Registered",
     *       "expsecs": "3600",
     *       "ping_time": "18",
     *       "sip_profile_name": "internal"
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     */
    public function index(
        Request $request,
        FreeswitchEslService $eslService,
        RegistrationService $registrationService,
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

        $registrations = $registrationService->getRegistrations($eslService, [
            'domain_name' => (string) $domain->domain_name,
            'search' => (string) $request->input('search', ''),
            'sortField' => (string) $request->input('sort', 'sip_auth_user'),
            'sortOrder' => (string) $request->input('order', 'asc'),
            'showGlobal' => false,
        ]);

        if ($startingAfter !== '') {
            $position = $registrations->search(fn ($registration) => ($registration['call_id'] ?? null) === $startingAfter);
            $registrations = $position === false ? collect() : $registrations->slice($position + 1)->values();
        }

        $hasMore = $registrations->count() > $limit;
        $registrations = $registrations->take($limit);

        $data = $registrations->map(function ($registration) use ($domain_uuid) {
            return [
                'call_id' => (string) ($registration['call_id'] ?? ''),
                'object' => 'registration',
                'domain_uuid' => $domain_uuid,
                'user' => $registration['user'] ?? null,
                'agent' => $registration['agent'] ?? null,
                'lan_ip' => $registration['lan_ip'] ?? null,
                'wan_ip' => $registration['wan_ip'] ?? null,
                'port' => isset($registration['port']) ? (string) $registration['port'] : null,
                'status' => $registration['status'] ?? null,
                'expsecs' => isset($registration['expsecs']) ? (string) $registration['expsecs'] : null,
                'ping_time' => isset($registration['ping_time']) ? (string) $registration['ping_time'] : null,
                'sip_profile_name' => $registration['sip_profile_name'] ?? null,
            ];
        })->all();

        return response()->json([
            'object' => 'list',
            'url' => "/api/v1/domains/{$domain_uuid}/registrations",
            'has_more' => $hasMore,
            'data' => $data,
        ], 200);
    }

    /**
     * Retrieve a registration
     *
     * Returns a single SIP registration for the specified domain.
     *
     * @group Registrations
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam call_id string required The registration call-id. Example: 8434b4e1-4f5d-4a85-9cab-2f31a8cb7010
     *
     * @response 200 scenario="Success" {
     *   "call_id": "8434b4e1-4f5d-4a85-9cab-2f31a8cb7010",
     *   "object": "registration",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "user": "101@pbx.example.com",
     *   "status": "Registered",
     *   "lan_ip": "192.0.2.10",
     *   "port": "5060",
     *   "contact": "sip:101@192.0.2.10:5060;transport=udp",
     *   "agent": "Yealink SIP-T46U 108.86.0.20",
     *   "transport": "UDP",
     *   "wan_ip": "203.0.113.42",
     *   "sip_profile_name": "internal",
     *   "sip_auth_user": "101",
     *   "sip_auth_realm": "pbx.example.com",
     *   "ping_time": "18",
     *   "expsecs": "3600"
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="Registration not found" {"error":{"type":"invalid_request_error","message":"Registration not found.","code":"resource_missing","param":"call_id"}}
     */
    public function show(
        Request $request,
        FreeswitchEslService $eslService,
        RegistrationService $registrationService,
        string $domain_uuid,
        string $call_id
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

        $registration = $registrationService->findRegistrationByCallId($eslService, $call_id, [
            'domain_name' => (string) $domain->domain_name,
            'showGlobal' => false,
        ]);

        if (! $registration) {
            throw new ApiException(404, 'invalid_request_error', 'Registration not found.', 'resource_missing', 'call_id');
        }

        return response()->json($this->toRegistrationData($registration, $domain_uuid)->toArray(), 200);
    }

    /**
     * Unregister a device
     *
     * Unregisters a device within the specified domain.
     *
     * @group Registrations
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam call_id string required The registration call-id. Example: 8434b4e1-4f5d-4a85-9cab-2f31a8cb7010
     *
     * @response 200 scenario="Success" {"object":"registration","uuid":"8434b4e1-4f5d-4a85-9cab-2f31a8cb7010","deleted":true}
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="Registration not found" {"error":{"type":"invalid_request_error","message":"Registration not found.","code":"resource_missing","param":"call_id"}}
     * @response 500 scenario="Internal server error" {"error":{"type":"api_error","message":"Internal server error.","code":"internal_error"}}
     */
    public function destroy(
        Request $request,
        FreeswitchEslService $eslService,
        RegistrationService $registrationService,
        DeviceActionService $deviceActionService,
        string $domain_uuid,
        string $call_id
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

        $registration = $registrationService->findRegistrationByCallId($eslService, $call_id, [
            'domain_name' => (string) $domain->domain_name,
            'showGlobal' => false,
        ]);

        if (! $registration) {
            throw new ApiException(404, 'invalid_request_error', 'Registration not found.', 'resource_missing', 'call_id');
        }

        try {
            $registrationService->unregister($registration, $eslService, $deviceActionService);

            $payload = DeletedResponseData::from([
                'uuid' => $call_id,
                'object' => 'registration',
                'deleted' => true,
            ]);

            return response()->json($payload->toArray(), 200);
        } catch (\Throwable $e) {
            logger('API Registration delete error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }

    /**
     * Restart a registered device
     *
     * Sends a restart event to the device associated with this SIP registration.
     *
     * @group Registrations
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam call_id string required The registration call-id. Example: 8434b4e1-4f5d-4a85-9cab-2f31a8cb7010
     *
     * @response 200 scenario="Success" {"object":"registration","uuid":"8434b4e1-4f5d-4a85-9cab-2f31a8cb7010","action":"restart","accepted":true}
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="Registration not found" {"error":{"type":"invalid_request_error","message":"Registration not found.","code":"resource_missing","param":"call_id"}}
     * @response 500 scenario="Internal server error" {"error":{"type":"api_error","message":"Internal server error.","code":"internal_error"}}
     */
    public function restart(
        Request $request,
        FreeswitchEslService $eslService,
        RegistrationService $registrationService,
        DeviceActionService $deviceActionService,
        string $domain_uuid,
        string $call_id
    ) {
        [$domain, $registration] = $this->resolveRegistration($request, $eslService, $registrationService, $domain_uuid, $call_id);

        try {
            $registrationService->reboot($registration, $deviceActionService);

            return response()->json([
                'object' => 'registration',
                'uuid' => $call_id,
                'action' => 'restart',
                'accepted' => true,
            ], 200);
        } catch (\Throwable $e) {
            logger('API Registration restart error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }

    /**
     * Sync a registered device
     *
     * Sends a sync/provision event to the device associated with this SIP registration.
     *
     * @group Registrations
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam call_id string required The registration call-id. Example: 8434b4e1-4f5d-4a85-9cab-2f31a8cb7010
     *
     * @response 200 scenario="Success" {"object":"registration","uuid":"8434b4e1-4f5d-4a85-9cab-2f31a8cb7010","action":"sync","accepted":true}
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="Registration not found" {"error":{"type":"invalid_request_error","message":"Registration not found.","code":"resource_missing","param":"call_id"}}
     * @response 500 scenario="Internal server error" {"error":{"type":"api_error","message":"Internal server error.","code":"internal_error"}}
     */
    public function sync(
        Request $request,
        FreeswitchEslService $eslService,
        RegistrationService $registrationService,
        DeviceActionService $deviceActionService,
        string $domain_uuid,
        string $call_id
    ) {
        [$domain, $registration] = $this->resolveRegistration($request, $eslService, $registrationService, $domain_uuid, $call_id);

        try {
            $registrationService->sync($registration, $deviceActionService);

            return response()->json([
                'object' => 'registration',
                'uuid' => $call_id,
                'action' => 'sync',
                'accepted' => true,
            ], 200);
        } catch (\Throwable $e) {
            logger('API Registration sync error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }

    private function toRegistrationData(array $registration, string $domain_uuid): RegistrationData
    {
        return new RegistrationData(
            call_id: (string) ($registration['call_id'] ?? ''),
            object: 'registration',
            domain_uuid: $domain_uuid,
            user: $registration['user'] ?? null,
            status: $registration['status'] ?? null,
            lan_ip: $registration['lan_ip'] ?? null,
            port: isset($registration['port']) ? (string) $registration['port'] : null,
            contact: $registration['contact'] ?? null,
            agent: $registration['agent'] ?? null,
            transport: $registration['transport'] ?? null,
            wan_ip: $registration['wan_ip'] ?? null,
            sip_profile_name: $registration['sip_profile_name'] ?? null,
            sip_auth_user: $registration['sip_auth_user'] ?? null,
            sip_auth_realm: $registration['sip_auth_realm'] ?? null,
            ping_time: isset($registration['ping_time']) ? (string) $registration['ping_time'] : null,
            expsecs: isset($registration['expsecs']) ? (string) $registration['expsecs'] : null,
        );
    }

    private function resolveRegistration(
        Request $request,
        FreeswitchEslService $eslService,
        RegistrationService $registrationService,
        string $domain_uuid,
        string $call_id
    ): array {
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

        $registration = $registrationService->findRegistrationByCallId($eslService, $call_id, [
            'domain_name' => (string) $domain->domain_name,
            'showGlobal' => false,
        ]);

        if (! $registration) {
            throw new ApiException(404, 'invalid_request_error', 'Registration not found.', 'resource_missing', 'call_id');
        }

        return [$domain, $registration];
    }
}
