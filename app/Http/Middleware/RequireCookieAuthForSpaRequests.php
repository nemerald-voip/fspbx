<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireCookieAuthForSpaRequests
{
    public function handle(Request $request, Closure $next)
    {
        // If they sent an Authorization bearer token, this is NOT a SPA cookie request.
        if ($request->bearerToken()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        return $next($request);
    }
}
