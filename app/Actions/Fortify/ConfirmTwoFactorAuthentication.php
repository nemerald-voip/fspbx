<?php

namespace App\Actions\Fortify;

use App\Services\Auth\AuthenticatorSettings;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication as FortifyAction;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;

class ConfirmTwoFactorAuthentication extends FortifyAction
{
    public function __construct(private AuthenticatorSettings $settings) {}

    public function __invoke($user, $code): void
    {
        $input = validator(['code' => $code], ['code' => ['required', 'string', 'regex:/^[0-9]{6}$/D']])->validate();
        $this->settings->confirm($user, $input['code']);
        TwoFactorAuthenticationConfirmed::dispatch($user);
    }
}
