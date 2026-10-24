<?php

namespace Tests\Unit;

use App\Models\Messages;
use App\Services\Messaging\Data\InboundMessageEventData;
use App\Services\Messaging\Data\MessageRouteData;
use App\Services\Messaging\InboundMessagePipeline;
use App\Services\Messaging\MessageDestinationResolver;
use App\Services\Messaging\MessageMediaIngestor;
use App\Services\Messaging\MessageRepository;
use App\Services\Messaging\Providers\FiberneticsWebhookParser;
use App\Services\Messaging\Providers\SinchWebhookParser;
use App\Services\Messaging\RingotelConversationService;
use App\Services\Messaging\RingotelSyncDispatcher;
use App\Services\Messaging\RingotelSyncService;
use App\Services\RingotelApiService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\WebhookClient\Models\WebhookCall;

class RingotelMmsPayloadTest extends TestCase
{
    private const DOMAIN = '11111111-1111-4111-8111-111111111111';
    private const LOCAL = '+12025550100';
    private const REMOTE = '+12025550101';
    private Manager $db;
    private Container $previousContainer;
    private $previousFacadeApplication;
    private $previousResolver;
    private $previousDispatcher;
    private RingotelSyncService $sync;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->previousResolver = Model::getConnectionResolver();
        $this->previousDispatcher = Model::getEventDispatcher();
        $app = new Application(dirname(__DIR__, 2));
        Facade::setFacadeApplication($app);
        Facade::clearResolvedInstances();
        $app->instance('config', new Repository([
            'messaging' => ['webhook_debug' => false],
            'data' => require base_path('vendor/spatie/laravel-data/config/data.php'),
        ]));
        $app->instance(DataConfig::class, DataConfig::createFromConfig(config('data')));
        $app->instance('url', new UrlGenerator(new RouteCollection(), Request::create('https://pbx.example.test')));
        Http::preventStrayRequests();
        Http::fake(['https://media.example.test/text.txt' => Http::response('MMS body', 200, ['Content-Type' => 'text/plain'])]);

