<?php

namespace Tests\Unit;

use App\Http\Webhooks\SignatureValidators\FiberneticsSignatureValidator;
use App\Http\Webhooks\WebhookProfiles\FiberneticsWebhookProfile;
use App\Models\Messages;
use App\Services\MessageMediaObjectStorageService;
use App\Services\Messaging\Outbound\Providers\FiberneticsOutboundProvider;
use App\Services\Messaging\Providers\FiberneticsWebhookParser;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\WebhookConfig;
use Tests\TestCase;

class FiberneticsMessagingProviderTest extends TestCase
{
    public function test_sends_ascii_sms_with_e164_query_parameters(): void
    {
        $this->configureProvider();
        Http::fake(['https://sms.example.test/*' => Http::response('0: Accepted for delivery', 200)]);

        $provider = new FiberneticsOutboundProvider(Mockery::mock(MessageMediaObjectStorageService::class));
        $result = $provider->send($this->message('This is a test'));

        $this->assertTrue($result->success);
        $this->assertSame('success', $result->status);

        Http::assertSent(function (ClientRequest $request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), 'username=sms-user')
                && str_contains($request->url(), 'password=sms-password')
                && str_contains($request->url(), 'to=%2B12266664782')
                && str_contains($request->url(), 'from=%2B19052398716')
                && str_contains($request->url(), 'coding=0')
                && str_contains($request->url(), 'text=This%20is%20a%20test');
        });
    }

    public function test_sends_unicode_sms_as_utf16_big_endian(): void
    {
        $this->configureProvider();
        Http::fake(['https://sms.example.test/*' => Http::response('0: Accepted for delivery', 200)]);

        $provider = new FiberneticsOutboundProvider(Mockery::mock(MessageMediaObjectStorageService::class));
        $result = $provider->send($this->message('你好'));

        $this->assertTrue($result->success);

        Http::assertSent(fn (ClientRequest $request) => str_contains($request->url(), 'coding=2')
            && str_contains($request->url(), 'charset=UTF-16BE')
            && str_contains($request->url(), 'text=%4F%60%59%7D'));
    }

    public function test_sends_mms_as_mm7_multipart_request(): void
    {
        $this->configureProvider();
        $mediaStorage = Mockery::mock(MessageMediaObjectStorageService::class);
        $mediaStorage->shouldReceive('getObjectForDomain')
            ->once()
            ->with('domain-uuid', 'messages', 'messages/photo.jpg')
            ->andReturn([
                'body' => 'image-bytes',
                'content_type' => 'image/jpeg',
            ]);

        Http::fake([
            'https://mms.example.test/*' => Http::response(
                '<?xml version="1.0"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
                . '<soapenv:Body><SubmitRsp><MM7Version>6.8.0</MM7Version><Status>'
                . '<StatusCode>1000</StatusCode><StatusText>Success</StatusText></Status>'
                . '<MessageID>mms-123</MessageID></SubmitRsp></soapenv:Body></soapenv:Envelope>',
                200,
                ['Content-Type' => 'text/xml']
            ),
        ]);

        $message = $this->message('Photo');
        $message->media = [[
            'bucket' => 'messages',
            'object_key' => 'messages/photo.jpg',
            'original_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
        ]];

        $result = (new FiberneticsOutboundProvider($mediaStorage))->send($message);

        $this->assertTrue($result->success);
        $this->assertSame('mms-123', $result->providerReferenceId);

        Http::assertSent(function (ClientRequest $request) {
            $body = $request->body();

            return $request->method() === 'POST'
                && $request->url() === 'https://mms.example.test/mm7'
                && $request->hasHeader('Authorization', 'Basic ' . base64_encode('mm7-user:mm7-password'))
                && str_contains($request->header('Content-Type')[0] ?? '', 'multipart/related')
                && str_contains($body, '<mm7:SubmitReq>')
                && str_contains($body, '<mm7:Number>+19052398716</mm7:Number>')
                && str_contains($body, '<mm7:Number>+12266664782</mm7:Number>')
                && str_contains($body, '<mm7:Content href="cid:')
                && str_contains($body, base64_encode('image-bytes'));
        });
    }

    public function test_parses_inbound_unicode_sms(): void
    {
        $call = $this->webhookCall([
            'from' => '12266664782',
            'to' => '19052398716',
            'message' => '',
            'binary' => mb_convert_encoding('你好', 'UTF-16BE', 'UTF-8'),
            'time' => '1784567890',
            'coding' => '2',
            'charset' => 'UTF-16BE',
            'metadata' => 'test',
        ]);

        $events = iterator_to_array(app(FiberneticsWebhookParser::class)->parse($call));

        $this->assertCount(1, $events);
        $this->assertSame('fibernetics', $events[0]->provider);
        $this->assertSame('+12266664782', $events[0]->from);
        $this->assertSame(['+19052398716'], $events[0]->to);
        $this->assertSame('你好', $events[0]->text);
        $this->assertSame('incoming_sms', $events[0]->providerEvent);
    }


    public function test_parses_inbound_windows_1252_sms(): void
    {
        $call = $this->webhookCall([
            'from' => '12266664782',
            'to' => '19052398716',
            'message' => "caf\xE9",
            'coding' => '0',
            'charset' => 'WINDOWS-1252',
        ]);

        $events = iterator_to_array(app(FiberneticsWebhookParser::class)->parse($call));

        $this->assertCount(1, $events);
        $this->assertSame('café', $events[0]->text);
    }

    public function test_only_accepts_fibernetics_source_networks(): void
    {
        config(['fibernetics.webhook_ips' => ['74.205.214.128/29']]);
        $validator = app(FiberneticsSignatureValidator::class);
        $config = Mockery::mock(WebhookConfig::class);

        $allowed = Request::create('/webhook/fibernetics/sms', 'GET', [], [], [], [
            'REMOTE_ADDR' => '74.205.214.130',
        ]);
        $denied = Request::create('/webhook/fibernetics/sms', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.10',
        ]);

        $this->assertTrue($validator->isValid($allowed, $config));
        $this->assertFalse($validator->isValid($denied, $config));
    }

    private function configureProvider(): void
    {
        config([
            'fibernetics.sms_url' => 'https://sms.example.test/cgi-bin/sendsms',
            'fibernetics.sms_username' => 'sms-user',
            'fibernetics.sms_password' => 'sms-password',
            'fibernetics.mm7_url' => 'https://mms.example.test/mm7',
            'fibernetics.mm7_username' => 'mm7-user',
            'fibernetics.mm7_password' => 'mm7-password',
            'fibernetics.mm7_version' => '6.8.0',
            'fibernetics.timeout' => 30,
        ]);
    }
    private function webhookCall(array $payload): WebhookCall
    {
        $request = Request::create('/webhook/fibernetics/sms', 'GET', $payload);
        $this->assertTrue(app(FiberneticsWebhookProfile::class)->shouldProcess($request));

        $call = new WebhookCall();
        $call->payload = $request->input();

        return $call;
    }

    private function message(string $text): Messages
    {
        return new Messages([
            'message_uuid' => 'message-uuid',
            'domain_uuid' => 'domain-uuid',
            'source' => '+19052398716',
            'destination' => '+12266664782',
            'message' => $text,
            'media' => [],
        ]);
    }
}
