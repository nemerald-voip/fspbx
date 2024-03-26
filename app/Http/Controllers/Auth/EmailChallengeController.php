<?php

namespace App\Http\Controllers\Auth;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Jobs\EmailLoginChallengeCode;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Exceptions\HttpResponseException;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

class EmailChallengeController extends Controller
{
    /**
     * Generate and store a new email challenge code.
     *
     * @param  mixed  $user
     */
    protected function generateAndStoreCode($user)
    {
        $code = random_int(100000, 999999);
        Session::put('code', $code);
        Session::put('code_expiration', now()->addMinutes(10));

        $attributes = [
            'name' => $user->user_adv_fields->first_name,
            'email' => $user->user_email,
            'code' => $code,
        ];

        EmailLoginChallengeCode::dispatch($attributes)->onQueue('emails');
    }

    /**
     * Show the email challenge view.
     *
     * @param  TwoFactorLoginRequest  $request
     * @return \Inertia\Response
     */
    public function create(TwoFactorLoginRequest $request)
    {
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
                        logger($value);
                        logger(session('code'));
                        $fail('Supplied authentication code is invalid.');
                    }
                    if (now()->greaterThan(session('code_expiration'))) {
                        $fail('The code has expired.');
                    }
                },
            ],
        ]);

        Auth::login($request->challengedUser(), $request->remember());
        $request->session()->regenerate();

        return app(TwoFactorLoginResponse::class);
    }

    /**
     * Process request to create a new email challenge code.
     *
     * @param  TwoFactorLoginRequest  $request
     * @return mixed
     */
    public function update(TwoFactorLoginRequest $request)
    {
        logger('update');
        if ($request->hasChallengedUser()) {
            // erase previous values
            Session::forget('code');
            Session::forget('code_expiration');

            // Generate new code
            $this->generateAndStoreCode($request->challengedUser());
        }

        return Inertia::render('Auth/TwoFactorEmailChallenge', [
            'links' => [
                'email-challenge' => "/email-challenge",
            ],
            'status' => session('status'),
        ]);
    }
}
