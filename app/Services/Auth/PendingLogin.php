<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PendingLogin
{
    public static function start(Request $request, User $user, string $method): void
    {
        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.method' => $method,
            'login.challenge' => Str::random(40),
            'login.identity' => self::identity($user),
            'login.expires' => now()->addMinutes(10)->timestamp,
        ]);
    }

    public static function valid(Request $request, User $user): bool
    {
        return $user->user_enabled === 'true'
            && $request->session()->get('login.expires', 0) > now()->timestamp
            && self::identity($user) === $request->session()->get('login.identity');
    }

    private static function identity(User $user): string
    {
        return hash('sha256', serialize([$user->getKey(), $user->user_email, $user->password,
            $user->hasEnabledTwoFactorAuthentication() ? $user->two_factor_secret : null]));
    }

    public static function emailKey(Request $request): string
    {
        return 'login.email.'.$request->session()->get('login.challenge');
    }

    public static function clear(Request $request): void
    {
        if ($request->session()->has('login.challenge')) {
            Cache::forget(self::emailKey($request));
        }
        $request->session()->forget(['login', 'code', 'code_expiration']);
    }
}
