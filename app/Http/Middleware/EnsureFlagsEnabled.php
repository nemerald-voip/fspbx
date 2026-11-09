<?php

namespace App\Http\Middleware;

use App\Services\FlagFeature;
use Closure;
use Illuminate\Http\Request;

class EnsureFlagsEnabled
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(app(FlagFeature::class)->enabled(), 404);

        return $next($request);
    }
}
