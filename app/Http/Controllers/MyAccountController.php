<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyAccountController extends Controller
{
    public function __invoke(Request $request, UserService $users): Response
    {
        $user = $request->user();
        $directory = $users->directoryManagement($user);

        return Inertia::render('MyAccount', [
            'profile' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'user_email' => $user->user_email,
                'directoryManaged' => $directory['managed'],
                'emailManaged' => $directory['email_managed'],
            ],
            'authenticator' => fn () => $this->authenticator($request),
            'initial_section' => $request->string('section')->toString(),
        ]);
    }

    private function authenticator(Request $request): array
    {
        $user = $request->user();
        $enabled = $user->hasEnabledTwoFactorAuthentication();
        $confirmedAt = $enabled ? $user->two_factor_confirmed_at : null;

        return [
            'issuer' => config('app.name'),
            'state' => $enabled ? 'enabled' : ($user->two_factor_secret ? 'pending' : 'off'),
            'confirmed_at' => $confirmedAt
                ? Carbon::parse($confirmedAt)->setTimezone(get_local_time_zone())
                    ->locale(app()->getLocale())->translatedFormat(local_date_format())
                : null,
        ];
    }
}
