<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\RedirectToEmailChallengeIf2FAIsNotEnabled;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\DefaultSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::authenticateThrough(function () {
            logger('Checking email challenge enabled status...'  . $this->emailChallengeEnabled());
            return [
                config('fortify.limiters.login') ? null : EnsureLoginIsNotThrottled::class,
                config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
                Features::enabled(Features::twoFactorAuthentication()) ? RedirectIfTwoFactorAuthenticatable::class : null,
                $this->emailChallengeEnabled() ? RedirectToEmailChallengeIf2FAIsNotEnabled::class : null,
                AttemptToAuthenticate::class,
                PrepareAuthenticatedSession::class,
            ];
        });


        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        Fortify::loginView(function () {
            $links['password-request'] = route('password.request');
            return Inertia::render('Auth/Login',[
                'links' => function () use ($links) {
                    return $links;
                },
                'status' => session('status'),
            ]);
        });

        Fortify::requestPasswordResetLinkView(function () {
            $links['login'] = route('login');
            $links['password-email'] = route('password.email');
            return Inertia::render('Auth/ForgotPassword',[
                'links' => function () use ($links) {
                    return $links;
                },
                'status' => session('status'),
            ]);
        });
    
        Fortify::resetPasswordView(function (Request $request) {
            $links['login'] = route('login');
            $links['password-update'] = route('password.update');
            return Inertia::render('Auth/ResetPassword', [
                'links' => function () use ($links) {
                    return $links;
                },
                'user_email' => $request->input('email'),
                'token' => $request->route('token'),
            ]);
        });

        Fortify::twoFactorChallengeView(function () {
            return Inertia::render('Auth/TwoFactorChallenge');
        });

        Fortify::confirmPasswordView(function () {
            return Inertia::render('Auth/ConfirmPassword');
        });
    }

    protected function emailChallengeEnabled(): bool
    {
        if (! Features::enabled('email-challenge')) {
            return false;
        }

        try {
            return Cache::remember('settings.email_challenge_enabled', 3600, function () {
                $value = DefaultSettings::where('default_setting_category', 'authentication')
                    ->where('default_setting_subcategory', 'email_challenge')
                    ->where('default_setting_name', 'boolean')
                    ->value('default_setting_value');

                // logger('default_setting_value raw: ' . var_export($value, true));

                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            });
        } catch (\Throwable $e) {
            return false;
        }
    }
}
