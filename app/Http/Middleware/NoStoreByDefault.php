<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Owns the browser cache policy for web routes.
 *
 * The legacy PHP session (started by CheckFusionPBXLogin and the login flow) would
 * otherwise add its own no-store headers, which a response cannot override. Turn
 * those off and apply the same no-store default here instead, so pages and
 * downloads stay out of browser caches unless a response opts in with a positive
 * max-age.
 */
class NoStoreByDefault
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_cache_limiter('');
        }

        $response = $next($request);

        if ((int) $response->headers->getCacheControlDirective('max-age') <= 0) {
            $response->headers->addCacheControlDirective('no-store');
            $response->headers->addCacheControlDirective('no-cache');
            $response->headers->addCacheControlDirective('must-revalidate');
        }

        return $response;
    }
}
