<?php

namespace App\Http\Requests;

class UpdateCallBlockRequest extends StoreCallBlockRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('call_block_edit');
    }
}
