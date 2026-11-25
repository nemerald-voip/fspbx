<?php

namespace App\Actions\Fortify;

use App\Events\TwoFactorEmailChallenged;
use App\Models\DefaultSettings;
use App\Services\Auth\PendingLogin;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Features;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;

class AuthenticateWebUser extends RedirectIfTwoFactorAuthenticatable
{
    public function handle($request, $next)
    {
        PendingLogin::clear($request);
        $user = $this->validateCredentials($request);

        if ($user->hasEnabledTwoFactorAuthentication()) {
            PendingLogin::start($request, $user, 'authenticator');
            TwoFactorAuthenticationChallenged::dispatch($user);
            return $request->wantsJson()
                ? response()->json(['two_factor' => true])
                : redirect()->route('two-factor.login');
        }

        if ($this->emailChallengeEnabled() && ! $this->remembersDevice($request, $user)) {
            PendingLogin::start($request, $user, 'email');
            TwoFactorEmailChallenged::dispatch($user);
            return $request->wantsJson()
                ? response()->json(['email_challenge' => true])
                : redirect()->route('email-challenge.login');
        }

        $this->guard->login($user, $request->boolean('remember'));
        return $next($request);
    }

    private function emailChallengeEnabled(): bool
    {
        return Features::enabled('email-challenge') && Cache::remember('settings.email_challenge_enabled', 3600, function () {
            $value = DefaultSettings::where('default_setting_category', 'authentication')
                ->where('default_setting_subcategory', 'email_challenge')
                ->where('default_setting_name', 'boolean')
                ->where('default_setting_enabled', true)
                ->value('default_setting_value');

            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        });
    }

    private function remembersDevice($request, $user): bool
    {
        $cookie = $request->cookie('__TWO_FACTOR_EMAIL');
        return Features::enabled('remember-cookie') && is_string($cookie)
            && in_array($cookie, json_decode($user->two_factor_cookies ?? '[]', true) ?: [], true);
    }
}
