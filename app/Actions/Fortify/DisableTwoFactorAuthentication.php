<?php

namespace App\Actions\Fortify;

use App\Services\Auth\AuthenticatorSettings;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication as FortifyAction;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

class DisableTwoFactorAuthentication extends FortifyAction
{
    public function __construct(private AuthenticatorSettings $settings) {}

    public function __invoke($user): void
    {
        $this->settings->disable($user);
        TwoFactorAuthenticationDisabled::dispatch($user);
    }
}
