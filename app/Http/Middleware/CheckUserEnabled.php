<?php

namespace App\Http\Middleware;

use App\Support\Localization\LocaleRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckUserEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if(auth()->check() && (auth()->user()->user_enabled == 'false')){
                // This middleware runs before SetApplicationLocale and clears the session.
                $locale = app(LocaleRegistry::class)->resolve(
                    session('domain.language.code') ?? get_domain_setting('language')
                );
                $message = __('Your account has been suspended, please contact your system administrator.', [], $locale);
                Auth::logout();
    
                $request->session()->invalidate();
    
                $request->session()->regenerateToken();
    
                return redirect()->route('login')->with('error', $message);
    
        }
    
        return $next($request);
    }
}
