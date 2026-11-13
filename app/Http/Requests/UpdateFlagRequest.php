<?php

namespace App\Http\Requests;

use App\Services\FlagFeature;

class UpdateFlagRequest extends StoreFlagRequest
{
    public function authorize(): bool
    {
        abort_unless(app(FlagFeature::class)->enabled(), 404);

        return userCheckPermission('flags_update');
    }

    protected function flagUuid(): ?string
    {
        $flag = $this->route('flag');

        return is_object($flag) ? $flag->uuid : $flag;
    }
}
