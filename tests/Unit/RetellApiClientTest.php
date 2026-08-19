<?php

namespace Tests\Unit;

use App\Models\AiAgent;
use App\Models\AiProviderIntegration;
use App\Services\AiProviderIntegrationService;
use App\Services\AiTools\AiProviderToolCatalog;
use App\Services\RetellApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RetellApiClientTest extends TestCase
{
    public function test_list_agents_uses_the_unified_voice_endpoint_and_follows_pagination(): void
    {
        Http::fake(function (Request $request) {
            if (($request->data()['pagination_key'] ?? null) === 'next-page') {
                return Http::response([
                    'items' => [
                        ['agent_id' => 'agent-2', 'agent_name' => 'After Hours', 'channel' => 'voice'],
                    ],
                    'has_more' => false,
                ]);
            }

            return Http::response([
                'items' => [
                    ['agent_id' => 'agent-1', 'agent_name' => 'Front Desk', 'channel' => 'voice'],
                ],
                'has_more' => true,
                'pagination_key' => 'next-page',
            ]);
        });
        $client = new RetellApiClient($this->integrationService());

        $agents = $client->listAgents($this->integration());

        $this->assertSame([
            ['value' => 'agent-1', 'label' => 'Front Desk'],
            ['value' => 'agent-2', 'label' => 'After Hours'],
        ], $agents);
        Http::assertSent(function (Request $request) {
            $payload = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://api.retellai.com/v2/list-agents'
                && $payload['filter_criteria']['channel'] === [
                    'type' => 'string',
                    'op' => 'eq',
                    'value' => 'voice',
                ]
                && $payload['limit'] === 1000
                && ! array_key_exists('pagination_key', $payload);
        });
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://api.retellai.com/v2/list-agents'
            && ($request->data()['pagination_key'] ?? null) === 'next-page');
        Http::assertSentCount(2);
    }

    public function test_list_agents_rejects_a_broken_pagination_response(): void
    {
        Http::fake(['api.retellai.com/*' => Http::response([
            'items' => [],
            'has_more' => true,
        ])]);

        $this->expectException(\App\Exceptions\RetellApiException::class);
        $this->expectExceptionMessage('Retell did not return a valid agent pagination key.');

        (new RetellApiClient($this->integrationService()))->listAgents($this->integration());
    }

    public function test_provisioning_uses_uuid_phone_number_tcp_and_latest_published_bindings(): void
    {
        Http::fake(['api.retellai.com/*' => Http::response(['phone_number' => self::UUID])]);
        $client = new RetellApiClient($this->integrationService());

        $client->provision($this->agent());

        Http::assertSent(function (Request $request) {
            $payload = $request->data();

            return $request->url() === 'https://api.retellai.com/import-phone-number'
                && $payload['phone_number'] === self::UUID
                && $payload['termination_uri'] === 'pbx.example.com:5080'
                && $payload['ignore_e164_validation'] === true
                && $payload['transport'] === 'TCP'
                && $payload['inbound_agents'][0] === [
                    'agent_id' => 'inbound-agent',
                    'weight' => 1,
                    'agent_version' => 'latest_published',
                ]
                && $payload['outbound_agents'][0]['agent_id'] === 'outbound-agent'
                && ! isset($payload['username'], $payload['password']);
        });
    }

    public function test_disabling_an_agent_removes_both_remote_bindings(): void
    {
        Http::fake(['api.retellai.com/*' => Http::response([])]);
        $client = new RetellApiClient($this->integrationService());

        $client->synchronize($this->agent(), false);

        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
            && $request->data()['inbound_agents'] === []
            && $request->data()['outbound_agents'] === []);
    }

    public function test_tool_sync_treats_a_retell_configured_recipient_as_current(): void
    {
        $tool = app(AiProviderToolCatalog::class)->definitions('retell')[0];
        $tool['parameters']['properties']['recipient']['const'] = 'support@example.org';

        Http::fake(function (Request $request) use ($tool) {
            if (str_contains($request->url(), '/get-agent-versions/')) {
                return Http::response([$this->publishedAgent(3, 7)]);
            }

            if (str_contains($request->url(), '/get-conversation-flow/')) {
                return Http::response(['tools' => [$tool]]);
            }

            return Http::response([], 404);
        });

        $result = (new RetellApiClient($this->integrationService()))->synchronizeTools(
            'inbound-agent',
            app(AiProviderToolCatalog::class)->definitions('retell'),
            null,
            fn () => $this->fail('A draft should not be created when only the Retell recipient differs.'),
        );

        $this->assertFalse($result['changed']);
        $this->assertFalse($result['published']);
        $this->assertFalse($result['configuration_required']);
        Http::assertSentCount(2);
    }

    public function test_tool_sync_reports_the_recipient_placeholder_as_configuration_required(): void
    {
        $tool = app(AiProviderToolCatalog::class)->definitions('retell')[0];

        Http::fake(function (Request $request) use ($tool) {
            if (str_contains($request->url(), '/get-agent-versions/')) {
                return Http::response([$this->publishedAgent(3, 7)]);
            }

            if (str_contains($request->url(), '/get-conversation-flow/')) {
                return Http::response(['tools' => [$tool]]);
            }

            return Http::response([], 404);
        });

        $result = (new RetellApiClient($this->integrationService()))->synchronizeTools(
            'inbound-agent',
            app(AiProviderToolCatalog::class)->definitions('retell'),
            null,
            fn () => $this->fail('A draft should not be created when the managed tool already matches.'),
        );

        $this->assertFalse($result['changed']);
        $this->assertFalse($result['published']);
        $this->assertTrue($result['configuration_required']);
        Http::assertSentCount(2);
    }

    public function test_tool_sync_updates_and_publishes_one_draft_while_preserving_the_retell_recipient(): void
    {
        $tool = app(AiProviderToolCatalog::class)->definitions('retell')[0];
        $tool['description'] = 'Old managed description';
        $tool['parameters']['properties']['recipient']['const'] = 'team@example.org';

        Http::fake(function (Request $request) use ($tool) {
            if (str_contains($request->url(), '/get-agent-versions/')) {
                return Http::response([$this->publishedAgent(3, 7)]);
            }

            if (str_contains($request->url(), '/create-agent-version/')) {
                return Http::response($this->draftAgent(4, 8));
            }

            if (str_contains($request->url(), '/get-conversation-flow/')) {
                return Http::response([
                    'tools' => [
                        ['type' => 'custom', 'name' => 'customer_tool', 'tool_id' => 'customer-tool'],
                        $tool,
                    ],
                ]);
            }

            return Http::response([]);
        });

        $createdVersion = null;
        $result = (new RetellApiClient($this->integrationService()))->synchronizeTools(
            'inbound-agent',
            app(AiProviderToolCatalog::class)->definitions('retell'),
            null,
            function (int $version) use (&$createdVersion) {
                $createdVersion = $version;
            },
        );

        $this->assertSame(4, $createdVersion);
        $this->assertTrue($result['changed']);
        $this->assertTrue($result['published']);
        $this->assertFalse($result['configuration_required']);
        $this->assertSame(4, $result['published_agent_version']);

        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'PATCH') {
                return false;
            }

            $managed = collect($request->data()['tools'])
                ->firstWhere('name', AiProviderToolCatalog::SEND_EMAIL_TOOL_NAME);

            return $request->url() === 'https://api.retellai.com/update-conversation-flow/flow-id?version=8'
                && $managed['parameters']['properties']['recipient']['const'] === 'team@example.org'
                && ! array_key_exists('version', $request->data())
                && collect($request->data()['tools'])->where('name', AiProviderToolCatalog::SEND_EMAIL_TOOL_NAME)->count() === 1
                && collect($request->data()['tools'])->contains('name', 'customer_tool');
        });

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://api.retellai.com/publish-agent-version/inbound-agent'
            && $request->data()['version'] === 4);
    }

    private const UUID = '40a021c1-6016-4c62-af08-43dcb4c44528';

    private function integrationService(): AiProviderIntegrationService
    {
        $integration = $this->integration();

        $service = $this->createMock(AiProviderIntegrationService::class);
        $service->method('retell')->willReturn($integration);
        $service->method('terminationUri')->willReturn('pbx.example.com:5080');

        return $service;
    }

    private function integration(): AiProviderIntegration
    {
        return new AiProviderIntegration([
            'provider' => 'retell',
            'api_key' => 'secret-key',
            'enabled' => true,
            'public_sip_host' => 'pbx.example.com',
        ]);
    }

    private function agent(): AiAgent
    {
        return new AiAgent([
            'ai_agent_uuid' => self::UUID,
            'name' => 'Front Desk',
            'provider_phone_number' => self::UUID,
            'inbound_agent_id' => 'inbound-agent',
            'outbound_agent_id' => 'outbound-agent',
            'enabled' => true,
        ]);
    }

    private function publishedAgent(int $version, int $flowVersion): array
    {
        return [
            'agent_id' => 'inbound-agent',
            'version' => $version,
            'is_published' => true,
            'response_engine' => [
                'type' => 'conversation-flow',
                'conversation_flow_id' => 'flow-id',
                'version' => $flowVersion,
            ],
        ];
    }

    private function draftAgent(int $version, int $flowVersion): array
    {
        return array_replace($this->publishedAgent($version, $flowVersion), [
            'is_published' => false,
        ]);
    }
}
