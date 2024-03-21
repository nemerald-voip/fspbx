<?php

namespace App\Http\Middleware;

use Closure;

class RequireTwoFactorEmailAuthentication
{
    public function handle($request, Closure $next)
    {
        // Check if we're post-login and the session indicates email verifications is needed
        // This only triggers if user is already succesfully authenticated but doesn't have 2FA enabled
        if ($request->session()->has('email_challenge') && $request->session()->get('email_challenge') == 'required') 
        {
            session(['email_challenge' => 'requested']);
            logger(route('login.email.challenge'));
            return redirect(route('login.email.challenge'));
        }

        return $next($request);
    }
}