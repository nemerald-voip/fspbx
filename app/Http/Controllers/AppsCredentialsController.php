<?php

namespace App\Http\Controllers;

use App\Providers\RouteServiceProvider;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AppsCredentialsController extends Controller
{
    /**
     * Show the credentials view.
     *
     * @param  Request  $request
     * @return \Inertia\Response
     */
    public function getPasswordByToken(Request $request)
    {

        var_dump($request->token);

        print '======';

        var_dump(route('appsGetPasswordByToken', Str::random(40)));
        //var_dump($request);
        die;

        if (!$request->hasChallengedUser()) {
            throw new HttpResponseException(redirect()->route('login'));
        }

        if (!Session::has('code')) {
            $this->generateAndStoreCode($request->challengedUser());
        }

        return Inertia::render('Auth/TwoFactorEmailChallenge', [
            'links' => [
                'email-challenge' => "/email-challenge",
            ],
            'status' => session('status'),
        ]);
    }

    /**
     * Attempt to authenticate a new session using the email challenge code.
     *
     * @param  TwoFactorLoginRequest  $request
     * @return mixed
     */
    public function store(TwoFactorLoginRequest $request)
    {
        $request->validate([
            'code' => [
                'required',
                'max:6',
                function ($attribute, $value, $fail) {
                    if ((string) $value !== (string) session('code')) {
                        $fail('Supplied authentication code is invalid.');
                    }
                    if (now()->greaterThan(session('code_expiration'))) {
                        $fail('The code has expired.');
                    }
                },
            ],
            'remember' => [
                'nullable',
            ]
        ]);

        Auth::login($request->challengedUser());
        $request->session()->regenerate();

        // If request has remember option then store browser details
        if ($request->get('remember')) {
            $this->storeCookieIfNotInDB($request);
        }

        // return app(TwoFactorLoginResponse::class);
        if ($request->session()->has('url.intended')) {
            return Inertia::location(session('url.intended'));
        }

        return redirect()->intended(RouteServiceProvider::HOME);
    }
}
