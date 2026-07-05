<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserSessionInvalidationService
{
    public function invalidateByUserUuids(iterable $userUuids): void
    {
        $userUuids = collect($userUuids)
            ->filter(fn ($uuid) => is_string($uuid) && $uuid !== '')
            ->unique()
            ->values();

        if ($userUuids->isEmpty()) {
            return;
        }

        foreach ($userUuids as $userUuid) {
            Cache::tags(['auth', "user:{$userUuid}", 'permissions'])->flush();
            Cache::tags(['auth', "user:{$userUuid}", 'domain_access'])->flush();
        }

        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            DB::table('sessions')
                ->whereIn('user_id', $userUuids)
                ->delete();
        }

        $currentUserUuid = optional(Auth::user())->user_uuid;

        if ($currentUserUuid && $userUuids->contains($currentUserUuid)) {
            Auth::logout();

            if (request()->hasSession()) {
                request()->session()->invalidate();
                request()->session()->regenerateToken();
            }
        }
    }
}
