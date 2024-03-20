<?php

namespace App\Providers;

use App\Models\User;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Hash;
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

        // Implement custom authentication function
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('user_email', $request->user_email)->first();
        
            if ($user && Hash::check($request->password, $user->password)) {
                if (!$user->two_factor_secret) {
                    // Assuming you have sent the code here or earlier in the process
                    // Redirect to verification page could be indicated by setting a session variable
                    session(['user_id_for_2fa' => $user->user_uuid]);
                }
                return $user; 
            }
            return null;
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
            logger("challange");
            return Inertia::render('Auth/TwoFactorChallenge');
        });

        Fortify::confirmPasswordView(function () {
            return Inertia::render('Auth/ConfirmPassword');
        });
    }
}
