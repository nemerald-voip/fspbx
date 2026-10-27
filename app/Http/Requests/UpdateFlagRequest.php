<?php

namespace App\Http\Requests;

class UpdateFlagRequest extends StoreFlagRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('flags_update');
    }

    protected function flagUuid(): ?string
    {
        $flag = $this->route('flag');

        return is_object($flag) ? $flag->uuid : $flag;
    }
}
