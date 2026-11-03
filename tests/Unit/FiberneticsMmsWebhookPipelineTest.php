<?php

namespace Tests\Unit;

use App\Models\Messages;
use App\Services\Messaging\Data\InboundMessageEventData;
use App\Services\Messaging\Data\MessageRouteData;
use App\Services\Messaging\InboundMessagePipeline;
use App\Services\Messaging\MessageDestinationResolver;
use App\Services\Messaging\MessageMediaIngestor;
use App\Services\Messaging\MessageRepository;
use App\Services\Messaging\Providers\MessagingWebhookParser;
use App\Services\Messaging\RingotelSyncDispatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class FiberneticsMmsWebhookPipelineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'logging.default' => 'null',
        ]);
        DB::purge('sqlite');
        Schema::create('v_domain_settings', function (Blueprint $table) {
            foreach (['domain_uuid', 'domain_setting_subcategory', 'domain_setting_value', 'domain_setting_enabled'] as $column) {
                $table->string($column);
            }
        });
        DB::table('v_domain_settings')->insert([
            'domain_uuid' => 'domain-uuid',
            'domain_setting_subcategory' => 'country',
            'domain_setting_value' => 'US',
            'domain_setting_enabled' => 'true',
        ]);
        Schema::create('messages', function (Blueprint $table) {
            foreach (['domain_uuid', 'direction', 'destination', 'reference_id'] as $column) {
                $table->string($column);
            }
            $table->json('delivery_meta')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        parent::tearDown();
    }

    public function test_pipeline_saves_staged_mm7_media_without_downloading_it_again(): void
    {
        $storedMedia = [[
            'bucket' => 'messages',
            'object_key' => 'message-media/photo.jpg',
            'original_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 11,
        ]];
        $event = InboundMessageEventData::from([
            'provider' => 'fibernetics',
            'providerReferenceId' => 'mms-inbound-123',
            'from' => '+16465552200',
            'to' => ['+19055550100'],
            'text' => 'Test image',
            'providerEvent' => 'incoming_mms',
            'storedMedia' => [
                '+19055550100' => $storedMedia,
            ],
            'isMms' => true,
        ]);
        $route = MessageRouteData::from([
            'domainUuid' => 'domain-uuid',
            'destination' => '+19055550100',
        ]);

        $resolver = Mockery::mock(MessageDestinationResolver::class);
        $resolver->shouldReceive('resolve')
            ->once()
            ->with('+19055550100')
            ->andReturn($route);

        $mediaIngestor = Mockery::mock(MessageMediaIngestor::class);
        $mediaIngestor->shouldNotReceive('store');

        $message = new Messages();
        $message->message_uuid = 'message-uuid';
        $ringotel = Mockery::mock(RingotelSyncDispatcher::class);
        $ringotel->shouldReceive('dispatch')->once()->with($message)->andReturnFalse();
        $this->app->instance(RingotelSyncDispatcher::class, $ringotel);
        $messages = Mockery::mock(MessageRepository::class);
        $messages->shouldReceive('inboundReferenceExists')
            ->once()
            ->with('fibernetics', 'mms-inbound-123')
            ->andReturnFalse();
        $messages->shouldReceive('storeInbound')
            ->once()
            ->withArgs(fn (...$arguments): bool =>
                $arguments[0] === 'domain-uuid'
                && $arguments[2] === '+16465552200'
                && $arguments[3] === '+19055550100'
                && $arguments[4] === 'Test image'
                && $arguments[5] === 'mms'
                && $arguments[6] === 'fibernetics'
                && $arguments[7] === 'mms-inbound-123'
                && $arguments[8] === $storedMedia
                && $arguments[9] === 'incoming_mms'
            )
            ->andReturn($message);

        $pipeline = new InboundMessagePipeline(
            $resolver,
            $mediaIngestor,
            $messages
        );
        $pipeline->handle(
            $event,
            Mockery::mock(MessagingWebhookParser::class)
        );
    }
}
