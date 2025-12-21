<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Domain;
use Illuminate\Http\Request;
use App\Data\Api\V1\DomainData;
use App\Exceptions\ApiException;
use App\Http\Responses\ApiResponse;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use App\Services\Auth\PermissionService;
use App\Data\Api\V1\DomainListResponseData;

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
     * GET /api/v1/domains/{domain_uuid}
     * Middleware should already enforce:
     *  - domain access (if route has {domain_uuid})
     *  - domains_view permission
     */
    public function show(Request $request, string $domain_uuid)
    {
        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->first();

        if (! $domain) {
            return ApiResponse::error('Not found.', 'not_found', ['resource' => 'domain'], 404);
        }

        return ApiResponse::ok($domain, 'OK');
    }

    /**
     * POST /api/v1/domains
     * Middleware should enforce domains_create permission (in user home-domain context).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'domain_name' => ['required', 'string', 'max:255'],
            'domain_enabled' => ['nullable', 'string'], // Fusion-style "true"/"false" as text
            // add other v_domains fields you want to allow externally
        ]);

        // NOTE: your Domain model likely uses uuid PK generation (TraitUuid).
        // If not, set domain_uuid here.

        $domain = new Domain();
        $domain->fill($data);

        // If you require defaults:
        if (! isset($data['domain_enabled'])) {
            $domain->domain_enabled = 'true';
        }

        $domain->save();

        return ApiResponse::ok($domain, 'Domain created.', [], 201);
    }

    /**
     * PUT /api/v1/domains/{domain_uuid}
     * Middleware should enforce:
     *  - domain access
     *  - domains_update permission
     */
    public function update(Request $request, string $domain_uuid)
    {
        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->first();

        if (! $domain) {
            return ApiResponse::error('Not found.', 'not_found', ['resource' => 'domain'], 404);
        }

        $data = $request->validate([
            'domain_name' => ['sometimes', 'string', 'max:255'],
            'domain_enabled' => ['sometimes', 'string'],
        ]);

        $domain->fill($data);
        $domain->save();

        return ApiResponse::ok($domain, 'Domain updated.');
    }

    /**
     * DELETE /api/v1/domains/{domain_uuid}
     * Middleware should enforce:
     *  - domain access
     *  - domains_delete permission
     */
    public function destroy(Request $request, string $domain_uuid)
    {
        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->first();

        if (! $domain) {
            return ApiResponse::error('Not found.', 'not_found', ['resource' => 'domain'], 404);
        }

        $domain->delete();

        return ApiResponse::ok(null, 'Domain deleted.');
    }
}
