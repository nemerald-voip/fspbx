<?php

namespace App\Observers;

use App\Models\DomainGroupRelations;
use App\Models\UserDomainGroupPermissions;
use Illuminate\Support\Facades\Cache;

class DomainGroupRelationsObserver
{
    protected function flushForDomainGroup(string $domainGroupUuid): void
    {
        // Get all users assigned to this domain group
        $userUuids = UserDomainGroupPermissions::query()
            ->where('domain_group_uuid', $domainGroupUuid)
            ->pluck('user_uuid')
            ->unique()
            ->values();

        foreach ($userUuids as $userUuid) {
            Cache::tags(['auth', "user:{$userUuid}", 'domain_access'])->flush();
        }
    }

    public function created(DomainGroupRelations $rel): void
    {
        $this->flushForDomainGroup((string) $rel->domain_group_uuid);
    }

    public function updated(DomainGroupRelations $rel): void
    {
        // If domain_group_uuid ever changes (rare), flush both old + new
        $oldGroup = (string) $rel->getOriginal('domain_group_uuid');
        $newGroup = (string) $rel->domain_group_uuid;

        if ($oldGroup) $this->flushForDomainGroup($oldGroup);
        if ($newGroup && $newGroup !== $oldGroup) $this->flushForDomainGroup($newGroup);
    }

    public function deleted(DomainGroupRelations $rel): void
    {
        // Use original value if needed
        $group = (string) ($rel->domain_group_uuid ?: $rel->getOriginal('domain_group_uuid'));
        if ($group) {
            $this->flushForDomainGroup($group);
        }
    }

    public function restored(DomainGroupRelations $rel): void
    {
        $this->flushForDomainGroup((string) $rel->domain_group_uuid);
    }
}
