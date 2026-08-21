<?php

namespace Tests\Unit;

use App\Http\Requests\StoreAiProviderIntegrationRequest;
use App\Models\AiProviderIntegration;
use App\Services\AiProviderIntegrationService;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreAiProviderIntegrationRequestTest extends TestCase
{
    public function test_it_normalizes_gateway_style_provider_ip_rows(): void
    {
        $request = $this->request([
            ['node_cidr' => ' 203.0.113.10 '],
            ['node_cidr' => '198.51.100.0/24'],
            ['node_cidr' => ''],
        ]);

        $request->normalizeForValidation();

        $this->assertSame([
            '203.0.113.10',
            '198.51.100.0/24',
        ], $request->input('provider_cidrs'));
    }

    public function test_it_preserves_legacy_line_separated_provider_ips(): void
    {
        $request = $this->request("203.0.113.10\n198.51.100.0/24");

        $request->normalizeForValidation();

        $this->assertSame([
            '203.0.113.10',
            '198.51.100.0/24',
        ], $request->input('provider_cidrs'));
    }

    public function test_api_key_is_required_when_the_integration_has_no_stored_key(): void
    {
        $integration = new AiProviderIntegration(['provider' => 'retell']);
        $service = $this->createMock(AiProviderIntegrationService::class);
        $service->method('retell')->willReturn($integration);
        $this->app->instance(AiProviderIntegrationService::class, $service);

        $request = $this->request([
            ['node_cidr' => '203.0.113.10'],
        ]);
        $request->request->add([
            'api_key' => '',
            'public_sip_host' => 'pbx.example.com',
        ]);
        $request->normalizeForValidation();

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->errors()->has('api_key'));
    }

    private function request(array|string $providerCidrs): StoreAiProviderIntegrationRequest
    {
        $request = new class extends StoreAiProviderIntegrationRequest
        {
            public function normalizeForValidation(): void
            {
                $this->prepareForValidation();
            }
        };

        $request->setMethod('PUT');
        $request->request->replace([
            'provider_cidrs' => $providerCidrs,
            'enabled' => true,
        ]);

        return $request;
    }
}
