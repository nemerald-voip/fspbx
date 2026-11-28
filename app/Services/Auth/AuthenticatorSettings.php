<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserAdvFields;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\RecoveryCode;

class AuthenticatorSettings
{
    public function __construct(private TwoFactorAuthenticationProvider $provider) {}

    public function enable(User $user): void
    {
        $user->updateAdvancedFields(function (UserAdvFields $fields) {
            if (! $fields->two_factor_secret) {
                $fields->two_factor_secret = encrypt($this->provider->generateSecretKey());
                $fields->two_factor_recovery_codes = $this->newRecoveryCodes();
                $fields->two_factor_confirmed_at = null;
            }
        });
    }

    public function confirm(User $user, string $code): void
    {
        $user->updateAdvancedFields(function (UserAdvFields $fields) use ($code) {
            if (! $fields->two_factor_secret || ! $this->provider->verify(decrypt($fields->two_factor_secret), $code)) {
                throw ValidationException::withMessages(['code' => __('The provided two factor authentication code was invalid.')]);
            }
            $fields->two_factor_confirmed_at ??= now();
            $fields->two_factor_cookies = null;
        });
    }

    public function disable(User $user): void
    {
        $user->updateAdvancedFields(function (UserAdvFields $fields) {
            $fields->two_factor_secret = null;
            $fields->two_factor_confirmed_at = null;
            $fields->two_factor_recovery_codes = null;
        });
    }

    public function regenerate(User $user): void
    {
        $user->updateAdvancedFields(function (UserAdvFields $fields) {
            abort_unless($fields->two_factor_confirmed_at && $fields->two_factor_secret, 409);
            $fields->two_factor_recovery_codes = $this->newRecoveryCodes();
        });
    }

    public function replaceRecoveryCode(User $user, string $code): void
    {
        $user->updateAdvancedFields(function (UserAdvFields $fields) use ($code) {
            $codes = $fields->two_factor_recovery_codes ? json_decode(decrypt($fields->two_factor_recovery_codes), true) : [];
            $index = array_search($code, $codes, true);
            if (! $fields->two_factor_confirmed_at || $index === false) {
                throw ValidationException::withMessages(['recovery_code' => __('The provided two factor recovery code was invalid.')]);
            }
            $codes[$index] = RecoveryCode::generate();
            $fields->two_factor_recovery_codes = encrypt(json_encode($codes));
        });
    }

    private function newRecoveryCodes(): string
    {
        return encrypt(Collection::times(8, fn () => RecoveryCode::generate())->toJson());
    }

}
