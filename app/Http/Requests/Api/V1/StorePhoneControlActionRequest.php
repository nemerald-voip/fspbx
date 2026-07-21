<?php

namespace App\Http\Requests\Api\V1;

use App\Services\PhoneControlDriver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePhoneControlActionRequest extends FormRequest
{
    public const ACTIONS = [
        PhoneControlDriver::ACTION_HOLD,
        PhoneControlDriver::ACTION_RESUME,
        PhoneControlDriver::ACTION_BLIND_TRANSFER,
        PhoneControlDriver::ACTION_ATTENDED_TRANSFER,
        PhoneControlDriver::ACTION_COMPLETE_TRANSFER,
        PhoneControlDriver::ACTION_CANCEL_TRANSFER,
        PhoneControlDriver::ACTION_CONFERENCE,
        PhoneControlDriver::ACTION_MUTE_TOGGLE,
        PhoneControlDriver::ACTION_MUTE_ON,
        PhoneControlDriver::ACTION_MUTE_OFF,
        PhoneControlDriver::ACTION_END_CALL,
        PhoneControlDriver::ACTION_ANSWER_CALL,
        PhoneControlDriver::ACTION_DND_ON,
        PhoneControlDriver::ACTION_DND_OFF,
        PhoneControlDriver::ACTION_DND_TOGGLE,
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'extension' => ['required', 'string', 'max:32'],
            'action' => ['required', 'string', Rule::in(self::ACTIONS)],
            'destination' => [
                'nullable',
                'string',
                'max:128',
                'required_if:action,blind-transfer,attended-transfer',
            ],
            'agent' => ['nullable', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:32'],
            'lan_ip' => ['nullable', 'ip'],
            'call_id' => ['nullable', 'string', 'max:255'],
            'force' => ['sometimes', 'boolean'],
            'no_resume' => ['sometimes', 'boolean'],
            'dry_run' => ['sometimes', 'boolean'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'extension' => [
                'description' => 'Extension number whose registered phone should be controlled.',
                'example' => '1001',
            ],
            'action' => [
                'description' => 'Phone-control action. The target endpoint lists the actions supported by each registered phone.',
                'example' => PhoneControlDriver::ACTION_HOLD,
            ],
            'destination' => [
                'description' => 'Required for blind-transfer and attended-transfer; ignored by other actions.',
                'example' => '2001',
            ],
            'agent' => [
                'description' => 'Optional preferred selector for a specific phone. Use an agent value returned by the targets endpoint; plain text matching is case-insensitive.',
                'example' => 'SIP-T53W',
            ],
            'vendor' => [
                'description' => 'Optional broader selector for a supported driver: yealink, snom, poly, grandstream, or generic.',
                'example' => 'yealink',
            ],
            'lan_ip' => [
                'description' => 'Optional exact device IP selector returned by the targets endpoint.',
                'example' => '10.0.0.25',
            ],
            'call_id' => [
                'description' => 'Optional registration call ID selector returned in a target registration_call_ids array.',
                'example' => '8cc81337-3728-4b87-a507-f59627abf313',
            ],
            'force' => [
                'description' => 'Skip normal single-call state checks. Use only when the phone already has the intended call selected.',
                'example' => false,
            ],
            'no_resume' => [
                'description' => 'For cancel-transfer, leave the original call on hold instead of automatically resuming it.',
                'example' => false,
            ],
            'dry_run' => [
                'description' => 'Resolve the target and return the command preview without sending it.',
                'example' => false,
            ],
        ];
    }
}
