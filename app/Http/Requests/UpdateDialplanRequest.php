<?php

namespace App\Http\Requests;

class UpdateDialplanRequest extends StoreDialplanRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('dialplan_edit');
    }
}
