<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Domain;
use App\Models\Dialplans;
use App\Models\RingGroups;
use App\Models\FusionCache;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use App\Data\Api\V1\RingGroupData;
use App\Services\RingGroupService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use App\Services\FreeswitchEslService;
use App\Data\Api\V1\DeletedResponseData;
use App\Data\Api\V1\RingGroupDestinationData;
use App\Http\Requests\Api\V1\StoreRingGroupRequest;
use App\Http\Requests\Api\V1\UpdateRingGroupRequest;

class RingGroupController extends Controller
{
    /**
     * List ring groups
     *
     * Returns ring groups for the specified domain the caller is allowed to access.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `ring_group_view` permission.
     *
     * Pagination (cursor-based):
     * - Both `limit` and `starting_after` are optional.
     * - If `limit` is not provided, it defaults to 50.
     * - If `starting_after` is not provided, results start from the beginning.
     * - If `has_more` is true, request the next page by passing `starting_after`
     *   equal to the last item's `ring_group_uuid` from the previous response.
     *
     * Examples:
     * - First page: `GET /api/v1/domains/{domain_uuid}/ring-groups`
     * - Next page:  `GET /api/v1/domains/{domain_uuid}/ring-groups?starting_after={last_ring_group_uuid}`
     * - Custom size: `GET /api/v1/domains/{domain_uuid}/ring-groups?limit=50`
     *
     * @group Ring Groups
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @queryParam limit integer Optional. Number of results to return (min 1, max 100). Defaults to 50. Example: 50
     * @queryParam starting_after string Optional. Return results after this ring group UUID (cursor). Example: c0ec8113-aa15-40ac-8437-47185dd9dcf4
     *
     * @response 200 scenario="Success" {
     *   "object": "list",
     *   "url": "/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/ring-groups",
     *   "has_more": true,
     *   "data": [
     *     {
     *       "ring_group_uuid": "c0ec8113-aa15-40ac-8437-47185dd9dcf4",
     *       "object": "ring_group",
     *       "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *
     *       "ring_group_name": "Sales Ring Group",
     *       "ring_group_extension": "9000",
     *
     *       "ring_group_enabled": true,
     *       "ring_group_description": null
     *
     *     }
     *   ]
     * }
     *
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid starting_after UUID" {"error":{"type":"invalid_request_error","message":"Invalid starting_after UUID.","code":"invalid_request","param":"starting_after"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
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

        $textBool = static function ($value): ?bool {
            // v_ring_groups stores booleans as text ('true'/'false')
            if ($value === null || $value === '') return null;
            if (is_bool($value)) return $value;
            if (is_numeric($value)) return ((int)$value) === 1;
            $v = strtolower(trim((string)$value));
            if (in_array($v, ['true', 't', '1', 'yes', 'y', 'on'], true)) return true;
            if (in_array($v, ['false', 'f', '0', 'no', 'n', 'off'], true)) return false;
            return null;
        };

        // $nativeBool = static function ($value): ?bool {
        //     // v_ring_group_destinations has real boolean + numeric prompt
        //     if ($value === null || $value === '') return null;
        //     if (is_bool($value)) return $value;
        //     if (is_numeric($value)) return ((int)$value) === 1;
        //     return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        // };

        $query = QueryBuilder::for(RingGroups::class)
            ->where('domain_uuid', $domain_uuid)
            ->defaultSort('ring_group_uuid')
            ->reorder('ring_group_uuid')
            ->limit($limit + 1)
            ->select([
                'ring_group_uuid',
                'domain_uuid',
                'ring_group_name',
                'ring_group_extension',
                'ring_group_call_forward_enabled',
                'ring_group_follow_me_enabled',
                'ring_group_enabled',
                'ring_group_description',
                'ring_group_forward_destination',
                'ring_group_forward_enabled',
            ]);

        if ($startingAfter !== '') {
            if (! preg_match('/^[0-9a-fA-F-]{36}$/', $startingAfter)) {
                throw new ApiException(400, 'invalid_request_error', 'Invalid starting_after UUID.', 'invalid_request', 'starting_after');
            }
            $query->where('ring_group_uuid', '>', $startingAfter);
        }

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $data = $rows->map(function ($rg) use ($textBool) {

            return new RingGroupData(
                ring_group_uuid: (string) $rg->ring_group_uuid,
                object: 'ring_group',
                domain_uuid: (string) $rg->domain_uuid,

                ring_group_name: (string) $rg->ring_group_name,
                ring_group_extension: (string) $rg->ring_group_extension,

                ring_group_enabled: $textBool($rg->ring_group_enabled),
                ring_group_description: $rg->ring_group_description,

            );
        });

        $url = "/api/v1/domains/{$domain_uuid}/ring-groups";

        return response()->json([
            'object' => 'list',
            'url' => $url,
            'has_more' => $hasMore,
            'data' => $data,
        ], 200);
    }

    /**
     * Retrieve a ring group
     *
     * Returns a single ring group for the specified domain the caller is allowed to access.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `ring_group_view` permission.
     *
     * @group Ring Groups
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 7d58342b-2b29-4dcf-92d6-e9a9e002a4e5
     * @urlParam ring_group_uuid string required The ring group UUID. Example: 40aec3e8-a572-40da-954b-ddf6a8a65324
     *
     * @response 200 scenario="Success" {
     *   "ring_group_uuid": "40aec3e8-a572-40da-954b-ddf6a8a65324",
     *   "object": "ring_group",
     *   "domain_uuid": "7d58342b-2b29-4dcf-92d6-e9a9e002a4e5",
     *   "ring_group_name": "Seattle Main Group",
     *   "ring_group_extension": "0",
     *   "ring_group_greeting": null,
     *   "ring_group_caller_id_name": null,
     *   "ring_group_caller_id_number": null,
     *   "ring_group_cid_name_prefix": null,
     *   "ring_group_cid_number_prefix": null,
     *   "ring_group_strategy": "sequence",
     *   "timeout_action": "voicemails",
     *   "timeout_target": "101",
     *   "ring_group_call_forward_enabled": true,
     *   "ring_group_follow_me_enabled": true,
     *   "ring_group_description": null,
     *   "ring_group_forward_enabled": false,
     *   "forward_action": "external",
     *   "forward_target": "456",
     *   "ring_group_enabled": true,
     *   "members": [
     *     {
     *       "ring_group_destination_uuid": "4ec29bfd-9f4b-4a4c-b934-0015412711c9",
     *       "ring_group_uuid": "40aec3e8-a572-40da-954b-ddf6a8a65324",
     *       "destination_number": "100",
     *       "destination_enabled": true,
     *       "destination_delay": 0,
     *       "destination_timeout": 25,
     *       "destination_prompt": false
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid ring group UUID" {"error":{"type":"invalid_request_error","message":"Invalid ring group UUID.","code":"invalid_request","param":"ring_group_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="Ring group not found" {"error":{"type":"invalid_request_error","message":"Ring group not found.","code":"resource_missing","param":"ring_group_uuid"}}
     */
    public function show(Request $request, string $domain_uuid, string $ring_group_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $ring_group_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid ring group UUID.', 'invalid_request', 'ring_group_uuid');
        }

        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->first(['domain_uuid', 'domain_name']);

