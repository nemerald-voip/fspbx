<?php

namespace App\Http\Requests;

use App\Services\Auth\PendingLogin;
use Illuminate\Http\Exceptions\HttpResponseException;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;

class PendingTwoFactorLoginRequest extends TwoFactorLoginRequest
{
    public function challengedUser()
    {
        $user = parent::challengedUser();
        if (! PendingLogin::valid($this, $user)
            || ($this->is('two-factor-challenge') && ! $user->hasEnabledTwoFactorAuthentication())) {
            PendingLogin::clear($this);
            throw new HttpResponseException(redirect()->route('login'));
        }
        return $user;
    }

    public function hasChallengedUser()
    {
        return $this->session()->has('login.id') && $this->challengedUser();
    }
}
