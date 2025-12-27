<?php

namespace App\Observers;

use App\Models\UserDomainGroupPermissions;
use Illuminate\Support\Facades\Cache;

class UserDomainGroupPermissionsObserver
{
    protected function flushForUser(?string $userUuid): void
    {
        if (! $userUuid) return;

        Cache::tags(['auth', "user:{$userUuid}", 'domain_access'])->flush();
    }

    public function created(UserDomainGroupPermissions $model): void
    {
        $this->flushForUser((string) $model->user_uuid);
    }

    public function updated(UserDomainGroupPermissions $model): void
    {
        // If user_uuid could ever change, flush both old + new
        $this->flushForUser((string) $model->getOriginal('user_uuid'));
        $this->flushForUser((string) $model->user_uuid);
    }

    public function deleted(UserDomainGroupPermissions $model): void
    {
        // Original still available on deleted() for most cases
        $this->flushForUser((string) ($model->user_uuid ?? $model->getOriginal('user_uuid')));
    }

    public function restored(UserDomainGroupPermissions $model): void
    {
        $this->flushForUser((string) $model->user_uuid);
    }
}
