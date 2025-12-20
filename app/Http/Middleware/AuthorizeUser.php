<?php 
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Auth\PermissionService;

class AuthorizeUser
{
    public function __construct(private PermissionService $authz) {}

public function handle(Request $request, Closure $next, string $permissionName)
{
    $user = $request->user();
    if (! $user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated.',
            'error'   => ['code' => 'unauthenticated'],
        ], 401);
    }

    // If the route is domain-scoped, you'll have a domain_uuid param
    $routeDomainUuid = $request->route('domain_uuid');

    // Use target domain when present; otherwise fall back to user's own domain
    $domainUuid = $routeDomainUuid ?: (string) $user->domain_uuid;

    // ✅ Only check "can access this domain" when a target domain is specified in the route
    if ($routeDomainUuid) {
        if (! $this->authz->userCanAccessDomain($user, (string) $domainUuid)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden (domain access).',
                'error'   => [
                    'code' => 'forbidden_domain',
                    'domain_uuid' => $domainUuid,
                ],
            ], 403);
        }
    }
    
    logger($permissionName);

    // ✅ Always check permission (use $domainUuid context)
    if (! $this->authz->userHasPermission($user, $permissionName, (string) $domainUuid)) {
        return response()->json([
            'success' => false,
            'message' => 'Forbidden (missing permission).',
            'error'   => [
                'code' => 'forbidden_permission',
                'permission' => $permissionName,
            ],
        ], 403);
    }

    return $next($request);
}

}

