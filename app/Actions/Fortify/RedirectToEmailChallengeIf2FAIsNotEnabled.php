<?php

namespace App\Actions\Fortify;

use Laravel\Fortify\Fortify;
use Illuminate\Auth\Events\Failed;
use Laravel\Fortify\LoginRateLimiter;
use App\Events\TwoFactorEmailChallenged;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Validation\ValidationException;
use App\Models\Traits\Fortify\EmailChallengable;

class RedirectToEmailChallengeIf2FAIsNotEnabled
{
    /**
     * The guard implementation.
     *
     * @var \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected $guard;

    /**
     * The login rate limiter instance.
     *
     * @var \Laravel\Fortify\LoginRateLimiter
     */
    protected $limiter;

    /**
     * Create a new controller instance.
     *
     * @param  \Illuminate\Contracts\Auth\StatefulGuard  $guard
     * @param  \Laravel\Fortify\LoginRateLimiter  $limiter
     * @return void
     */
    public function __construct(StatefulGuard $guard, LoginRateLimiter $limiter)
    {
        $this->guard = $guard;
        $this->limiter = $limiter;
    }

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  callable  $next
     * @return mixed
     */
    public function handle($request, $next)
    {
        $model = $this->guard->getProvider()->getModel();
        $user = $model::where(Fortify::username(), $request->{Fortify::username()})->first();

        if (
            !optional($user)->two_factor_secret &&
            in_array(EmailChallengable::class, class_uses_recursive($user))
        ) {
            return $this->emailChallengeResponse($request, $user);
        }

        return $next($request);
    }


    /**
     * Get the email challenge enabled response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function emailChallengeResponse($request, $user)
    {
        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $request->boolean('remember'),
        ]);

        TwoFactorEmailChallenged::dispatch($user);

        return $request->wantsJson()
            ? response()->json(['email_challenge' => true])
            : redirect()->route('email-challenge.login');
    }
}
