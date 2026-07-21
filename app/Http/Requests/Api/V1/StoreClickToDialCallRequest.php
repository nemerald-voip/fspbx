<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreClickToDialCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'extension' => ['required', 'string', 'max:32'],
            'destination' => ['required', 'string', 'max:128'],
            'agent' => ['nullable', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:32'],
            'event' => ['nullable', 'string', 'max:128'],
            'content_type' => ['nullable', 'string', 'max:128'],
            'body' => ['nullable', 'string', 'max:4096'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'extension' => [
                'description' => 'Extension number whose registered phone or softphone should place the call.',
                'example' => '1001',
            ],
            'destination' => [
                'description' => 'Destination number or dial string to send to the phone control path.',
                'example' => '18005551212',
            ],
            'agent' => [
                'description' => 'Optional preferred selector for a specific phone. Retrieve the agent value from GET /api/v1/domains/{domain_uuid}/phone-control/targets; plain text matching is case-insensitive.',
                'example' => 'SIP-T53W',
            ],
            'vendor' => [
                'description' => 'Optional broader selector for any matching phone from this vendor. Valid values: poly, polycom, yealink, grandstream, snom, ringotel, or generic.',
                'example' => 'yealink',
            ],
            'event' => [
                'description' => 'Optional SIP NOTIFY Event header override. Supplying this forces the NOTIFY transport.',
                'example' => 'ACTION-URI',
            ],
            'content_type' => [
                'description' => 'Optional SIP NOTIFY Content-Type override. Supplying this forces the NOTIFY transport.',
                'example' => 'message/sipfrag',
            ],
            'body' => [
                'description' => 'Optional SIP NOTIFY body override. Supplying this forces the NOTIFY transport.',
                'example' => 'number=18005551212&outgoing_uri=sip:1001@example.com',
            ],
        ];
    }
}
