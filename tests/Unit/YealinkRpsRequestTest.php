<?php

namespace Tests\Unit;

use App\Http\Requests\StoreZtpOrganizationRequest;
use App\Http\Requests\UpdateCloudProviderCredentialsRequest;
use App\Http\Requests\UpdateZtpOrganizationRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class YealinkRpsRequestTest extends TestCase
{
    public function test_yealink_accepts_valid_server_fields(): void
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

    public function test_yealink_server_name_longer_than_twenty_characters_is_accepted(): void
    {
        foreach ([StoreZtpOrganizationRequest::class, UpdateZtpOrganizationRequest::class] as $requestClass) {
            $request = new $requestClass();
            $request->merge(['provider' => 'yealink']);

            $data = [
                'provider' => 'yealink',
                'name' => 'TEst server - admin domain',
                'address' => 'https://pbx.example.test/prov/',
                'prov_un' => 'provision-user',
                'prov_pw' => 'provision-password',
            ];

            if ($request instanceof UpdateZtpOrganizationRequest) {
                $data['organization_id'] = 'server-id';
            }

            $this->assertTrue(Validator::make($data, $request->rules())->passes());
        }
    }

    public function test_yealink_server_rejects_invalid_url_and_oversized_fields(): void
    {
        $request = new StoreZtpOrganizationRequest();
        $request->merge(['provider' => 'yealink']);

        $validator = Validator::make([
            'provider' => 'yealink',
            'name' => str_repeat('a', 101),
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
            'api_url' => 'https://us-api.ymcs.yealink.com',
        ], (new UpdateCloudProviderCredentialsRequest())->rules());

        $this->assertTrue($validator->errors()->has('access_key_secret'));
    }

    public function test_yealink_credentials_require_an_official_ymcs_api_domain(): void
    {
        $validator = Validator::make([
            'provider' => 'yealink',
            'access_key_id' => 'access-key-id',
            'access_key_secret' => 'access-key-secret',
            'api_url' => 'https://example.test',
        ], (new UpdateCloudProviderCredentialsRequest())->rules());

        $this->assertTrue($validator->errors()->has('api_url'));
    }

    public function test_yealink_credentials_accept_each_official_ymcs_api_domain(): void
    {
        foreach ([
            'https://us-api.ymcs.yealink.com',
            'https://eu-api.ymcs.yealink.com',
            'https://au-api.ymcs.yealink.com',
        ] as $apiUrl) {
            $validator = Validator::make([
                'provider' => 'yealink',
                'access_key_id' => 'access-key-id',
                'access_key_secret' => 'access-key-secret',
                'api_url' => $apiUrl,
            ], (new UpdateCloudProviderCredentialsRequest())->rules());

            $this->assertTrue($validator->passes(), "Expected {$apiUrl} to be accepted.");
        }
    }
}
