<?php

namespace Tests\Unit;

use App\Http\Webhooks\Jobs\ProcessFiberneticsWebhookJob;
use App\Models\WhCall;
use App\Services\MessageMediaObjectStorageService;
use App\Services\Messaging\Data\MessageRouteData;
use App\Services\Messaging\FiberneticsMmsWebhookQueueService;
use App\Services\Messaging\MessageDestinationResolver;
use App\Services\Messaging\MessageRepository;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class FiberneticsMmsWebhookQueueServiceTest extends TestCase
{
    public function test_stages_media_in_object_storage_and_queues_the_webhook_job(): void
    {
        Bus::fake();

        $route = MessageRouteData::from([
            'domainUuid' => 'domain-uuid',
            'destination' => '+19055550100',
        ]);
        $resolver = Mockery::mock(MessageDestinationResolver::class);
        $resolver->shouldReceive('resolve')
            ->once()
            ->with('+19055550100')
            ->andReturn($route);

        $storedMedia = [
            'provider' => 'fibernetics',
            'storage' => 's3_compatible',
            'bucket' => 'messages',
            'object_key' => 'message-media/photo.jpg',
            'original_name' => 'photo.jpg',
            'stored_name' => 'stored-photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 11,
            'access_path' => null,
        ];
        $mediaStorage = Mockery::mock(MessageMediaObjectStorageService::class);
        $mediaStorage->shouldReceive('storeBinary')
            ->once()
            ->with(
                'domain-uuid',
                'image-bytes',
                'photo.jpg',
                'fibernetics',
                'image/jpeg'
            )
            ->andReturn($storedMedia);

        $messages = Mockery::mock(MessageRepository::class);
        $messages->shouldReceive('inboundReferenceExists')
            ->once()
            ->with('fibernetics', 'mms-inbound-123')
            ->andReturnFalse();

        $webhookCall = Mockery::mock(WhCall::class)->makePartial();
        $webhookCall->id = 'webhook-call-uuid';
        $webhookCall->payload = [
            'protocol' => 'mm7',
            'transaction_id' => 'mms-inbound-123',
            'recipients' => ['+19055550100'],
        ];
        $webhookCall->shouldReceive('save')->once()->andReturnTrue();

        $service = new FiberneticsMmsWebhookQueueService(
            $resolver,
            $mediaStorage,
            $messages
        );
        $service->queue($webhookCall, [[
            'binary' => 'image-bytes',
            'original_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
        ]]);

        $this->assertSame(
            [$storedMedia],
            $webhookCall->payload['stored_media']['+19055550100']
        );
        Bus::assertDispatched(
            ProcessFiberneticsWebhookJob::class,
            fn (ProcessFiberneticsWebhookJob $job): bool =>
                $job->webhookCall === $webhookCall
                && $job->queue === 'messages'
        );
    }
}
