<?php

namespace App\Http\Middleware;

use Closure;
use Inertia\Inertia;

class RequireTwoFactorEmailAuthentication
{
    public function handle($request, Closure $next)
    {
        // Check if we're post-login and the session indicates email verifications is needed
        // This only triggers if user is already succesfully authenticated but doesn't have 2FA enabled
        if ($request->session()->has('user_id_for_2fa')) {
            return Inertia::render('Auth/TwoFactorEmailChallenge');
        }

        return $next($request);
    }
}