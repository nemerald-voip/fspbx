<?php

namespace App\Http\Requests;

class UpdateAccessControlRequest extends StoreAccessControlRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('access_control_edit');
    }
}
