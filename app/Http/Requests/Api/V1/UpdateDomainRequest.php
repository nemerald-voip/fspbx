<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\UpdateDomainRequest as InternalUpdateDomainRequest;

class UpdateDomainRequest extends InternalUpdateDomainRequest
{
    public function authorize(): bool
    {
        // API uses route middleware for permissions
        return true;
    }

    public function bodyParameters(): array
    {
        return [
            'domain_description' => [
                'description' => 'A human-friendly label for the domain.',
                'example' => 'BluePeak Solutions',
            ],
            'domain_name' => [
                'description' => 'The domain name (lowercase).',
                'example' => '10001.fspbx.com',
            ],
            'domain_enabled' => [
                'description' => 'Whether the domain is enabled.',
                'example' => true,
            ],
        ];
    }
}
