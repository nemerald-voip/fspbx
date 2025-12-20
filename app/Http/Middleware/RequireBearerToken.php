<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireBearerToken
{
    public function handle(Request $request, Closure $next)
    {
        // External calls must send Authorization: Bearer <token>
        if (! $request->bearerToken()) {
            return response()->json([
                'success' => false,
                'message' => 'Bearer token required.',
            ], 401);
        }

        return $next($request);
    }
}
