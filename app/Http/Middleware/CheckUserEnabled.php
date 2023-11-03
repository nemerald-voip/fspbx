<?php

namespace App\Http\Middleware;

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
                Auth::logout();
    
                $request->session()->invalidate();
    
                $request->session()->regenerateToken();
    
                return redirect()->route('login')->with('error', 'Your account has been suspended, please contact your system administrator.');
    
        }
    
        return $next($request);
    }
}
