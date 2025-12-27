<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Domain;
use Illuminate\Http\Request;
use App\Data\Api\V1\DomainData;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Database\QueryException;
use App\Data\Api\V1\DeletedResponseData;
use App\Services\Auth\PermissionService;
use App\Data\Api\V1\DomainListResponseData;
use App\Http\Requests\Api\V1\StoreDomainRequest;
use App\Http\Requests\Api\V1\UpdateDomainRequest;

class DomainController extends Controller
{
    public function __construct(
        private PermissionService $authz,
    ) {}

    /**
     * List domains
     *
     * Returns only the domains the caller is allowed to access.
     *
     * Access rules:
     * - If the user has the `domain_all` permission, all domains are returned.
     * - If the user has any assigned domain groups or individual domains, only those domains are returned
     *   (even if the user's own domain is not included).
     * - If nothing is assigned, only the user's own domain is returned.
     *
     * Pagination (cursor-based):
     * - Both `limit` and `starting_after` are optional.
     * - If `limit` is not provided, it defaults to 25.
     * - If `starting_after` is not provided, results start from the beginning.
     * - If `has_more` is true, request the next page by passing `starting_after`
     *   equal to the last item's `domain_uuid` from the previous response.
     * 
     * Examples:
     * - First page: `GET /api/v1/domains`
     * - Next page:  `GET /api/v1/domains?starting_after={last_domain_uuid}`
     * - Custom size: `GET /api/v1/domains?limit=50`
     *
     * @group Domains
     * @authenticated
     *
     * @queryParam limit integer Optional. Number of results to return (min 1, max 100). Defaults to 25. Example: 25
     * @queryParam starting_after string Optional. Return results after this domain UUID (cursor). Example: 7d58342b-2b29-4dcf-92d6-e9a9e002a4e5
     *
     * @response 200 scenario="Success" {
     *   "object": "list",
     *   "url": "/api/v1/domains",
     *   "has_more": true,
     *   "data": [
     *     {
     *       "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *       "object": "domain",
     *       "domain_name": "10001.fspbx.com",
     *       "domain_enabled": true,
     *       "domain_description": "BluePeak Solutions"
     *     }
     *   ]
     * }
     *
     * @response 401 scenario="Unauthenticated" {
     *   "error": {
     *     "type": "authentication_error",
     *     "message": "Unauthenticated.",
     *     "code": "unauthenticated"
     *   }
     * }
     */

    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        $allowed = $this->authz->allowedDomainUuids($user); // null => all domains

        $limit = (int) $request->input('limit', 25);
        $limit = max(1, min(100, $limit));

        $startingAfter = (string) $request->input('starting_after', '');

        $query = QueryBuilder::for(Domain::class)
            ->allowedFilters(['domain_name', 'domain_enabled'])
            ->defaultSort('domain_uuid')
            ->reorder('domain_uuid')
            ->limit($limit + 1);

        if ($allowed !== null) {
            $query->whereIn('domain_uuid', $allowed);
        }

        if ($startingAfter !== '') {
            $query->where('domain_uuid', '>', $startingAfter);
        }

        $rows = $query->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $data = $rows->map(fn($d) => new DomainData(
            domain_uuid: (string) $d->domain_uuid,
            object: 'domain',
            domain_name: (string) $d->domain_name,
            domain_enabled: (bool) $d->domain_enabled,
            domain_description: $d->domain_description,
        ))->all();

        $payload = new DomainListResponseData(
            object: 'list',
            url: '/api/v1/domains',
            has_more: $hasMore,
            data: $data,
        );

