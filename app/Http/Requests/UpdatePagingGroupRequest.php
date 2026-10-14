<?php

namespace App\Http\Requests;

class UpdatePagingGroupRequest extends StorePagingGroupRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('paging_groups_update');
    }

    protected function groupUuid(): ?string
    {
        return $this->route('paging_group');
    }
}
