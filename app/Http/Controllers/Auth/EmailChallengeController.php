<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\EmailLoginChallengeCode;
use App\Models\UserAdvFields;
use App\Services\Auth\PendingLogin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

class EmailChallengeController extends Controller
{
    public function create(TwoFactorLoginRequest $request)
    {
        if (! $request->hasChallengedUser()) {
            return redirect()->route('login');
        }
        if (! $request->session()->has('login.email_sent')) {
            if ($request->session()->get('login.method') === 'authenticator') {
                return redirect()->route('two-factor.login');
            }
            $this->sendCode($request);
        }
        return $this->view($request);
    }

    public function update(TwoFactorLoginRequest $request)
    {
        if (! $request->hasChallengedUser()) {
            return redirect()->route('login');
        }
        $resent = $request->session()->has('login.email_sent');
        $this->sendCode($request);
        $response = redirect()->route('email-challenge.login');
        return $resent ? $response->with('status', __('Verification code has been resent')) : $response;
    }

    private function sendCode(TwoFactorLoginRequest $request): void
    {
        $user = $request->challengedUser();
        $key = PendingLogin::emailKey($request);
        Cache::lock($key.'.lock', 10)->block(3, function () use ($key, $user, $request) {
            $previous = Cache::get($key);
            do {
                $code = (string) random_int(100000, 999999);
                $digest = hash_hmac('sha256', $code, config('app.key'));
            } while ($digest === $previous);
            $expires = now()->addMinutes(10);
            EmailLoginChallengeCode::dispatch([
                'domain_uuid' => $user->domain_uuid,
                'name' => $user->first_name,
                'email' => $user->user_email,
                'code' => $code,
            ])->onQueue('emails');
            Cache::put($key, $digest, $expires);
            $request->session()->put('login.email_sent', true);
        });
    }

    public function store(TwoFactorLoginRequest $request)
    {
        $user = $request->challengedUser();
        $request->validate(['code' => ['required', 'string', 'regex:/^[0-9]{6}$/D']]);
        $key = PendingLogin::emailKey($request);
        Cache::lock($key.'.lock', 10)->block(3, function () use ($key, $request) {
            $digest = Cache::get($key);
            if (! $digest) {
                throw ValidationException::withMessages(['code' => __('The code has expired.')]);
            }
            if (! hash_equals($digest, hash_hmac('sha256', $request->input('code'), config('app.key')))) {
                throw ValidationException::withMessages(['code' => __('Supplied authentication code is invalid.')]);
            }
            Cache::forget($key);
        });

        if ($request->boolean('remember') && $this->canRemember($request)) {
            $token = Str::random(64);
            $user->updateAdvancedFields(function (UserAdvFields $fields) use ($token) {
                $tokens = json_decode($fields->two_factor_cookies ?? '[]', true) ?: [];
                $tokens[] = $token;
                $fields->two_factor_cookies = json_encode(array_slice($tokens, -3));
            });
            Cookie::queue('__TWO_FACTOR_EMAIL', $token, 7 * 24 * 60);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        return app(TwoFactorLoginResponse::class);
    }

    private function canRemember(TwoFactorLoginRequest $request): bool
    {
        return $request->session()->get('login.method') === 'email' && Features::enabled('remember-cookie');
    }

    private function view(TwoFactorLoginRequest $request)
    {
        return Inertia::render('Auth/TwoFactorChallenge', [
            'method' => 'email',
            'authenticatorEnabled' => $request->challengedUser()->hasEnabledTwoFactorAuthentication(),
            'canRemember' => $this->canRemember($request),
            'status' => session('status'),
        ]);
    }
}