        return response()->json($payload->toArray(), 200);
    }

    /**
     * Retrieve a domain
     *
     * Returns a single domain the caller is allowed to access.
     *
     * Access rules:
     * - If the user has the `domain_all` permission, any domain may be retrieved.
     * - If the user has assigned domain groups or individual domains, the domain must be in those assignments
     *   (even if the user's own domain is not included).
     * - If nothing is assigned, the user may only retrieve their own domain.
     *
     * Notes:
     * - If the domain does not exist, a `resource_missing` error is returned.
     * - If the domain exists but is not accessible, a `forbidden_domain` error is returned.
     *
     * @group Domains
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     *
     * @response 200 scenario="Success" {
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "object": "domain",
     *   "domain_name": "10001.fspbx.com",
     *   "domain_enabled": true,
     *   "domain_description": "BluePeak Solutions"
     * }
     *
     * @response 401 scenario="Unauthenticated" {
     *   "error": {
     *     "type": "authentication_error",
     *     "message": "Unauthenticated.",
     *     "code": "unauthenticated"
     *   }
     * }
     *
     * @response 403 scenario="Forbidden (domain access)" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "You do not have access to this domain.",
     *     "code": "forbidden_domain",
     *     "param": "domain_uuid"
     *   }
     * }
     *
     * @response 404 scenario="Not found" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Domain not found.",
     *     "code": "resource_missing",
     *     "param": "domain_uuid"
     *   }
     * }
     */
    public function show(Request $request, string $domain_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        // (Optional) Validate UUID format early (nice for consumers + Scribe)
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(
                400,
                'invalid_request_error',
                'Invalid domain UUID.',
                'invalid_request',
                'domain_uuid'
            );
        }

        $allowed = $this->authz->allowedDomainUuids($user); // null => all domains

        // If user is not "all domains", enforce membership before hitting DB (cheap and clear)
        if ($allowed !== null && ! $allowed->contains($domain_uuid)) {
            throw new ApiException(
                403,
                'invalid_request_error',
                'You do not have access to this domain.',
                'forbidden_domain',
                'domain_uuid'
            );
        }

        $domain = QueryBuilder::for(Domain::class)
            ->where('domain_uuid', $domain_uuid)
            ->first();

        if (! $domain) {
            throw new ApiException(
                404,
                'invalid_request_error',
                'Domain not found.',
                'resource_missing',
                'domain_uuid'
            );
        }

        $payload = new DomainData(
            domain_uuid: (string) $domain->domain_uuid,
            object: 'domain',
            domain_name: (string) $domain->domain_name,
            domain_enabled: (bool) $domain->domain_enabled,
            domain_description: $domain->domain_description,
        );

        return response()->json($payload->toArray(), 200);
    }


    /**
     * Create a domain
     *
     * Creates a new domain.
     *
     * Notes:
     * - `domain_enabled` is optional and defaults to `true`.
     * - `domain_name` is normalized to lowercase and trimmed.
     *
     * @group Domains
     * @authenticated
     *
     * @response 201 scenario="Created" {
     *   "domain_uuid": "9b6a4aa2-2b4f-4c5a-b4bb-1f6b2a9b9b01",
     *   "object": "domain",
     *   "domain_name": "10005.fspbx.com",
     *   "domain_enabled": true,
     *   "domain_description": "BluePeak Solutions"
     * }
     *
     * @response 400 scenario="Domain name already exists" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Domain name already exists.",
     *     "code": "domain_name_exists",
     *     "param": "domain_name"
     *   }
     * }
     *
     * @response 401 scenario="Unauthenticated" {
     *   "error": {
     *     "type": "authentication_error",
     *     "message": "Unauthenticated.",
     *     "code": "unauthenticated"
     *   }
     * }
     */
    public function store(StoreDomainRequest $request)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        $validated = $request->validated();

        try {
            $domain = DB::transaction(function () use ($validated) {
                $domain = new Domain();
                $domain->fill($validated);
                $domain->save();

                return $domain->fresh();
            });

            $payload = new DomainData(
                domain_uuid: (string) $domain->domain_uuid,
                object: 'domain',
                domain_name: (string) $domain->domain_name,
                domain_enabled: (bool) $domain->domain_enabled,
                domain_description: $domain->domain_description,
            );

            return response()
                ->json($payload->toArray(), 201)
                ->header('Location', "/api/v1/domains/{$domain->domain_uuid}");
        } catch (QueryException $e) {
            // Postgres unique violation = 23505
            if (($e->errorInfo[0] ?? null) === '23505') {
                throw new ApiException(
                    400,
                    'invalid_request_error',
                    'Domain name already exists.',
                    'domain_name_exists',
                    'domain_name'
                );
            }

            logger('API Domain store QueryException: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        } catch (\Throwable $e) {
            logger('API Domain store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }

    /**
     * Update a domain
     *
     * Updates an existing domain. Returns the updated domain object.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `domain_edit` permission.
     *
     * @group Domains
     * @authenticated
     * 
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     *
     *
     *
     * @response 200 scenario="Success" {
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "object": "domain",
     *   "domain_name": "10001.fspbx.com",
     *   "domain_enabled": true,
     *   "domain_description": "BluePeak Solutions"
     * }
     *
     * @response 400 scenario="Validation error" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Please enter a domain label.",
     *     "code": "invalid_parameter",
     *     "param": "domain_description",
     *     "doc_url": "https://www.fspbx.com/docs/api/v1/errors/"
     *   }
     * }
     *
     * @response 401 scenario="Unauthenticated" {
     *   "error": {
     *     "type": "authentication_error",
     *     "message": "Unauthenticated.",
     *     "code": "unauthenticated"
     *   }
     * }
     *
     * @response 403 scenario="Forbidden" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Forbidden.",
     *     "code": "forbidden"
     *   }
     * }
     *
     * @response 404 scenario="Not found" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Domain not found.",
     *     "code": "resource_missing",
     *     "param": "domain_uuid"
     *   }
     * }
     */
    public function update(UpdateDomainRequest $request, string $domain_uuid)
    {

        $domain = Domain::where('domain_uuid', $domain_uuid)->firstOrFail();

        $inputs = $request->validated();

        try {
            DB::transaction(function () use ($domain, $inputs) {
                $domain->update($inputs);
                $domain->save();
            });
        } catch (\Throwable $e) {
            logger()->error('API domain update failed', ['exception' => $e]);

            throw new ApiException(
                500,
                'api_error',
                'An unexpected error occurred.',
            );
        }

        // return the updated resource object
        $data = new DomainData(
            domain_uuid: (string) $domain->domain_uuid,
            object: 'domain',
            domain_name: (string) $domain->domain_name,
            domain_enabled: (bool) $domain->domain_enabled,
            domain_description: $domain->domain_description,
        );

        return response()->json($data->toArray(), 200);
    }

    /**
     * Delete a domain
     *
     * Permanently deletes the specified domain.
     *
     * @group Domains
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     *
     * @response 200 scenario="Deleted" {
     *   "id": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "object": "domain",
     *   "deleted": true
     * }
     *
     * @response 401 scenario="Unauthenticated" {
     *   "error": {
     *     "type": "authentication_error",
     *     "message": "Unauthenticated.",
     *     "code": "unauthenticated"
     *   }
     * }
     *
     * @response 404 scenario="Not found" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Domain not found.",
     *     "code": "resource_missing",
     *     "param": "domain_uuid"
     *   }
     * }
     */

    public function destroy(Request $request, string $domain_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        // Let your Handler convert ModelNotFoundException -> Stripe-shaped 404
        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->firstOrFail();

        DB::transaction(function () use ($domain) {
            $domain->delete();
        });

        $payload = DeletedResponseData::from([
            'uuid'      => (string) $domain_uuid, 
            'object'  => 'domain',
            'deleted' => true,
        ]);

        return response()->json($payload->toArray(), 200);
    }
}
