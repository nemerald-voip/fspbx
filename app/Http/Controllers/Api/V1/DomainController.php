<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Domain;
use Illuminate\Http\Request;
use App\Http\Responses\ApiResponse;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use App\Services\Auth\PermissionService;

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
     * @group Domains
     * @authenticated
     * @queryParam per_page integer Results per page (min 1, max 200). Example: 50
     * @queryParam page integer Page number. Example: 1
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $allowedDomainUuids = $this->authz->allowedDomainUuids($user); // null => all domains

        $query = QueryBuilder::for(Domain::class)
            // add allowed filters/sorts as you like
            ->allowedFilters([
                'domain_name',
                'domain_enabled',
            ])
            ->allowedSorts([
                'domain_name',
                'insert_date',
                'update_date',
            ]);

        if ($allowedDomainUuids !== null) {
            $query->whereIn('domain_uuid', $allowedDomainUuids);
        }

        $perPage = (int)($request->input('per_page', 50));
        $perPage = max(1, min(200, $perPage));

        return ApiResponse::ok(
            $query->paginate($perPage),
            'OK'
        );
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
