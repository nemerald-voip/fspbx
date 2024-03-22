<?php

namespace App\Http\Controllers\Auth;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Jobs\EmailLoginChallengeCode;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Exceptions\HttpResponseException;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

class EmailChallengeController extends Controller
{

    /**
     * Show the email challenge view.
     *
     * @param  \Laravel\Fortify\Http\Requests\TwoFactorLoginRequest  $request
     * @return  view
     */

    public function create(TwoFactorLoginRequest $request)
    {
        if (!$request->hasChallengedUser()) {
            throw new HttpResponseException(redirect()->route('login'));
        }

        logger('create');

        if (!Session::has('code') || now()->greaterThan(Session::get('code_expiration'))) {
            //If the code has not been generated yet or expired generate a new one
            $code = random_int(100000, 999999);

            // Store code and the current timestamp for expiration mechanism
            Session::put('code', $code);
            // Set the code expiration after 10 minutes
            $expirationTimestamp = now()->addMinutes(10);
            Session::put('code_expiration', $expirationTimestamp);
            logger(Session::get('code_expiration'));

            $attributes = [
                'name' => $request->challengedUser()->user_adv_fields->first_name,
                'email' => $request->challengedUser()->user_email,
                'code' => $code,
            ];

            logger($attributes);

            // Send email verification code by email
            EmailLoginChallengeCode::dispatch($attributes)->onQueue('emails');
        }

        $links['email-challenge'] = "/email-challenge";
        return Inertia::render('Auth/TwoFactorEmailChallenge',[
            'links' => function () use ($links) {
                return $links;
            },
            'status' => session('status'),
        ]);

    }



    /**
     * Attempt to authenticate a new session using the email challenge code.
     *
     * @param  \Laravel\Fortify\Http\Requests\TwoFactorLoginRequest  $request
     * @return mixed
     */
    public function store(TwoFactorLoginRequest $request)
    {
        logger(Session::get('code_expiration'));
        
        logger(Session::get('code_expiration'));
        if (now()->greaterThan(Session::get('code_expiration'))) {
            logger('code expired');
            return redirect()->back()->withErrors(['message' => 'The code has expired.']);
        }

        $user = $request->challengedUser();

        logger('store');
        logger($request);
        logger(Session::get('code'));

        Auth::login($user, $request->remember());

        $request->session()->regenerate();

        // if (! $request->hasValidCode()) {
        //     return app(FailedTwoFactorLoginResponse::class)->toResponse($request);
        // }

        // $this->guard->login($user, $request->remember());

        // $request->session()->regenerate();

        // return app(TwoFactorLoginResponse::class);
    }
}
