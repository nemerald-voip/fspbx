<?php

namespace App\Http\Requests;

class UpdatePinNumberRequest extends StorePinNumberRequest
{
    public function authorize(): bool
    {
        return userCheckPermission('pin_number_edit');
    }
}
