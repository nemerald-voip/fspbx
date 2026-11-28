<?php

namespace App\Services;

use App\Models\LdapDirectoryUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class LdapUserAuthenticator
{
    public function authenticate(string $email, string $password): ?User
    {
        $user = User::query()->whereRaw('LOWER(user_email) = ?', [strtolower(trim($email))])->first();

        return $user ? $this->verify($user, $password) : null;
    }

    public function confirmPassword(User $user, string $password): bool
    {
        $key = 'account-password:'.$user->getKey();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            abort(429, __('Too Many Requests'));
        }
        if ($this->verify($user, $password)) {
            RateLimiter::clear($key);
            return true;
        }
        RateLimiter::hit($key, 60);
        return false;
    }

    private function verify(User $user, string $password): ?User
    {
        if ($password === '' || $user->user_enabled !== 'true') {
            return null;
        }

        if (! Schema::hasTable('ldap_directory_users')) {
            return Hash::check($password, $user->password) ? $user : null;
        }

        $links = LdapDirectoryUser::query()
            ->where('user_uuid', $user->user_uuid)
            ->whereNotNull('distinguished_name')
            ->whereHas('directory', fn ($query) => $query->where('enabled', true))
            ->with('directory.domain')
            ->get()
            ->sortBy(fn ($link) => $link->directory->priority);

        if ($links->isEmpty()) {
            return Hash::check($password, $user->password) ? $user : null;
        }

        if ($password === '' || ! $user->user_groups()->exists()) {
            return null;
        }

        foreach ($links as $link) {
            $directory = $link->directory;
            if (! $link->remote_enabled || ! $directory?->domain || ! filter_var($directory->domain->domain_enabled, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }
            if ((new ActiveDirectoryClient($directory))->authenticate($link->distinguished_name, $password)) {
                return $user;
            }
        }

        return null;
    }
}
