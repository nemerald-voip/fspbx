<?php

namespace App\Http\Requests;

class UpdateGatewayRequest extends StoreGatewayRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('gateway_edit');
    }
}
