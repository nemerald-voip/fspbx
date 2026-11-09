<?php

namespace Tests\Unit;

use App\Models\Messages;
use App\Services\MessageMediaObjectStorageService;
use App\Services\Messaging\Outbound\Providers\TelnyxOutboundProvider;
use App\Services\Messaging\Providers\BandwidthWebhookParser;
use App\Services\Messaging\Providers\ClickSendWebhookParser;
use App\Services\Messaging\Providers\TelnyxWebhookParser;
use App\Services\Messaging\Providers\TwilioWebhookParser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Mockery;
use Spatie\WebhookClient\Models\WebhookCall;
use Tests\TestCase;

class OutboundMessageProviderContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['messaging.webhook_debug' => false, 'telnyx.message_base_url' => 'https://provider.example.test/v2', 'telnyx.api_key' => 'test']);
    }

    public static function responses(): array
    {
        return [
            [500, ['errors' => [['detail' => 'Internal error']]], false],
            [502, ['errors' => [['detail' => 'Bad gateway']]], false],
            [503, ['errors' => [['detail' => 'Unavailable']]], false],
            [504, ['errors' => [['detail' => 'Gateway timeout']]], false],
            [400, ['errors' => [['detail' => 'Rejected']]], false],
            [200, ['data' => ['id' => 'provider-id', 'to' => [['status' => 'queued']]]], true],
        ];
    }

    /** @dataProvider responses */
    public function test_submission_acceptance_and_rejection_are_distinct(int $http, array $body, bool $accepted): void
    {
        Http::fake(['https://provider.example.test/*' => Http::response($body, $http)]);
        $message = new Messages(['message_uuid' => 'message-id', 'source' => '+12025550100', 'destination' => '+12025550101', 'message' => 'Test', 'media' => []]);
        $result = app(TelnyxOutboundProvider::class)->send($message);
        $this->assertSame($accepted, $result->success);
        Http::assertSentCount(1);
    }

    public function test_network_timeout_is_a_failure(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) { $calls++; throw new ConnectionException('Timeout'); });
        $message = new Messages(['source' => '+12025550100', 'destination' => '+12025550101', 'message' => 'Test', 'media' => []]);
        $result = app(TelnyxOutboundProvider::class)->send($message);
        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertSame('Timeout', $result->error);
        $this->assertSame(1, $calls);
    }

    public static function carrierServerErrors(): array
    {
        $cases = [];
        foreach (['Apidaze', 'Bandwidth', 'BulkVS', 'ClickSend', 'Commio', 'Fibernetics', 'Sinch', 'Telnyx', 'Twilio', 'VoipMs', 'Voxutel'] as $carrier) {
            foreach ([500, 502, 503, 504] as $status) {
                $cases[$carrier.' '.$status] = [$carrier, $status, false];
                if ($carrier === 'Fibernetics') $cases[$carrier.' MMS '.$status] = [$carrier, $status, true];
            }
        }
        return $cases;
    }

    public function test_attachment_exception_before_carrier_submission_is_a_failure(): void
    {
        config(['fibernetics.mm7_url' => 'https://provider.example.test/mm7',
            'fibernetics.mm7_username' => 'test', 'fibernetics.mm7_password' => 'test']);
        Http::preventStrayRequests();
        Http::fake();
        $storage = Mockery::mock(MessageMediaObjectStorageService::class);
        $storage->shouldReceive('getObjectForDomain')->once()->with('domain-id', 'media', 'photo')
            ->andThrow(new \RuntimeException('Attachment unavailable'));
        $message = new Messages(['message_uuid' => 'message-id', 'domain_uuid' => 'domain-id',
            'source' => '+12025550100', 'destination' => '+12025550101', 'message' => 'Test',
            'media' => [['bucket' => 'media', 'object_key' => 'photo', 'original_name' => 'photo.jpg', 'mime_type' => 'image/jpeg']]]);
        $result = (new \App\Services\Messaging\Outbound\Providers\FiberneticsOutboundProvider($storage))->send($message);
        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        $this->assertSame('Attachment unavailable', $result->error);
        Http::assertNothingSent();
    }

    /** @dataProvider carrierServerErrors */
    public function test_all_carriers_treat_http_server_errors_as_failures(string $carrier, int $status, bool $mms): void
    {
        foreach ([
            'apidaze' => ['api_key', 'api_secret', 'endpoint'],
            'bandwidth' => ['account_id', 'application_id', 'api_token', 'api_secret'],
            'bulkvs' => ['username', 'password'], 'clicksend' => ['username', 'api_key'],
            'commio' => ['username', 'token', 'send_endpoint'],
            'fibernetics' => ['sms_username', 'sms_password', 'mm7_username', 'mm7_password'],
            'sinch' => ['api_key'], 'twilio' => ['account_sid', 'auth_token'],
            'voipms' => ['api_username', 'api_password'], 'voxutel' => ['api_key'],
        ] as $provider => $keys) {
            foreach ($keys as $key) config([$provider.'.'.$key => 'test']);
        }
        foreach (['apidaze.base_url', 'bandwidth.message_base_url', 'bulkvs.base_url', 'clicksend.base_url',
            'commio.base_url', 'fibernetics.sms_url', 'fibernetics.mm7_url', 'sinch.message_broker_url',
            'twilio.base_url', 'voipms.base_url', 'voxutel.message_broker_url'] as $key) {
            config([$key => 'https://provider.example.test/']);
        }
        config(['bulkvs.basic_auth_header' => '', 'commio.send_endpoint' => '/send', 'apidaze.endpoint' => '/send']);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('<html>Upstream failure</html>', $status)]);
        $storage = Mockery::mock(MessageMediaObjectStorageService::class);
        $this->app->instance(MessageMediaObjectStorageService::class, $storage);
        $message = new Messages(['message_uuid' => 'message-id', 'domain_uuid' => 'domain-id',
            'source' => '+12025550100', 'destination' => '+12025550101', 'message' => 'Test', 'media' => []]);
        if ($mms) {
            $message->media = [['bucket' => 'media', 'object_key' => 'photo', 'original_name' => 'photo.jpg', 'mime_type' => 'image/jpeg']];
            $storage->shouldReceive('getObjectForDomain')->once()->with('domain-id', 'media', 'photo')
                ->andReturn(['body' => 'image', 'content_type' => 'image/jpeg']);
        }
        $result = app('App\\Services\\Messaging\\Outbound\\Providers\\'.$carrier.'OutboundProvider')->send($message);
        $this->assertFalse($result->success);
        $this->assertSame('failed', $result->status);
        Http::assertSentCount(1);
    }

    public function test_receipts_preserve_nonterminal_and_unconfirmed_states_and_group_correlation(): void
    {
        foreach (['queued', 'sent', 'failed', 'undelivered'] as $state) {
            $call = new WebhookCall;
            $call->payload = ['MessageSid' => 'provider-id', 'MessageStatus' => $state];
            $event = iterator_to_array(app(TwilioWebhookParser::class)->parse($call))[0];
            $this->assertSame($state, $event->rawStatus);
        }
        $call = new WebhookCall;
        $call->payload = ['data' => ['event_type' => 'message.finalized', 'occurred_at' => '2026-11-05T12:00:00Z',
            'payload' => ['id' => 'provider-id', 'to' => [['phone_number' => '+12025550101', 'status' => 'delivery_unconfirmed']]]]];
        $event = iterator_to_array(app(TelnyxWebhookParser::class)->parse($call))[0];
        $this->assertSame('delivery_unconfirmed', $event->rawStatus);
        $this->assertSame('2026-11-05T12:00:00Z', $event->occurredAt);
        $call->payload = [['type' => 'message-delivered', 'time' => '2026-11-05T12:00:00Z', 'to' => '+12025550101',
            'message' => ['id' => 'provider-id']]];
        $event = iterator_to_array(app(BandwidthWebhookParser::class)->parse($call))[0];
        $this->assertSame('+12025550101', $event->recipient);
        $this->assertSame('provider-id', $event->referenceId);
        $call->payload = ['message_id' => 'provider-id', 'status' => 'sent'];
        $event = iterator_to_array(app(ClickSendWebhookParser::class)->parse($call))[0];
        $this->assertSame('sent', $event->rawStatus);
    }
}
