<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserObserver
{
    public function saved(User $user): void
    {
        // Any time the user record changes, invalidate auth-related caches for that user
        Cache::tags(['auth', "user:{$user->user_uuid}", 'domain_access'])->flush();
        Cache::tags(['auth', "user:{$user->user_uuid}", 'permissions'])->flush(); // if you add permissions caching later
    }

    public function deleted(User $user): void
    {
        Cache::tags(['auth', "user:{$user->user_uuid}", 'domain_access'])->flush();
        Cache::tags(['auth', "user:{$user->user_uuid}", 'permissions'])->flush();
    }
}
