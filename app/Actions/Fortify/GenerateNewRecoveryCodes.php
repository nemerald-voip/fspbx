<?php

namespace App\Actions\Fortify;

use App\Services\Auth\AuthenticatorSettings;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes as FortifyAction;
use Laravel\Fortify\Events\RecoveryCodesGenerated;

class GenerateNewRecoveryCodes extends FortifyAction
{
    public function __construct(private AuthenticatorSettings $settings) {}

    public function __invoke($user): void
    {
        $this->settings->regenerate($user);
        RecoveryCodesGenerated::dispatch($user);
    }
}
