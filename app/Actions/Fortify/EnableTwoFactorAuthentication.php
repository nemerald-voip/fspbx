<?php

namespace App\Actions\Fortify;

use App\Services\Auth\AuthenticatorSettings;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication as FortifyAction;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;

class EnableTwoFactorAuthentication extends FortifyAction
{
    public function __construct(private AuthenticatorSettings $settings) {}

    public function __invoke($user, $force = false): void
    {
        $this->settings->enable($user);
        TwoFactorAuthenticationEnabled::dispatch($user);
    }
}
