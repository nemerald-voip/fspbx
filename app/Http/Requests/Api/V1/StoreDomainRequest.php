<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\StoreDomainRequest as InternalStoreDomainRequest;

class StoreDomainRequest extends InternalStoreDomainRequest
{
    public function authorize(): bool
    {
        // API uses route middleware for permissions
        return true;
    }
}
