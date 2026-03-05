<?php

namespace App\Observers;

use App\Models\Organization;

class OrganizationObserver
{
    public function deleting(Organization $organization): void
    {
        // 1. Unlink Contacts (Set organization_uuid to null)
        $organization->contacts()->update(['organization_uuid' => null]);

    }
}