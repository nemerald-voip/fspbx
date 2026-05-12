<?php

namespace App\Http\Requests;

class UpdateMusicOnHoldRequest extends StoreMusicOnHoldRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('music_on_hold_edit');
    }
}
