<?php

namespace Tests\Unit;

use App\Http\Requests\StoreZtpOrganizationRequest;
use App\Http\Requests\UpdateCloudProviderCredentialsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class YealinkRpsRequestTest extends TestCase
{
    public function test_yealink_server_fields_follow_the_rps_limits(): void
    {
        $request = new StoreZtpOrganizationRequest();
        $request->merge(['provider' => 'yealink']);

        $validator = Validator::make([
            'provider' => 'yealink',
            'name' => 'Main RPS Server',
            'address' => 'https://pbx.example.test/prov/',
            'prov_un' => 'provision-user',
            'prov_pw' => 'provision-password',
        ], $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_yealink_server_rejects_invalid_url_and_oversized_credentials(): void
    {
        $request = new StoreZtpOrganizationRequest();
        $request->merge(['provider' => 'yealink']);

        $validator = Validator::make([
            'provider' => 'yealink',
            'name' => str_repeat('a', 21),
            'address' => 'not-a-url',
            'prov_un' => str_repeat('u', 33),
            'prov_pw' => str_repeat('p', 33),
        ], $request->rules());

        $this->assertTrue($validator->errors()->has('name'));
        $this->assertTrue($validator->errors()->has('address'));
        $this->assertTrue($validator->errors()->has('prov_un'));
        $this->assertTrue($validator->errors()->has('prov_pw'));
    }

    public function test_yealink_credentials_require_both_access_keys(): void
    {
        $validator = Validator::make([
            'provider' => 'yealink',
            'access_key_id' => 'access-key-id',
        ], (new UpdateCloudProviderCredentialsRequest())->rules());

        $this->assertTrue($validator->errors()->has('access_key_secret'));
    }
}
