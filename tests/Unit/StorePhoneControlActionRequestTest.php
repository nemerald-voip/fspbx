<?php

namespace Tests\Unit;

use App\Http\Requests\Api\V1\StorePhoneControlActionRequest;
use App\Services\PhoneControlDriver;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StorePhoneControlActionRequestTest extends TestCase
{
    public function test_it_accepts_a_documented_action_payload(): void
    {
        $validator = Validator::make([
            'extension' => '1001',
            'action' => PhoneControlDriver::ACTION_HOLD,
            'agent' => 'SIP-T53W',
            'vendor' => 'yealink',
            'lan_ip' => '10.0.0.25',
            'call_id' => 'registration-call-id',
            'force' => false,
            'no_resume' => false,
            'dry_run' => true,
        ], (new StorePhoneControlActionRequest())->rules());

        $this->assertTrue($validator->passes());
    }

    public static function transferActions(): array
    {
        return [
            'blind transfer' => [PhoneControlDriver::ACTION_BLIND_TRANSFER],
            'attended transfer' => [PhoneControlDriver::ACTION_ATTENDED_TRANSFER],
        ];
    }

    /**
     * @dataProvider transferActions
     */
    public function test_transfer_actions_require_a_destination(string $action): void
    {
        $validator = Validator::make([
            'extension' => '1001',
            'action' => $action,
        ], (new StorePhoneControlActionRequest())->rules());

        $this->assertTrue($validator->errors()->has('destination'));
    }

    public function test_it_rejects_an_unknown_action(): void
    {
        $validator = Validator::make([
            'extension' => '1001',
            'action' => 'reboot',
        ], (new StorePhoneControlActionRequest())->rules());

        $this->assertTrue($validator->errors()->has('action'));
    }

    public function test_it_rejects_an_invalid_lan_ip_selector(): void
    {
        $validator = Validator::make([
            'extension' => '1001',
            'action' => PhoneControlDriver::ACTION_HOLD,
            'lan_ip' => 'not-an-ip',
        ], (new StorePhoneControlActionRequest())->rules());

        $this->assertTrue($validator->errors()->has('lan_ip'));
    }
}
