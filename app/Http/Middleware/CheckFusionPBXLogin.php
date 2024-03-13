<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        logger('CheckFusionPBXLogin');
        session_start();
        if (!isset($_SESSION['user'])) {
            return redirect()->route('logout');
        }

        return $next($request);
    }
}
