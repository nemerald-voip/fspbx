<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\AuthenticateWebUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use App\Services\LdapUserAuthenticator;
use App\Support\Localization\LocaleRegistry;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use App\Http\Requests\PendingTwoFactorLoginRequest;
use App\Http\Responses\CompletedTwoFactorLogin;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Actions\Fortify as AccountActions;
use Laravel\Fortify\Actions as FortifyActions;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TwoFactorLoginRequest::class, PendingTwoFactorLoginRequest::class);
        $this->app->bind(TwoFactorLoginResponse::class, CompletedTwoFactorLogin::class);
        $this->app->bind(FortifyActions\EnableTwoFactorAuthentication::class, AccountActions\EnableTwoFactorAuthentication::class);
        $this->app->bind(FortifyActions\ConfirmTwoFactorAuthentication::class, AccountActions\ConfirmTwoFactorAuthentication::class);
        $this->app->bind(FortifyActions\DisableTwoFactorAuthentication::class, AccountActions\DisableTwoFactorAuthentication::class);
        $this->app->bind(FortifyActions\GenerateNewRecoveryCodes::class, AccountActions\GenerateNewRecoveryCodes::class);
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

        // Use editable email templates while retaining Laravel's reset-token flow.
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $domainUuid = $notifiable->domain_uuid;
            $email = $notifiable->getEmailForPasswordReset();
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $email,
            ], false));

            return (new ResetPasswordMail([
                'domain_uuid' => $domainUuid,
                'language' => app(LocaleRegistry::class)->resolve(
                    $domainUuid ? get_domain_setting('language', $domainUuid) : null
                ),
                'email' => $email,
                'name' => $notifiable->name_formatted ?? null,
                'url' => $url,
                'expire_minutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            ]))->to($email);
        });

        Fortify::authenticateUsing(function (Request $request) {
            return app(LdapUserAuthenticator::class)->authenticate(
                (string) $request->input(Fortify::username()),
                (string) $request->input('password')
            );
        });

        Fortify::confirmPasswordsUsing(fn (User $user, ?string $password) =>
            app(LdapUserAuthenticator::class)->confirmPassword($user, (string) $password)
        );

        Fortify::authenticateThrough(fn () => array_filter([
            config('fortify.limiters.login') ? null : EnsureLoginIsNotThrottled::class,
            config('fortify.lowercase_usernames') ? CanonicalizeUsername::class : null,
            AuthenticateWebUser::class,
            PrepareAuthenticatedSession::class,
        ]));

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

}
