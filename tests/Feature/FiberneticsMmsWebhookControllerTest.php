<?php

namespace Tests\Feature;

use App\Models\WhCall;
use App\Services\Messaging\FiberneticsMmsWebhookAuditService;
use App\Services\Messaging\FiberneticsMmsWebhookQueueService;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class FiberneticsMmsWebhookControllerTest extends TestCase
{
    public function test_accepts_multipart_request_from_an_allowed_source(): void
    {
        config([
            'fibernetics.mms_webhook_ips' => ['107.150.228.75/32'],
            'messaging.webhook_debug' => true,
        ]);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '107.150.228.75'])
            ->post('/webhook/fibernetics/sms', [
                'MMSFrom' => '+16465552200',
                'MMSSubject' => 'Test image',
                'MMSFile' => [
                    UploadedFile::fake()->create('photo.jpg', 32, 'image/jpeg'),
                ],
            ]);

        $response->assertOk()->assertSee('OK');
    }

    public function test_rejects_an_unlisted_source(): void
    {
        config([
            'fibernetics.mms_webhook_ips' => ['107.150.228.75/32'],
            'messaging.webhook_debug' => false,
        ]);

        $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->post('/webhook/fibernetics/sms', [
                'MMSFrom' => '+16465552200',
            ])
            ->assertForbidden();
    }

    public function test_accepts_mm7_multipart_request_and_returns_deliver_response(): void
    {
        config([
            'fibernetics.mms_webhook_ips' => ['107.150.228.75/32'],
            'messaging.webhook_debug' => true,
        ]);
        $webhookCall = new WhCall();
        $webhookCall->id = 'fibernetics-mm7-call';
        $audit = Mockery::mock(FiberneticsMmsWebhookAuditService::class);
        $audit->shouldReceive('record')
            ->once()
            ->with(
                Mockery::type(\Illuminate\Http\Request::class),
                Mockery::on(fn (array $mm7): bool => $mm7['message']['transaction_id'] === 'mms-inbound-123'
                    && $mm7['media'][0]['binary'] === 'image-bytes')
            )
            ->andReturn($webhookCall);
        $this->app->instance(FiberneticsMmsWebhookAuditService::class, $audit);
        $queue = Mockery::mock(FiberneticsMmsWebhookQueueService::class);
        $queue->shouldReceive('queue')
            ->once()
            ->with(
                $webhookCall,
                Mockery::on(fn (array $media): bool => count($media) === 1
                    && $media[0]['original_name'] === 'photo.jpg'
                    && $media[0]['mime_type'] === 'image/jpeg'
                    && $media[0]['binary'] === 'image-bytes')
            );
        $this->app->instance(FiberneticsMmsWebhookQueueService::class, $queue);

        $boundary = 'test-mm7-boundary';
        $transactionId = 'mms-inbound-123';
        $soap = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:mm7="urn:mm7">'
            . '<soapenv:Header><mm7:TransactionID>' . $transactionId . '</mm7:TransactionID></soapenv:Header>'
            . '<soapenv:Body><mm7:DeliverReq><mm7:MM7Version>6.8.0</mm7:MM7Version>'
            . '<mm7:Sender><mm7:SenderAddress><mm7:Number>+16465552200</mm7:Number></mm7:SenderAddress></mm7:Sender>'
            . '<mm7:Recipients><mm7:To><mm7:Number>+19055550100</mm7:Number></mm7:To></mm7:Recipients>'
            . '<mm7:Subject>Test image</mm7:Subject><mm7:Content href="cid:photo"/></mm7:DeliverReq></soapenv:Body>'
            . '</soapenv:Envelope>';
        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/xml; charset=UTF-8\r\n"
            . "Content-ID: <mm7_msg>\r\n\r\n"
            . $soap . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: image/jpeg; name=\"photo.jpg\"\r\n"
            . "Content-ID: <photo>\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . base64_encode('image-bytes') . "\r\n"
            . '--' . $boundary . "--\r\n";

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '107.150.228.75',
                'CONTENT_TYPE' => 'multipart/related; boundary="' . $boundary . '"; type="text/xml"; start="<mm7_msg>"',
            ])
            ->call('POST', '/webhook/fibernetics/sms', [], [], [], [], $body);

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'text/xml; charset=UTF-8')
            ->assertSee('<mm7:DeliverRsp>', false)
            ->assertSee($transactionId, false)
            ->assertSee('<mm7:StatusCode>1000</mm7:StatusCode>', false);
    }

    public function test_returns_submit_response_for_submit_request(): void
    {
        config([
            'fibernetics.mms_webhook_ips' => ['107.150.228.75/32'],
            'messaging.webhook_debug' => true,
        ]);
        $webhookCall = new WhCall();
        $webhookCall->id = 'fibernetics-mm7-submit-call';
        $audit = Mockery::mock(FiberneticsMmsWebhookAuditService::class);
        $audit->shouldReceive('record')
            ->once()
            ->with(
                Mockery::type(\Illuminate\Http\Request::class),
                Mockery::on(fn (array $mm7): bool => $mm7['message']['transaction_id'] === 'submit-123')
            )
            ->andReturn($webhookCall);
        $this->app->instance(FiberneticsMmsWebhookAuditService::class, $audit);
        $queue = Mockery::mock(FiberneticsMmsWebhookQueueService::class);
        $queue->shouldReceive('queue')
            ->once()
            ->with($webhookCall, []);
        $this->app->instance(FiberneticsMmsWebhookQueueService::class, $queue);

        $boundary = 'test-mm7-submit-boundary';
        $soap = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:mm7="urn:mm7">'
            . '<soapenv:Header><mm7:TransactionID>submit-123</mm7:TransactionID></soapenv:Header>'
            . '<soapenv:Body><mm7:SubmitReq><mm7:MM7Version>6.8.0</mm7:MM7Version>'
            . '<mm7:Sender><mm7:SenderAddress><mm7:Number>+16465552200</mm7:Number></mm7:SenderAddress></mm7:Sender>'
            . '<mm7:Recipients><mm7:To><mm7:Number>+19055550100</mm7:Number></mm7:To></mm7:Recipients>'
            . '<mm7:Content href="cid:mms_cid"/></mm7:SubmitReq></soapenv:Body></soapenv:Envelope>';
        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/xml; charset=UTF-8\r\n"
            . "Content-ID: <mm7_msg>\r\n\r\n"
            . $soap . "\r\n"
            . '--' . $boundary . "--\r\n";

        $response = $this
            ->withServerVariables([
                'REMOTE_ADDR' => '107.150.228.75',
                'CONTENT_TYPE' => 'multipart/related; boundary="' . $boundary . '"; type="text/xml"',
            ])
            ->call('POST', '/webhook/fibernetics/sms', [], [], [], [], $body);

        $response
            ->assertOk()
            ->assertSee('<mm7:SubmitRsp>', false)
            ->assertSee('<mm7:MessageID>fspbx-', false)
            ->assertSee('<mm7:StatusCode>1000</mm7:StatusCode>', false);
    }
}
