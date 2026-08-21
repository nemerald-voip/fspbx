<?php

namespace App\Http\Requests;

class UpdateVirtualReceptionistKeyRequest extends CreateVirtualReceptionistKeyRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'option_uuid' => 'required|uuid',
        ];
    }
}
