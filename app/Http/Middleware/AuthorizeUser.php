<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Services\Auth\PermissionService;
use Closure;
use Illuminate\Http\Request;

class AuthorizeUser
{
    public function __construct(private PermissionService $authz) {}

    public function handle(Request $request, Closure $next, string $permissionName)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(
                401,
                'authentication_error',
                'Unauthenticated.',
                'unauthenticated'
            );
        }

        // Domain scope
        $routeDomainUuid = (string) ($request->route('domain_uuid') ?? '');
        $domainUuid = $routeDomainUuid !== '' ? $routeDomainUuid : (string) $user->domain_uuid;

        if ($routeDomainUuid) {
            if (! $this->authz->userCanAccessDomain($user, $domainUuid)) {
                throw new ApiException(
                    403,
                    'invalid_request_error',
                    'You do not have access to this domain.',
                    'forbidden_domain',
                    'domain_uuid'
                );
            }
        }


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
