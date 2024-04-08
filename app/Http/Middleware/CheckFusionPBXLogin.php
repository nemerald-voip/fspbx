<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class CheckFusionPBXLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            };
            if (!isset($_SESSION['user'])) {
                session_unset();
                session_destroy();
        
                Auth::logout();
                Session::flush();
            }
        }

        return $next($request);
    }
}
