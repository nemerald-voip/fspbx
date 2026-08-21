<?php

namespace Tests\Unit;

use App\Http\Requests\StoreSipProfileRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreSipProfileRequestTest extends TestCase
{
    public function test_profile_description_is_optional(): void
    {
        $rule = (new StoreSipProfileRequest())->rules()['sip_profile_description'];

        $this->assertTrue(Validator::make([], [
            'sip_profile_description' => $rule,
        ])->passes());
        $this->assertTrue(Validator::make([
            'sip_profile_description' => null,
        ], [
            'sip_profile_description' => $rule,
        ])->passes());
        $this->assertTrue(Validator::make([
            'sip_profile_description' => '',
        ], [
            'sip_profile_description' => $rule,
        ])->passes());
    }

    public function test_numeric_setting_values_are_normalized_to_strings(): void
    {
        $request = new class extends StoreSipProfileRequest
        {
            public function normalizeForValidation(): void
            {
                $this->prepareForValidation();
            }
        };

        $request->initialize([], [
            'settings' => [
                ['sip_profile_setting_value' => 35000],
                ['sip_profile_setting_value' => 5.5],
                ['sip_profile_setting_value' => '5060'],
                ['sip_profile_setting_value' => null],
            ],
        ], [], [], [], ['REQUEST_METHOD' => 'POST']);

        $request->normalizeForValidation();

        $this->assertSame('35000', $request->input('settings.0.sip_profile_setting_value'));
        $this->assertSame('5.5', $request->input('settings.1.sip_profile_setting_value'));
        $this->assertSame('5060', $request->input('settings.2.sip_profile_setting_value'));
        $this->assertNull($request->input('settings.3.sip_profile_setting_value'));

        $validator = Validator::make($request->all(), [
            'settings.*.sip_profile_setting_value' => $request->rules()['settings.*.sip_profile_setting_value'],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_non_numeric_setting_values_are_left_for_validation(): void
    {
        $request = new class extends StoreSipProfileRequest
        {
            public function normalizeForValidation(): void
            {
                $this->prepareForValidation();
            }
        };

        $request->initialize([], [
            'settings' => [
                ['sip_profile_setting_value' => ['invalid']],
                ['sip_profile_setting_value' => true],
            ],
        ], [], [], [], ['REQUEST_METHOD' => 'POST']);

        $request->normalizeForValidation();

        $this->assertSame(['invalid'], $request->input('settings.0.sip_profile_setting_value'));
        $this->assertTrue($request->input('settings.1.sip_profile_setting_value'));

        $validator = Validator::make($request->all(), [
            'settings.*.sip_profile_setting_value' => $request->rules()['settings.*.sip_profile_setting_value'],
        ]);

        $this->assertTrue($validator->errors()->has('settings.0.sip_profile_setting_value'));
        $this->assertTrue($validator->errors()->has('settings.1.sip_profile_setting_value'));
    }
}
