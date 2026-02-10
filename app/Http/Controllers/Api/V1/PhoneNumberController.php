<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Domain;
use App\Models\Dialplans;
use App\Models\RingGroups;
use App\Models\FusionCache;
use Illuminate\Support\Str;
use App\Models\Destinations;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use App\Data\Api\V1\PhoneNumberData;
use App\Http\Controllers\Controller;
use App\Services\PhoneNumberService;
use Spatie\QueryBuilder\QueryBuilder;
use App\Services\FreeswitchEslService;
use App\Data\Api\V1\DeletedResponseData;
use App\Services\DialplanBuilderService;
use App\Jobs\BuildDialplanForPhoneNumber;
use App\Http\Requests\Api\V1\StorePhoneNumberRequest;
use App\Http\Requests\Api\V1\UpdatePhoneNumberRequest;

class PhoneNumberController extends Controller
{
    /**
     * List phone numbers
     *
     * Returns phone numbers for the specified domain the caller is allowed to access.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `destination_domain` permission.
     *
     * Pagination (cursor-based):
     * - Both `limit` and `starting_after` are optional.
     * - If `limit` is not provided, it defaults to 50.
     * - If `starting_after` is not provided, results start from the beginning.
     * - If `has_more` is true, request the next page by passing `starting_after`
     *   equal to the last item's `destination_uuid` from the previous response.
     *
     * Examples:
     * - First page: `GET /api/v1/domains/{domain_uuid}/phone-numbers`
     * - Next page:  `GET /api/v1/domains/{domain_uuid}/phone-numbers?starting_after={destination_uuid}`
     * - Custom size: `GET /api/v1/domains/{domain_uuid}/phone-numbers?limit=50`
     *
     * @group Phone Numbers
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @queryParam limit integer Optional. Number of results to return (min 1, max 100). Defaults to 50. Example: 50
     * @queryParam starting_after string Optional. Return results after this destination UUID (cursor). Example: c0ec8113-aa15-40ac-8437-47185dd9dcf4
     *
     * @response 200 scenario="Success" {
     *   "object": "list",
     *   "url": "/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/phone-numbers",
     *   "has_more": true,
     *   "data": [
     *     {
     *       "destination_uuid": "c0ec8113-aa15-40ac-8437-47185dd9dcf4",
     *       "object": "phone_number",
     *       "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *
     *       "destination_prefix": "1",
     *       "destination_number": "2135551212",
     *       "destination_enabled": true,
     *       "destination_description": null
     *     }
     *   ]
     * }
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

        // v_destinations stores booleans as text ('true'/'false')
        $textBool = static function ($value): ?bool {
            if ($value === null || $value === '') return null;
            if (is_bool($value)) return $value;
            if (is_numeric($value)) return ((int) $value) === 1;
            $v = strtolower(trim((string) $value));
            if (in_array($v, ['true', 't', '1', 'yes', 'y', 'on'], true)) return true;
            if (in_array($v, ['false', 'f', '0', 'no', 'n', 'off'], true)) return false;
            return null;
        };

        $query = QueryBuilder::for(Destinations::class)
            ->where('domain_uuid', $domain_uuid)
            ->defaultSort('destination_uuid')
            ->reorder('destination_uuid')
            ->limit($limit + 1)
            ->select([
                'destination_uuid',
                'domain_uuid',
                'destination_number',
                'destination_prefix',
                'destination_enabled',
                'destination_description',
            ]);

        if ($startingAfter !== '') {
            if (! preg_match('/^[0-9a-fA-F-]{36}$/', $startingAfter)) {
                throw new ApiException(400, 'invalid_request_error', 'Invalid starting_after UUID.', 'invalid_request', 'starting_after');
            }
            $query->where('destination_uuid', '>', $startingAfter);
        }

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $data = $rows->map(function ($d) use ($textBool) {
            return new PhoneNumberData(
                destination_uuid: (string) $d->destination_uuid,
                object: 'phone_number',
                domain_uuid: (string) $d->domain_uuid,

                destination_number: $d->destination_number !== null ? (string) $d->destination_number : null,
                destination_prefix: $d->destination_prefix !== null ? (string) $d->destination_prefix : null,

                destination_enabled: $textBool($d->destination_enabled),
                destination_description: $d->destination_description,
            );
        });

        $url = "/api/v1/domains/{$domain_uuid}/phone-numbers";

        return response()->json([
            'object' => 'list',
            'url' => $url,
            'has_more' => $hasMore,
            'data' => $data,
        ], 200);
    }

    /**
     * Retrieve  phone number
     *
     * Returns a single phone number for the specified domain the caller is allowed to access.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `destination_view` permission.
     *
     * Example:
     * - `GET /api/v1/domains/{domain_uuid}/phone-numbers/{destination_uuid}`
     *
     * @group Phone Numbers
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 7d58342b-2b29-4dcf-92d6-e9a9e002a4e5
     * @urlParam destination_uuid string required The destination UUID. Example: ad45f53d-405b-48bc-aac2-d51b2b3b3e55
     *
     * @response 200 scenario="Success" {
     *   "destination_uuid": "ad45f53d-405b-48bc-aac2-d51b2b3b3e55",
     *   "object": "phone_number",
     *   "domain_uuid": "7d58342b-2b29-4dcf-92d6-e9a9e002a4e5",
     *   "destination_number": "2137577900",
     *   "destination_prefix": "1",
     *   "destination_enabled": true,
     *   "destination_description": null,
     *   "fax_uuid": null,
     *   "destination_record": true,
     *   "destination_type_fax": null,
     *   "destination_hold_music": null,
     *   "destination_distinctive_ring": null,
     *   "destination_cid_name_prefix": null,
     *   "destination_accountcode": null,
     *   "routing_options": [
     *     {
     *       "type": "extensions",
     *       "extension": "100",
     *       "option": "2304420c-9ec2-469c-a7d0-f671a6176f36",
     *       "name": "100 - Alice Johnson"
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid destination UUID" {"error":{"type":"invalid_request_error","message":"Invalid destination UUID.","code":"invalid_request","param":"destination_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 scenario="Forbidden" {"error":{"type":"permission_error","message":"You do not have permission to access this resource.","code":"forbidden"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="Phone number not found" {"error":{"type":"invalid_request_error","message":"Phone number not found.","code":"resource_missing","param":"destination_uuid"}}
     */
    public function show(Request $request, string $domain_uuid, string $destination_uuid)
    {

        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $destination_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid destination UUID.', 'invalid_request', 'destination_uuid');
        }

