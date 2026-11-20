<?php

namespace Tests\Unit;

use App\Http\Middleware\NoStoreByDefault;
use App\Http\Middleware\SetApplicationLocale;
use App\Http\Webhooks\Jobs\ProcessVoipInnovationsWebhookJob;
use App\Http\Webhooks\WebhookProfiles\VoipInnovationsWebhookProfile;
use App\Models\VoipInnovationsWebhookCall;
use App\Services\Messaging\Data\DeliveryStatusEventData;
use App\Services\Messaging\Data\InboundMessageEventData;
use App\Services\Messaging\Providers\VoipInnovationsWebhookParser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Spatie\WebhookClient\Models\WebhookCall;
use Tests\TestCase;

class VoipInnovationsWebhookTest extends TestCase
{
    public function test_delivered_receipt_uses_the_vi_uuid(): void
    {
        $receipt = [
            'messageType' => 'DLR',
            'uuid' => '00000000-0000-4000-8000-000000000001',
            'remote_id' => 'downstream-id',
            'status' => 'Delivered',
            'to' => '2025550100',
        ];

        $events = iterator_to_array((new VoipInnovationsWebhookParser)->parse(
            new WebhookCall(['payload' => $receipt])
        ));

        $this->assertCount(1, $events);
        $this->assertInstanceOf(DeliveryStatusEventData::class, $events[0]);
        $this->assertSame('voipinnovations', $events[0]->provider);
        $this->assertSame('delivered', $events[0]->status);
        $this->assertSame($receipt['uuid'], $events[0]->referenceId);
        $this->assertSame('+12025550100', $events[0]->recipient);
    }

    public function test_inline_png_is_available_to_the_media_ingestor(): void
    {
        $image = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/RmsAAAAASUVORK5CYII=';
        $payload = [
            'messageType' => 'MMS',
            'uuid' => '00000000-0000-4000-8000-000000000002',
            'from' => '12025550100',
            'to' => '12025550101',
            'text' => 'Caption',
            'files' => ['photo.png' => $image],
        ];
        $parser = new VoipInnovationsWebhookParser;
        $events = iterator_to_array($parser->parse(new WebhookCall(['payload' => $payload])));

        $this->assertCount(1, $events);
        $this->assertInstanceOf(InboundMessageEventData::class, $events[0]);
        $this->assertTrue($events[0]->isMms);
        $this->assertSame(['+12025550101'], $events[0]->to);
        $this->assertSame('Caption', $events[0]->text);
        $this->assertCount(1, $events[0]->mediaUrls);

        $media = $parser->downloadMedia($events[0]->mediaUrls[0]);
        $this->assertSame(base64_decode($image, true), $media->binary);
        $this->assertSame('image/png', $media->mimeType);
        $this->assertSame('photo.png', $media->originalName);
        $this->assertSame('vi-inline://[redacted]', $media->sourceUrl);
    }

    public function test_malformed_inline_media_is_ignored_by_the_profile(): void
    {
        $request = Request::create('/', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'messageType' => 'MMS',
            'uuid' => '00000000-0000-4000-8000-000000000003',
            'from' => '12025550100',
            'to' => '12025550101',
            'files' => ['photo.png' => 'invalid!'],
        ], JSON_THROW_ON_ERROR));

        $this->assertFalse((new VoipInnovationsWebhookProfile)->shouldProcess($request));
    }

    public function test_incorrect_path_key_is_rejected(): void
    {
        $this->withoutMiddleware([NoStoreByDefault::class, SetApplicationLocale::class]);
        config(['voipinnovations.inbound_webhook_key' => str_repeat('a', 64)]);

        $this->postJson('/webhook/voipinnovations/sms/invalid-key', [
            'messageType' => 'DLR',
            'uuid' => '00000000-0000-4000-8000-000000000004',
            'status' => 'Delivered',
        ])->assertForbidden();
    }

    public function test_valid_callback_is_stored_and_queued_with_its_url_redacted(): void
    {
        $this->withoutMiddleware([NoStoreByDefault::class, SetApplicationLocale::class]);
        $key = str_repeat('a', 64);
        config([
            'voipinnovations.inbound_webhook_key' => $key,
            'database.default' => 'vi_webhook_test',
            'database.connections.vi_webhook_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        Schema::create('webhook_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('url');
            $table->json('headers');
            $table->json('payload');
            $table->text('exception')->nullable();
            $table->timestamps();
        });
        Bus::fake([ProcessVoipInnovationsWebhookJob::class]);

        $this->postJson('/webhook/voipinnovations/sms/' . $key, [
            'messageType' => 'SMS',
            'uuid' => '00000000-0000-4000-8000-000000000005',
            'from' => '+12025550100',
            'to' => '+12025550101',
            'text' => 'Test',
        ])->assertSuccessful();

        $call = VoipInnovationsWebhookCall::query()->sole();
        $this->assertSame('voipinnovations_messaging', $call->name);
        $this->assertSame('/webhook/voipinnovations/sms/[redacted]', $call->url);
        $this->assertSame('Test', $call->payload['text']);
        Bus::assertDispatched(ProcessVoipInnovationsWebhookJob::class);
    }
}
