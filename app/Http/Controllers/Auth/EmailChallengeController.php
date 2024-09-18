<?php

namespace App\Http\Controllers\Auth;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Jobs\EmailLoginChallengeCode;
use Illuminate\Support\Facades\Cookie;
use App\Providers\RouteServiceProvider;
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
        logger('Your 2FA code is ' . $code);
        $attributes = [
            'name' => optional($user->user_adv_fields)->first_name ?? '',
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

    /**
     * Process request to create a new email challenge code.
     *
     * @param  TwoFactorLoginRequest  $request
     * @return mixed
     */
    public function update(TwoFactorLoginRequest $request)
    {
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
            'status' => 'Verification code has been resent',
        ]);
    }

    /**
     * Store the cookie if it is not in the database.
     *
     * @param  TwoFactorLoginRequest  $request
     * @return void
     */
    protected function storeCookieIfNotInDB($request)
    {
        $user = $request->challengedUser();
        $userAdvFields = $user->user_adv_fields; // Load the related UserAdvFields model

        // Check if user_adv_fields is null; if so, create a new instance and associate it with the user
        if ($userAdvFields === null) {
            $userAdvFields = new \App\Models\UserAdvFields();
            $userAdvFields->user_uuid = $user->user_uuid;
            $userAdvFields->save();

            // Associate the new UserAdvFields instance with the user
            $user->user_adv_fields()->save($userAdvFields);
        }


        // Decode the existing two_factor_cookies, or initialize an empty array if null
        $two_factor_cookies = json_decode($userAdvFields->two_factor_cookies, true) ?? [];

        $two_factor_cookie = Cookie::get('__TWO_FACTOR_EMAIL');

        if (!in_array($two_factor_cookie, $two_factor_cookies)) {
            $two_factor_cookie = md5($request->header('User-Agent') . ' ' . $request->ip());
            $two_factor_cookies[] = $two_factor_cookie;

            // Limit the array to the last 3 cookies
            if (count($two_factor_cookies) > 3) {
                array_shift($two_factor_cookies);
            }

            // Ensure unique values
            $two_factor_cookies = array_unique($two_factor_cookies);

            // Encode and save the updated cookies back to the UserAdvFields model
            $userAdvFields->two_factor_cookies = json_encode($two_factor_cookies);
            $userAdvFields->save(); // Save the changes to the database

            $lifetime = 60 * 24 * 90; //90 days
            Cookie::queue('__TWO_FACTOR_EMAIL', $two_factor_cookie, $lifetime);
            // Cookie::queue('__TWO_FACTOR_EMAIL',$two_factor_cookie,5); // For testing. 5 minutes lifetime only
        }
    }
}