        if (! $domain) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        $payload = $this->buildRingGroupShowPayload($domain_uuid, $ring_group_uuid);

        return response()->json($payload->toArray(), 200);
    }

    /**
     * Create a ring group
     *
     * Creates a new ring group in the specified domain.
     *
     *
     * @group Ring Groups
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     *
     * @response 201 scenario="Created" {
     *   "ring_group_uuid": "40aec3e8-a572-40da-954b-ddf6a8a65324",
     *   "object": "ring_group",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "ring_group_name": "Sales Ring Group",
     *   "ring_group_extension": "9000",
     *   "ring_group_greeting": null,
     *   "ring_group_caller_id_name": null,
     *   "ring_group_caller_id_number": null,
     *   "ring_group_cid_name_prefix": null,
     *   "ring_group_cid_number_prefix": null,
     *   "ring_group_strategy": "sequence",
     *   "timeout_action": "voicemails",
     *   "timeout_target": "101",
     *   "ring_group_call_forward_enabled": true,
     *   "ring_group_follow_me_enabled": true,
     *   "ring_group_description": null,
     *   "ring_group_forward_enabled": false,
     *   "forward_action": "external",
     *   "forward_target": "456",
     *   "ring_group_enabled": true,
     *   "members": [
     *     {
     *       "ring_group_destination_uuid": "4ec29bfd-9f4b-4a4c-b934-0015412711c9",
     *       "ring_group_uuid": "40aec3e8-a572-40da-954b-ddf6a8a65324",
     *       "destination_number": "100",
     *       "destination_enabled": true,
     *       "destination_delay": 0,
     *       "destination_timeout": 25,
     *       "destination_prompt": false
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 422 scenario="Validation error" {"error":{"type":"invalid_request_error","message":"The given data was invalid.","code":"invalid_request","param":null,"details":{"ring_group_extension":["The ring group extension has already been taken."]}}}
     */
    public function store(StoreRingGroupRequest $request, string $domain_uuid)
    {
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

        $validated = $request->validated();

        try {
            /** @var \App\Models\RingGroups $rg */
            $rg = DB::transaction(function () use ($validated, $domain, $domain_uuid) {

                $createData = array_merge($validated, [
                    'domain_uuid'        => $domain_uuid,
                    'ring_group_context' => (string) $domain->domain_name,

                    'ring_group_enabled' => 'true',
                    'ring_group_strategy' => $validated['ring_group_strategy'] ?? 'enterprise',
                    'ring_group_ringback' => $validated['ring_group_ringback'] ?? '${us-ring}',

                    'ring_group_call_forward_enabled' => function_exists('get_domain_setting')
                        ? (get_domain_setting('honor_member_cfwd', $domain_uuid) ? 'true' : 'false')
                        : 'false',

                    'ring_group_follow_me_enabled' => function_exists('get_domain_setting')
                        ? (get_domain_setting('honor_member_followme', $domain_uuid) ? 'true' : 'false')
                        : 'false',

                    'dialplan_uuid' => (string) Str::uuid(),
                ]);

                // Derived fields (same logic as internal)
                $derived = app(RingGroupService::class)->buildUpdateData($validated, (string) $domain->domain_name);

                $createData = array_merge($createData, $derived);

                $rg = RingGroups::create($createData);

                // Destinations: only if "members" key is present
                if (array_key_exists('members', $validated)) {
                    $members = is_array($validated['members'] ?? null) ? $validated['members'] : [];

                    $strategy = $validated['ring_group_strategy'] ?? $rg->ring_group_strategy;
                    if ($strategy === 'sequence') {
                        foreach ($members as $i => &$m) {
                            $m['destination_delay'] = (string) ($i * 5);
                        }
                        unset($m);
                    }

                    $rows = $this->buildDestinationRows($members, $domain_uuid, (string) $rg->ring_group_uuid);

                    if (! empty($rows)) {
                        $rg->destinations()->insert($rows);
                    }
                }

                return $rg->fresh();
            });

            // Return SAME payload format as show()
            $payload = $this->buildRingGroupShowPayload($domain_uuid, (string) $rg->ring_group_uuid);

            return response()
                ->json($payload->toArray(), 201)
                ->header('Location', "/api/v1/domains/{$domain_uuid}/ring-groups/{$rg->ring_group_uuid}");
        } catch (\Throwable $e) {
            logger('API RingGroup store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }

    /**
     * Update a ring group
     *
     * Updates an existing ring group in the specified domain.
     *
     * Notes:
     * - Fields are optional. If omitted, the current value is unchanged.
     * - If `members` is present:
     *   - members omitted => no change
     *   - members: [] => deletes all members
     *   - members: [...] => replaces all members
     * - The response payload matches the `show` endpoint shape.
     *
     * @group Ring Groups
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam ring_group_uuid string required The ring group UUID. Example: 40aec3e8-a572-40da-954b-ddf6a8a65324
     *
     *
     * @response 200 scenario="Success" {
     *   "ring_group_uuid": "40aec3e8-a572-40da-954b-ddf6a8a65324",
     *   "object": "ring_group",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "ring_group_name": "Sales Ring Group",
     *   "ring_group_extension": "9000",
     *   "ring_group_greeting": null,
     *   "ring_group_caller_id_name": null,
     *   "ring_group_caller_id_number": null,
     *   "ring_group_cid_name_prefix": null,
     *   "ring_group_cid_number_prefix": null,
     *   "ring_group_strategy": "sequence",
     *   "timeout_action": "voicemails",
     *   "timeout_target": "101",
     *   "ring_group_call_forward_enabled": true,
     *   "ring_group_follow_me_enabled": true,
     *   "ring_group_description": null,
     *   "ring_group_forward_enabled": false,
     *   "forward_action": "external",
     *   "forward_target": "456",
     *   "ring_group_enabled": true,
     *   "members": [
     *     {
     *       "ring_group_destination_uuid": "4ec29bfd-9f4b-4a4c-b934-0015412711c9",
     *       "ring_group_uuid": "40aec3e8-a572-40da-954b-ddf6a8a65324",
     *       "destination_number": "100",
     *       "destination_enabled": true,
     *       "destination_delay": 0,
     *       "destination_timeout": 25,
     *       "destination_prompt": false
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid ring group UUID" {"error":{"type":"invalid_request_error","message":"Invalid ring group UUID.","code":"invalid_request","param":"ring_group_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="Ring group not found" {"error":{"type":"invalid_request_error","message":"Ring group not found.","code":"resource_missing","param":"ring_group_uuid"}}
     * @response 422 scenario="Validation error" {"error":{"type":"invalid_request_error","message":"The given data was invalid.","code":"invalid_request","param":null,"details":{"ring_group_extension":["The ring group extension has already been taken."]}}}
     */

    public function update(UpdateRingGroupRequest $request, string $domain_uuid, string $ring_group_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $ring_group_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid ring group UUID.', 'invalid_request', 'ring_group_uuid');
        }

        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->first(['domain_uuid', 'domain_name']);

        if (! $domain) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        /** @var \App\Models\RingGroups|null $rg */
        $rg = RingGroups::query()
            ->where('domain_uuid', $domain_uuid)
            ->where('ring_group_uuid', $ring_group_uuid)
            ->first();

        if (! $rg) {
            throw new ApiException(404, 'invalid_request_error', 'Ring group not found.', 'resource_missing', 'ring_group_uuid');
        }

        $validated = $request->validated();

        try {
            DB::transaction(function () use ($validated, $domain_uuid, $domain, $rg) {

                $updateData = app(RingGroupService::class)->buildUpdateData($validated, (string) $domain->domain_name);

                $rg->update($updateData);

                if (array_key_exists('members', $validated)) {
                    $rg->destinations()->delete();

                    $members = is_array($validated['members'] ?? null) ? $validated['members'] : [];

                    $strategy = $validated['ring_group_strategy'] ?? $rg->ring_group_strategy;
                    if ($strategy === 'sequence') {
                        foreach ($members as $i => &$m) {
                            $m['destination_delay'] = (string) ($i * 5);
                        }
                        unset($m);
                    }

                    $rows = $this->buildDestinationRows($members, $domain_uuid, (string) $rg->ring_group_uuid);

                    if (! empty($rows)) {
                        $rg->destinations()->insert($rows);
                    }
                }
            });

            // Return SAME payload format as show()
            $payload = $this->buildRingGroupShowPayload($domain_uuid, (string) $ring_group_uuid);

            return response()->json($payload->toArray(), 200);
        } catch (\Throwable $e) {
            logger('API RingGroup update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }


    /**
     * Builds response payload as a reusable helper.
     */
    private function buildRingGroupShowPayload(string $domain_uuid, string $ring_group_uuid): RingGroupData
    {
        // v_ring_groups stores booleans as text ('true'/'false')
        $textBool = static function ($value): ?bool {
            if ($value === null || $value === '') return null;
            if (is_bool($value)) return $value;
            if (is_numeric($value)) return ((int) $value) === 1;
            $v = strtolower(trim((string) $value));
            if (in_array($v, ['true', 't', '1', 'yes', 'y', 'on'], true)) return true;
            if (in_array($v, ['false', 'f', '0', 'no', 'n', 'off'], true)) return false;
            return null;
        };

        // destinations has native boolean + numeric prompt
        $nativeBool = static function ($value): ?bool {
            if ($value === null || $value === '') return null;
            if (is_bool($value)) return $value;
            if (is_numeric($value)) return ((int) $value) === 1;
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        };

        $rg = RingGroups::query()
            ->where('domain_uuid', $domain_uuid)
            ->where('ring_group_uuid', $ring_group_uuid)
            ->select([
                'ring_group_uuid',
                'domain_uuid',
                'ring_group_name',
                'ring_group_extension',
                'ring_group_greeting',
                'ring_group_caller_id_name',
                'ring_group_caller_id_number',
                'ring_group_cid_name_prefix',
                'ring_group_cid_number_prefix',
                'ring_group_strategy',
                'ring_group_timeout_app',
                'ring_group_timeout_data',
                'ring_group_call_forward_enabled',
                'ring_group_follow_me_enabled',
                'ring_group_enabled',
                'ring_group_description',
                'ring_group_forward_destination',
                'ring_group_forward_enabled',
            ])
            ->with([
                'destinations' => function ($q) use ($domain_uuid) {
                    $q->select([
                        'ring_group_destination_uuid',
                        'ring_group_uuid',
                        'domain_uuid',
                        'destination_number',
                        'destination_delay',
                        'destination_timeout',
                        'destination_prompt',
                        'destination_enabled',
                    ])
                        ->where('domain_uuid', $domain_uuid)
                        ->orderBy('destination_delay', 'asc')
                        ->orderBy('ring_group_destination_uuid', 'asc');
                },
            ])
            ->first();

        if (! $rg) {
            // Should not happen after store/update, but keeps behavior consistent
            throw new ApiException(404, 'invalid_request_error', 'Ring group not found.', 'resource_missing', 'ring_group_uuid');
        }

        $rg->append([
            'timeout_target_uuid',
            'timeout_action',
            'timeout_target_extension',
            'forward_target_uuid',
            'forward_action',
            'forward_target_extension',
        ]);

        $destinations = [];
        if ($rg->relationLoaded('destinations')) {
            $destinations = $rg->destinations->map(function ($d) use ($nativeBool) {
                return new RingGroupDestinationData(
                    ring_group_destination_uuid: (string) $d->ring_group_destination_uuid,
                    ring_group_uuid: (string) $d->ring_group_uuid,
                    destination_number: (string) $d->destination_number,
                    destination_enabled: $nativeBool($d->destination_enabled),
                    destination_delay: $d->destination_delay !== null ? (int) $d->destination_delay : null,
                    destination_timeout: $d->destination_timeout !== null ? (int) $d->destination_timeout : null,
                    destination_prompt: $nativeBool($d->destination_prompt) ?? false
                );
            })->all();
        }

        return new RingGroupData(
            ring_group_uuid: (string) $rg->ring_group_uuid,
            object: 'ring_group',
            domain_uuid: (string) $rg->domain_uuid,

            ring_group_name: (string) $rg->ring_group_name,
            ring_group_extension: (string) $rg->ring_group_extension,

            ring_group_greeting: $rg->ring_group_greeting,

            ring_group_caller_id_name: $rg->ring_group_caller_id_name,
            ring_group_caller_id_number: $rg->ring_group_caller_id_number,
            ring_group_cid_name_prefix: $rg->ring_group_cid_name_prefix,
            ring_group_cid_number_prefix: $rg->ring_group_cid_number_prefix,

            ring_group_strategy: $rg->ring_group_strategy,
            timeout_action: $rg->timeout_action,
            timeout_target: $rg->timeout_target_extension,

            ring_group_call_forward_enabled: $textBool($rg->ring_group_call_forward_enabled),
            ring_group_follow_me_enabled: $textBool($rg->ring_group_follow_me_enabled),

            ring_group_description: $rg->ring_group_description,

            ring_group_forward_enabled: $textBool($rg->ring_group_forward_enabled),
            forward_action: $rg->forward_action,
            forward_target: $rg->forward_target_extension,

            ring_group_enabled: $textBool($rg->ring_group_enabled),

            members: $destinations,
        );
    }


    /**
     * Helper for bulk destination insert
     */
    private function buildDestinationRows(array $members, string $domainUuid, string $ringGroupUuid): array
    {
        $now = now();
        $rows = [];

        foreach ($members as $member) {
            $rows[] = [
                'ring_group_destination_uuid' => (string) Str::uuid(),
                'domain_uuid'                 => $domainUuid,
                'ring_group_uuid'             => $ringGroupUuid,

                'destination_number'          => $member['destination_number'] ?? null,
                'destination_delay'           => isset($member['destination_delay']) ? (float) $member['destination_delay'] : 0,
                'destination_timeout'         => isset($member['destination_timeout']) ? (float) $member['destination_timeout'] : 0,

                'destination_enabled'         => ! empty($member['destination_enabled']),
                'destination_prompt'          => ! empty($member['destination_prompt']) ? 1 : null,

                'update_date'                 => $now,
            ];
        }

        return $rows;
    }

    /**
     * Delete a ring group
     *
     * Deletes a ring group within the specified domain.
     *
     *
     * @group Ring Groups
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam ring_group_uuid string required The ring group UUID. Example: c0ec8113-aa15-40ac-8437-47185dd9dcf4
     *
     * @response 200 scenario="Success" {"object":"ring_group","uuid":"c0ec8113-aa15-40ac-8437-47185dd9dcf4","deleted":true}
     */
    public function destroy(Request $request, string $domain_uuid, string $ring_group_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $ring_group_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid ring group UUID.', 'invalid_request', 'ring_group_uuid');
        }

        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->first(['domain_uuid', 'domain_name']);

        if (! $domain) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        $rg = RingGroups::query()
            ->where('domain_uuid', $domain_uuid)
            ->where('ring_group_uuid', $ring_group_uuid)
            ->first();

        if (! $rg) {
            throw new ApiException(404, 'invalid_request_error', 'Ring group not found.', 'resource_missing', 'ring_group_uuid');
        }

        try {
            DB::transaction(function () use ($rg) {
                // Delete destinations
                if (method_exists($rg, 'destinations')) {
                    $rg->destinations()->delete();
                }

                // Delete dialplan (if exists)
                if (!empty($rg->dialplan_uuid)) {
                    Dialplans::where('dialplan_uuid', $rg->dialplan_uuid)->delete();
                }

                $context = $rg->ring_group_context;
                $rg->delete();


                // Reload XML from FreeSWITCH
                $freeSwitchService = new FreeswitchEslService();
                $command = 'bgapi reloadxml';
                $result = $freeSwitchService->executeCommand($command);
            });

            $payload = DeletedResponseData::from([
                'uuid' => (string) $ring_group_uuid,
                'object' => 'ring_group',
                'deleted' => true,
            ]);

            return response()->json($payload->toArray(), 200);
        } catch (\Exception $e) {
            logger('API RingGroup delete QueryException: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        } catch (\Throwable $e) {
            logger('API RingGroup delete error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }

    // ------------------------
    // Helpers (ported from internal controller)
    // ------------------------

    protected function buildExitDestinationAction(array $inputs, string $domainName): array
    {
        $action = $inputs['fallback_action'] ?? null;

        switch ($action) {
            case 'extensions':
            case 'ring_groups':
            case 'ivrs':
            case 'business_hours':
            case 'time_conditions':
            case 'contact_centers':
            case 'faxes':
            case 'call_flows':
                return ['action' => 'transfer', 'data' => ($inputs['fallback_target'] ?? '') . ' XML ' . $domainName];

            case 'voicemails':
                return ['action' => 'transfer', 'data' => '*99' . ($inputs['fallback_target'] ?? '') . ' XML ' . $domainName];

            case 'recordings':
                return ['action' => 'lua', 'data' => 'streamfile.lua ' . ($inputs['fallback_target'] ?? '')];

            case 'check_voicemail':
                return ['action' => 'transfer', 'data' => '*98 XML ' . $domainName];

            case 'company_directory':
                return ['action' => 'transfer', 'data' => '*411 XML ' . $domainName];

            case 'hangup':
                return ['action' => 'hangup', 'data' => ''];

            default:
                return [];
        }
    }

    protected function buildForwardDestinationTarget(array $inputs): ?string
    {
        $action = $inputs['forward_action'] ?? null;

        switch ($action) {
            case 'extensions':
            case 'ring_groups':
            case 'ivrs':
            case 'business_hours':
            case 'time_conditions':
            case 'contact_centers':
            case 'faxes':
            case 'call_flows':
                return $inputs['forward_target'] ?? null;

            case 'voicemails':
                return isset($inputs['forward_target']) ? '*99' . $inputs['forward_target'] : null;

            case 'external':
                return $inputs['forward_external_target'] ?? $inputs['forward_target'];

            default:
                return null;
        }
    }

    protected function calculateTimeout(array $validated): int
    {
        $enabledMembers = array_filter($validated['members'] ?? [], fn($m) => !empty($m['enabled']));

        if (in_array($validated['ring_group_strategy'] ?? '', ['random', 'sequence', 'rollover'], true)) {
            return array_reduce($enabledMembers, fn($carry, $m) => $carry + (int) ($m['timeout'] ?? 0), 0);
        }

        return collect($enabledMembers)
            ->map(fn($m) => (int) ($m['delay'] ?? 0) + (int) ($m['timeout'] ?? 0))
            ->max() ?? 0;
    }

    protected function generateDialPlanXML(RingGroups $ringGroup, string $domainName, string $domainUuid, ?string $userUuid = null): void
    {
        $xml = view('layouts.xml.ring-group-dial-plan-template', [
            'ring_group' => $ringGroup,
        ])->render();

        $dialPlan = Dialplans::where('dialplan_uuid', $ringGroup->dialplan_uuid)->first();

        if (!$dialPlan) {
            $dialPlan = new Dialplans();
            $dialPlan->dialplan_uuid = $ringGroup->dialplan_uuid;
            $dialPlan->app_uuid = '1d61fb65-1eec-bc73-a6ee-a6203b4fe6f2';
            $dialPlan->domain_uuid = $domainUuid;
            $dialPlan->dialplan_name = $ringGroup->ring_group_name;
            $dialPlan->dialplan_number = $ringGroup->ring_group_extension;
            $dialPlan->dialplan_context = $ringGroup->ring_group_context ?? $domainName;
            $dialPlan->dialplan_continue = 'false';
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_order = 101;
            $dialPlan->dialplan_enabled = $ringGroup->ring_group_enabled;
            $dialPlan->dialplan_description = $ringGroup->ring_group_description;
            $dialPlan->insert_date = date('Y-m-d H:i:s');
            $dialPlan->insert_user = $userUuid;
        } else {
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_name = $ringGroup->ring_group_name;
            $dialPlan->dialplan_number = $ringGroup->ring_group_extension;
            $dialPlan->dialplan_description = $ringGroup->ring_group_description;
            $dialPlan->update_date = date('Y-m-d H:i:s');
            $dialPlan->update_user = $userUuid;
        }

        $dialPlan->save();

        // Reload XML from FreeSWITCH
        $fp = event_socket_create(
            config('eventsocket.ip'),
            config('eventsocket.port'),
            config('eventsocket.password')
        );
        event_socket_request($fp, 'bgapi reloadxml');

        // Clear FusionPBX cache
        FusionCache::clear("dialplan:" . ($ringGroup->ring_group_context ?? $domainName));
    }
}
