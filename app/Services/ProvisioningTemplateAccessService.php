<?php

namespace App\Services;

use App\Models\ProvisioningTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/** Account scope for the template editor; shared templates never grant access to other accounts. */
class ProvisioningTemplateAccessService
{
    public function currentDomain(): string
    {
        $domain = session('domain_uuid');
        abort_unless(is_string($domain) && Str::isUuid($domain), 403);

        return $domain;
    }

    public function visibleQuery(): Builder
    {
        return ProvisioningTemplate::visibleToDomain($this->currentDomain());
    }

    public function editableQuery(): Builder
    {
        return $this->visibleQuery()->where('type', 'custom')
            ->when(! userCheckPermission('provisioning_templates_manage_global'),
                fn ($query) => $query->where('domain_uuid', $this->currentDomain()));
    }

    public function canModify(ProvisioningTemplate $template, string $action): bool
    {
        return userCheckPermission('provisioning_templates_'.$action)
            && $template->isCustom()
            && ($template->domain_uuid === $this->currentDomain()
                || ($template->domain_uuid === null && userCheckPermission('provisioning_templates_manage_global')));
    }

    public function permissions(): array
    {
        $permissions = [];
        foreach (['view', 'create', 'update', 'delete', 'manage_global'] as $action) {
            $permissions['provisioning_templates_'.$action] = userCheckPermission('provisioning_templates_'.$action);
        }

        return $permissions;
    }
}
