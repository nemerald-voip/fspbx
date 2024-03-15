<?php

namespace App\Providers;

use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use App\Actions\Fortify\CreateNewUser;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use Illuminate\Support\Facades\RateLimiter;
use App\Actions\Fortify\UpdateUserProfileInformation;

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