        $domainExists = Domain::query()->where('domain_uuid', $domain_uuid)->exists();
        if (! $domainExists) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        $payload = $this->buildPhoneNumberPayload($domain_uuid, $destination_uuid);

        return response()->json($payload->toArray(), 200);
    }

    /**
     * Create phone number
     *
     * Creates a phone number in the specified domain.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `destination_add` permission.
     *
     * Example:
     * - `POST /api/v1/domains/{domain_uuid}/phone-numbers`
     *
     * @group Phone Numbers
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 7d58342b-2b29-4dcf-92d6-e9a9e002a4e5
     *
     * @response 201 scenario="Created" {
     *   "destination_uuid": "ad45f53d-405b-48bc-aac2-d51b2b3b3e55",
     *   "object": "phone_number",
     *   "domain_uuid": "7d58342b-2b29-4dcf-92d6-e9a9e002a4e5",
     *   "destination_number": "2137577900",
     *   "destination_prefix": "1",
     *   "destination_enabled": true,
     *   "destination_description": null,
     *   "fax_uuid": null,
     *   "destination_record": true,
     *   "destination_type_fax": null,
     *   "destination_hold_music": null,
     *   "destination_distinctive_ring": null,
     *   "destination_cid_name_prefix": null,
     *   "destination_accountcode": null,
     *   "routing_options": [
     *     {
     *       "type": "extensions",
     *       "extension": "100",
     *       "option": "2304420c-9ec2-469c-a7d0-f671a6176f36",
     *       "name": "100 - Alice Johnson"
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 scenario="Forbidden" {"error":{"type":"permission_error","message":"You do not have permission to access this resource.","code":"forbidden"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 422 scenario="Validation error" {"errors":{"destination_number":["The destination number has already been taken."]}}
     */
    public function store(StorePhoneNumberRequest $request, string $domain_uuid)
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
            /** @var \App\Models\Destinations $phoneNumber */
            $phoneNumber = DB::transaction(function () use ($validated, $domain_uuid, $domain) {

                $createData = array_merge($validated, [
                    'destination_uuid' => Str::uuid(),
                    'domain_uuid'        => $domain_uuid,
                    'dialplan_uuid' => (string) Str::uuid(),
                    'destination_type' => 'inbound',
                    'destination_context' => 'public',
                    'destination_enabled' =>  $validated['destination_enabled'] ?? true,
                    'destination_record' => $validated['destination_record'] ?? false,
                    'destination_type_fax' => $validated['destination_type_fax'] ?? false,
                ]);

                // Build normalized + derived data (API vs internal compatible)
                $derived = app(PhoneNumberService::class)
                    ->buildUpdateData($createData, $domain_uuid, (string) $domain->domain_name);

                // Create destination row
                $pn = Destinations::create($derived);

                return $pn->fresh();
            });

            // Build dialplan async 
            dispatch(new BuildDialplanForPhoneNumber(
                (string) $phoneNumber->destination_uuid,
                (string) $domain->domain_name
            ));

            $payload = $this->buildPhoneNumberPayload($domain_uuid, $phoneNumber->destination_uuid);

            return response()
                ->json($payload->toArray(), 201)
                ->header('Location', "/api/v1/domains/{$domain_uuid}/phone-numbers/{$phoneNumber->destination_uuid}");
        } catch (\Throwable $e) {
            logger('API PhoneNumber store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }

    /**
     * Update phone number
     *
     * Updates a phone number within the specified domain.
     *
     * Access rules:
     * - Caller must be authenticated.
     * - Caller must have access to the target domain (domain scope via middleware).
     * - Caller must have the `destination_edit` permission.
     *
     * Notes:
     * - This endpoint is PATCH-like: only provided fields are updated.
     *
     * @group Phone Numbers
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 7d58342b-2b29-4dcf-92d6-e9a9e002a4e5
     * @urlParam destination_uuid string required The destination UUID. Example: ad45f53d-405b-48bc-aac2-d51b2b3b3e55
     *
     * @response 200 scenario="Success" {
     *   "destination_uuid": "ad45f53d-405b-48bc-aac2-d51b2b3b3e55",
     *   "object": "phone_number",
     *   "domain_uuid": "7d58342b-2b29-4dcf-92d6-e9a9e002a4e5",
     *   "destination_number": "2137577900",
     *   "destination_prefix": "1",
     *   "destination_enabled": true,
     *   "destination_description": null,
     *   "fax_uuid": null,
     *   "destination_record": true,
     *   "destination_type_fax": null,
     *   "destination_hold_music": null,
     *   "destination_distinctive_ring": null,
     *   "destination_cid_name_prefix": null,
     *   "destination_accountcode": null,
     *   "routing_options": [
     *     {
     *       "type": "extensions",
     *       "extension": "100",
     *       "option": "2304420c-9ec2-469c-a7d0-f671a6176f36",
     *       "name": "100 - Alice Johnson"
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid destination UUID" {"error":{"type":"invalid_request_error","message":"Invalid destination UUID.","code":"invalid_request","param":"destination_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="Phone number not found" {"error":{"type":"invalid_request_error","message":"Phone number not found.","code":"resource_missing","param":"destination_uuid"}}
     * @response 422 scenario="Validation error" {"errors":{"destination_number":["The destination number has already been taken."]}}
     * @response 500 scenario="Internal server error" {"error":{"type":"api_error","message":"Internal server error.","code":"internal_error"}}
     */

    public function update(UpdatePhoneNumberRequest $request, string $domain_uuid, string $destination_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $destination_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid destination UUID.', 'invalid_request', 'destination_uuid');
        }

        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->first(['domain_uuid', 'domain_name']);

        if (! $domain) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        /** @var \App\Models\Destinations|null $phoneNumber */
        $phoneNumber = Destinations::query()
            ->where('domain_uuid', $domain_uuid)
            ->where('destination_uuid', $destination_uuid)
            ->first();

        if (! $phoneNumber) {
            throw new ApiException(404, 'invalid_request_error', 'Phone number not found.', 'resource_missing', 'destination_uuid');
        }

        $validated = $request->validated();

        try {
            DB::transaction(function () use ($validated, $domain_uuid, $domain, $phoneNumber) {
                // Build normalized + derived data (API vs internal compatible, PATCH-safe)
                // IMPORTANT: pass the *validated* payload, not $phoneNumber->toArray(),
                // so we only mutate fields that were provided.
                $updateData = app(PhoneNumberService::class)
                    ->buildUpdateData($validated, $domain_uuid, (string) $domain->domain_name);

                $phoneNumber->update($updateData);
            });

            // Build dialplan async (same behavior as store)
            dispatch(new BuildDialplanForPhoneNumber(
                (string) $phoneNumber->destination_uuid,
                (string) $domain->domain_name
            ));

            // Return SAME payload format as show()
            $payload = $this->buildPhoneNumberPayload($domain_uuid, (string) $phoneNumber->destination_uuid);

            return response()->json($payload->toArray(), 200);
        } catch (\Throwable $e) {
            logger('API PhoneNumber update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }



    /**
     * Builds response payload as a reusable helper.
     */
    private function buildPhoneNumberPayload(string $domain_uuid, string $destination_uuid): PhoneNumberData
    {
        // v_destinations stores some bool-ish fields as text ('true'/'false')
        $textBool = static function ($value): ?bool {
            if ($value === null || $value === '') return null;
            if (is_bool($value)) return $value;
            if (is_numeric($value)) return ((int) $value) === 1;
            $v = strtolower(trim((string) $value));
            if (in_array($v, ['true', 't', '1', 'yes', 'y', 'on'], true)) return true;
            if (in_array($v, ['false', 'f', '0', 'no', 'n', 'off'], true)) return false;
            return null;
        };

        $numericBool = static function ($value): ?bool {
            if ($value === null || $value === '') return null;
            if (is_bool($value)) return $value;
            if (is_numeric($value)) return ((int) $value) === 1;
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        };

        $d = Destinations::query()
            ->where('domain_uuid', $domain_uuid)
            ->where('destination_uuid', $destination_uuid)
            ->select([
                'destination_uuid',
                'domain_uuid',
                'dialplan_uuid',
                'fax_uuid',
                'destination_number',
                'destination_prefix',

                'destination_record',
                'destination_hold_music',
                'destination_distinctive_ring',
                'destination_cid_name_prefix',
                'destination_accountcode',
                'destination_type_fax',

                'destination_enabled',
                'destination_description',
                'destination_actions',
            ])
            ->first();

        if (! $d) {
            throw new ApiException(404, 'invalid_request_error', 'Phone number not found.', 'resource_missing', 'destination_uuid');
        }

        // routing_options is appended by the model (no controller parsing)
        return new PhoneNumberData(
            destination_uuid: (string) $d->destination_uuid,
            object: 'phone_number',
            domain_uuid: (string) $d->domain_uuid,

            destination_number: $d->destination_number !== null ? (string) $d->destination_number : null,
            destination_prefix: $d->destination_prefix !== null ? (string) $d->destination_prefix : null,

            destination_enabled: $textBool($d->destination_enabled),
            destination_description: $d->destination_description,

            fax_uuid: $d->fax_uuid !== null ? (string) $d->fax_uuid : null,

            destination_record: $textBool($d->destination_record),
            destination_type_fax: $numericBool($d->destination_type_fax),

            destination_hold_music: $d->destination_hold_music !== null ? (string) $d->destination_hold_music : null,
            destination_distinctive_ring: $d->destination_distinctive_ring !== null ? (string) $d->destination_distinctive_ring : null,
            destination_cid_name_prefix: $d->destination_cid_name_prefix !== null ? (string) $d->destination_cid_name_prefix : null,
            destination_accountcode: $d->destination_accountcode !== null ? (string) $d->destination_accountcode : null,

            routing_options: $d->routing_options
        );
    }

    /**
     * Delete a phone number
     *
     * Deletes a phone number within the specified domain.
     *
     * @group Phone Numbers
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 7d58342b-2b29-4dcf-92d6-e9a9e002a4e5
     * @urlParam destination_uuid string required The destination UUID. Example: ad45f53d-405b-48bc-aac2-d51b2b3b3e55
     *
     * @response 200 scenario="Success" {"object":"phone_number","uuid":"ad45f53d-405b-48bc-aac2-d51b2b3b3e55","deleted":true}
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid destination UUID" {"error":{"type":"invalid_request_error","message":"Invalid destination UUID.","code":"invalid_request","param":"destination_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="Phone number not found" {"error":{"type":"invalid_request_error","message":"Phone number not found.","code":"resource_missing","param":"destination_uuid"}}
     * @response 500 scenario="Internal server error" {"error":{"type":"api_error","message":"Internal server error.","code":"internal_error"}}
     */
    public function destroy(Request $request, string $domain_uuid, string $destination_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $destination_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid destination UUID.', 'invalid_request', 'destination_uuid');
        }

        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->first(['domain_uuid', 'domain_name']);

        if (! $domain) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        /** @var \App\Models\Destinations|null $pn */
        $pn = Destinations::query()
            ->where('domain_uuid', $domain_uuid)
            ->where('destination_uuid', $destination_uuid)
            ->first(['destination_uuid', 'dialplan_uuid', 'destination_prefix', 'destination_number', 'destination_context']);

        if (! $pn) {
            throw new ApiException(404, 'invalid_request_error', 'Phone number not found.', 'resource_missing', 'destination_uuid');
        }

        try {
            DB::transaction(function () use ($pn) {

                // Delete dialplan (if exists)
                if (! empty($pn->dialplan_uuid)) {
                    Dialplans::where('dialplan_uuid', $pn->dialplan_uuid)->delete();
                }

                // Delete destination
                $pn->delete();

                // Clear dialplan cache (same behavior as internal bulk delete)
                try {
                    $dialplanBuilder = new DialplanBuilderService();
                    $dialplanBuilder->clearCacheForPhoneNumber($pn);
                } catch (\Throwable $e) {
                    // cache clear should not block deletion
                    logger('PhoneNumber destroy cache clear failed: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
                }

            });

            $payload = DeletedResponseData::from([
                'uuid' => (string) $destination_uuid,
                'object' => 'phone_number',
                'deleted' => true,
            ]);

            return response()->json($payload->toArray(), 200);
        } catch (\Throwable $e) {
            logger('API PhoneNumber delete error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }
}