        $this->db = new Manager($app);
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->setEventDispatcher(new Dispatcher($app));
        $this->db->bootEloquent();
        Model::clearBootedModels();
        $app->instance('db', $this->db->getDatabaseManager());
        $schema = $this->db->getConnection()->getSchemaBuilder();
        $schema->create('messages', function (Blueprint $table) {
            $table->uuid('message_uuid')->primary();
            foreach (['domain_uuid', 'extension_uuid', 'source', 'destination', 'direction', 'message_group_uuid', 'type', 'reference_id', 'status', 'message'] as $column) {
                $table->string($column)->nullable();
            }
            $table->json('media')->nullable();
            $table->json('delivery_meta')->nullable();
            $table->timestamps();
        });
        $schema->create('v_domain_settings', function (Blueprint $table) {
            foreach (['domain_uuid', 'domain_setting_subcategory', 'domain_setting_enabled', 'domain_setting_value'] as $column) $table->string($column);
        });
        $this->db->getConnection()->table('v_domain_settings')->insert([
            'domain_uuid' => self::DOMAIN, 'domain_setting_subcategory' => 'country',
            'domain_setting_enabled' => 'true', 'domain_setting_value' => 'US',
        ]);
        $schema->create('message_groups', function (Blueprint $table) {
            $table->uuid('message_group_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->string('local_number');
            $table->json('recipients');
            $table->timestamps();
        });

        $dispatcher = Mockery::mock(RingotelSyncDispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andReturnFalse();
        $app->instance(RingotelSyncDispatcher::class, $dispatcher);
        $api = Mockery::mock(RingotelApiService::class);
        $api->shouldNotReceive('message');
        $this->sync = new RingotelSyncService(new RingotelConversationService($api), $api, new MessageRepository());
    }

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            $this->db->getConnection()->disconnect();
            Model::clearBootedModels();
            $this->previousResolver ? Model::setConnectionResolver($this->previousResolver) : Model::unsetConnectionResolver();
            $this->previousDispatcher ? Model::setEventDispatcher($this->previousDispatcher) : Model::unsetEventDispatcher();
            Container::setInstance($this->previousContainer);
            Facade::setFacadeApplication($this->previousFacadeApplication);
            Facade::clearResolvedInstances();
            parent::tearDown();
        }
    }

    public function test_sinch_text_part_is_delivered_as_text_while_remaining_an_mms(): void
    {
        $event = $this->sinchEvent();
        $message = $this->persist($event);

        $this->assertSame('mms', $message->type);
        $this->assertSame(['text' => ['content' => 'MMS body', 'type' => 1]], $this->sync->payloads($message));
        $this->assertSame(0, data_get($message->delivery_meta, 'provider.expected_media_count'));
        Http::assertSentCount(1);
    }

    public function test_text_and_photo_are_both_delivered(): void
    {
        $message = $this->persist($this->sinchEvent(['https://media.example.test/photo.jpg']), [$this->photo()]);
        $payloads = $this->sync->payloads($message);

        $this->assertSame(['text', 'media_0'], array_keys($payloads));
        $this->assertSame(['content' => 'MMS body', 'type' => 1], $payloads['text']);
        $this->assertSame(7, $payloads['media_0']['type']);
        $this->assertSame('https://pbx.example.test'.$message->media[0]['access_path'], $payloads['media_0']['content']);
        $this->assertSame(1, data_get($message->delivery_meta, 'provider.expected_media_count'));
    }

    public function test_failed_photo_ingestion_does_not_turn_into_a_text_only_success(): void
    {
        // The current ingestor returns no media when a download/storage attempt fails.
        $message = $this->persist($this->sinchEvent(['https://media.example.test/photo.jpg']), []);
        $this->assertSame(1, data_get($message->delivery_meta, 'provider.expected_media_count'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MMS attachments are unavailable');
        $this->sync->payloads($message);
    }

    /** @dataProvider stagedAttachments */
    public function test_staged_mm7_media_is_counted_per_destination(array $media, int $expected): void
    {
        $webhook = new WebhookCall(['payload' => [
            'protocol' => 'mm7', 'sender' => self::REMOTE, 'recipients' => [self::LOCAL], 'text' => 'MM7 body',
            'stored_media' => [self::LOCAL => $media, '+12025550102' => [$this->photo()]],
        ]]);
        $event = iterator_to_array((new FiberneticsWebhookParser())->parse($webhook))[0];
        $message = $this->persist($event);

        $this->assertSame('mms', $message->type);
        $this->assertSame($expected, data_get($message->delivery_meta, 'provider.expected_media_count'));
        $this->assertCount(1 + $expected, $this->sync->payloads($message));
    }

    public static function stagedAttachments(): array
    {
        return ['text only' => [[], 0], 'with photo' => [[['object_key' => 'photo.jpg', 'mime_type' => 'image/jpeg']], 1]];
    }

    public function test_missing_staged_destination_is_not_treated_as_confirmed_text_only(): void
    {
        $event = new InboundMessageEventData(
            provider: 'fibernetics', providerReferenceId: null, from: self::REMOTE, to: [self::LOCAL],
            text: 'Caption', storedMedia: ['+12025550102' => [$this->photo()]], isMms: true,
        );
        $message = $this->persist($event);

        $this->assertNull(data_get($message->delivery_meta, 'provider.expected_media_count'));
        $this->expectException(RuntimeException::class);
        $this->sync->payloads($message);
    }

    public function test_group_text_only_mms_keeps_working(): void
    {
        $event = $this->sinchEvent();
        $event->to[] = '+12025550102';
        $message = $this->persist($event);

        $this->assertNotNull($message->message_group_uuid);
        $this->assertSame(['text' => ['content' => 'MMS body', 'type' => 1]], $this->sync->payloads($message));
    }

    public function test_legacy_mms_without_attachment_information_still_requires_review(): void
    {
        $message = new Messages(['direction' => 'in', 'type' => 'mms', 'message' => 'Caption', 'media' => []]);
        $this->expectException(RuntimeException::class);
        $this->sync->payloads($message);
    }

    public function test_empty_mms_is_not_reported_as_delivered(): void
    {
        $event = $this->sinchEvent();
        $event->text = '';
        $message = $this->persist($event);
        $this->expectException(RuntimeException::class);
        $this->sync->payloads($message);
    }

    public function test_missing_media_url_is_still_rejected(): void
    {
        $message = $this->persist($this->sinchEvent());
        $message->media = [['object_key' => 'missing.jpg']];
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no accessible media URL');
        $this->sync->payloads($message);
    }

    private function sinchEvent(array $extraMedia = []): InboundMessageEventData
    {
        $webhook = new WebhookCall(['payload' => [
            'deliveryReceipt' => false, 'from' => self::REMOTE, 'to' => [self::LOCAL],
            'mediaUrls' => array_merge(['https://media.example.test/text.txt', 'https://media.example.test/layout.smil'], $extraMedia),
        ]]);
        return iterator_to_array((new SinchWebhookParser())->parse($webhook))[0];
    }

    private function persist(InboundMessageEventData $event, array $media = []): Messages
    {
        $resolver = Mockery::mock(MessageDestinationResolver::class);
        $resolver->shouldReceive('isLocal')->andReturnUsing(fn ($number) => $number === self::LOCAL);
        $resolver->shouldReceive('resolve')->with(self::LOCAL)->once()->andReturn(new MessageRouteData(self::DOMAIN, self::LOCAL));
        $ingestor = Mockery::mock(MessageMediaIngestor::class);
        if ($event->storedMedia !== []) {
            $ingestor->shouldNotReceive('store');
        } else {
            $ingestor->shouldReceive('store')->once()->andReturn($media);
        }
        (new InboundMessagePipeline($resolver, $ingestor, new MessageRepository()))->handle($event, new SinchWebhookParser());
        return Messages::firstOrFail();
    }

    private function photo(): array
    {
        return ['object_key' => 'photo.jpg', 'mime_type' => 'image/jpeg'];
    }
}
