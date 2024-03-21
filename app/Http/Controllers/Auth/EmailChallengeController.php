<?php

namespace App\Http\Controllers\Auth;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmailChallengeController extends Controller
{
    public function create (Request $request)
    {
        logger("TwoFactorEmailChallenge");
        if ($request->session()->has('user_uuid')) 
        {
            return Inertia::render('Auth/TwoFactorEmailChallenge');
        }
        return redirect(route('login'));
        
    }
}
