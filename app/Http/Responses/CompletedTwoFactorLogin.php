<?php

namespace App\Http\Responses;

use App\Services\Auth\PendingLogin;
use Inertia\Inertia;
use Laravel\Fortify\Http\Responses\TwoFactorLoginResponse;

class CompletedTwoFactorLogin extends TwoFactorLoginResponse
{
    public function toResponse($request)
    {
        PendingLogin::clear($request);
        if ($request->header('X-Inertia') && $request->session()->has('url.intended')) {
            return Inertia::location($request->session()->pull('url.intended'));
        }
        return parent::toResponse($request);
    }
}
