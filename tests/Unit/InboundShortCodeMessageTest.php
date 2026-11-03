<?php

namespace Tests\Unit;

use App\Jobs\DeliverMessageToEmail;
use App\Models\MessageGroup;
use App\Models\Messages;
use App\Services\Messaging\Data\InboundMessageEventData;
use App\Services\Messaging\Data\MessageRouteData;
use App\Services\Messaging\InboundMessagePipeline;
use App\Services\Messaging\MessageDestinationResolver;
use App\Services\Messaging\MessageGroupService;
use App\Services\Messaging\MessageMediaIngestor;
use App\Services\Messaging\MessageRepository;
use App\Services\Messaging\Providers\BandwidthWebhookParser;
use App\Services\Messaging\Providers\MessagingWebhookParser;
use App\Services\Messaging\Providers\SinchWebhookParser;
use App\Services\Messaging\RingotelConversationService;
use App\Services\Messaging\RingotelSyncDispatcher;
use App\Services\Messaging\RingotelSyncService;
use App\Services\PhoneNumberService;
use App\Services\RingotelApiService;
use Illuminate\Bus\Dispatcher as BusDispatcher;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcherContract;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use libphonenumber\PhoneNumberFormat;
use Mockery;
use PHPUnit\Framework\TestCase;
use Spatie\LaravelData\Support\DataConfig;
use Spatie\WebhookClient\Models\WebhookCall;

class InboundShortCodeMessageTest extends TestCase
{
    private const DOMAIN = '11111111-1111-4111-8111-111111111111';
    private const LOCAL = '+12025550100';
    private const REMOTE = '+12025550101';
    private const OTHER = '+12025550102';
    private Manager $db;
    private Container $previousContainer;
    private $previousFacadeApplication;
    private $previousResolver;
    private $previousDispatcher;
    private InboundMessagePipeline $pipeline;
    private array $forwarded = [];
    private array $media = [];

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
        $translator = new Translator(new ArrayLoader(), 'en-us');
        $app->instance('translator', $translator);
        $app->instance('validator', new Factory($translator, $app));
        $app->instance(BusDispatcherContract::class, new BusDispatcher($app));
        Bus::fake();
        Http::preventStrayRequests();

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
        $schema->create('message_groups', function (Blueprint $table) {
            $table->uuid('message_group_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->string('local_number');
            $table->json('recipients');
            $table->timestamps();
        });

        $phone = Mockery::mock(PhoneNumberService::class);
        $phone->shouldReceive('countryCodeForDomain')->andReturn('US');
        $app->instance(PhoneNumberService::class, $phone);
        $resolver = Mockery::mock(MessageDestinationResolver::class);
        $resolver->shouldReceive('isLocal')->andReturnUsing(fn ($number) =>
            formatPhoneNumber($number, 'US', PhoneNumberFormat::E164) === self::LOCAL);
        $resolver->shouldReceive('resolve')->andReturn(new MessageRouteData(
            domainUuid: self::DOMAIN, destination: self::LOCAL, email: 'inbox@example.test',
        ));
        $ingestor = Mockery::mock(MessageMediaIngestor::class);
        $ingestor->shouldReceive('store')->andReturnUsing(fn () => $this->media);
        $dispatcher = Mockery::mock(RingotelSyncDispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andReturnUsing(function (Messages $message) {
            $this->forwarded[] = $message;
            return false;
        });
        $app->instance(RingotelSyncDispatcher::class, $dispatcher);
        $this->pipeline = new InboundMessagePipeline($resolver, $ingestor, new MessageRepository());
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

    /** @dataProvider shortCodeSenders */
    public function test_short_code_is_preserved_in_storage_and_delivery(string $provider, string $sender): void
    {
        $this->handle($this->event($provider, $sender));
        $message = Messages::sole();

        $this->assertSame($sender, $message->source);
        $this->assertSame(self::LOCAL, $message->destination);
        $this->assertSame(self::DOMAIN, $message->domain_uuid);
        $this->assertSame('sms', $message->type);
        $this->assertSame('received', $message->status);
        $this->assertNull($message->message_group_uuid);
        $this->assertSame(0, MessageGroup::count());
        $this->assertCount(1, $this->forwarded);
        $this->assertSame($sender, $this->forwarded[0]->source);
        Bus::assertDispatched(DeliverMessageToEmail::class, fn ($job) =>
            $job->messageUuid === $message->message_uuid && $job->email === 'inbox@example.test' && $job->queue === 'emails');

        $api = Mockery::mock(RingotelApiService::class);
        $api->shouldNotReceive('message');
        $conversations = new RingotelConversationService($api);
        $this->assertSame($sender, $conversations->normalize($message->source, 'US'));
        $sync = new RingotelSyncService($conversations, $api, new MessageRepository());
        $this->assertSame(['text' => ['content' => 'Test message', 'type' => 1]], $sync->payloads($message));
    }

    public static function shortCodeSenders(): array
    {
        $cases = [];
        foreach (['sinch', 'bandwidth'] as $provider) {
            foreach (['73981', '123456', '01234'] as $sender) $cases[$provider.' '.$sender] = [$provider, $sender];
        }
        return $cases;
    }

    /** @dataProvider carriers */
    public function test_duplicate_webhooks_and_equivalent_local_formats_create_one_message(string $provider): void
    {
        $event = $this->event($provider, '73981', [self::LOCAL, '2025550100', self::LOCAL]);
        $this->handle($event);
        $this->handle($event);

        $this->assertSame(1, Messages::count());
        $this->assertSame('73981', Messages::sole()->source);
        $this->assertNull(Messages::sole()->message_group_uuid);
        $this->assertCount(1, $this->forwarded);
        Bus::assertDispatchedTimes(DeliverMessageToEmail::class, 1);
    }

    /** @dataProvider carriers */
    public function test_regular_sms_and_short_code_mms_keep_their_types(string $provider): void
    {
        $this->handle($this->event($provider, self::REMOTE));
        $sms = Messages::sole();
        $this->assertSame(self::REMOTE, $sms->source);
        $this->assertSame('sms', $sms->type);
        $this->assertNull($sms->message_group_uuid);

        $event = $this->event($provider, '73981');
        $event->providerReferenceId = 'mms-reference';
        $event->mediaUrls = ['https://media.example.test/photo.jpg'];
        $this->media = [['object_key' => 'photo.jpg', 'mime_type' => 'image/jpeg']];
        $this->handle($event);
        $mms = Messages::where('reference_id', 'mms-reference')->sole();
        $this->assertSame('73981', $mms->source);
        $this->assertSame('mms', $mms->type);
        $this->assertNull($mms->message_group_uuid);
        $this->assertCount(1, $mms->media);
        $this->assertSame(1, data_get($mms->delivery_meta, 'provider.expected_media_count'));
    }

    /** @dataProvider carriers */
    public function test_group_replies_keep_the_same_roster_and_identity(string $provider): void
    {
        $this->handle($this->event($provider, self::REMOTE, [self::OTHER, self::LOCAL]));
        $first = Messages::sole();
        $this->assertSame('mms', $first->type);
        $this->assertSame([self::REMOTE, self::OTHER], $first->group->recipients);
        $this->assertSame(self::DOMAIN, $first->group->domain_uuid);

        $reply = $this->event($provider, self::OTHER, [self::LOCAL, self::REMOTE, self::LOCAL]);
        $reply->providerReferenceId = 'reply-reference';
        $this->handle($reply);
        $this->assertSame($first->message_group_uuid, Messages::where('reference_id', 'reply-reference')->sole()->message_group_uuid);
        $this->assertSame(1, MessageGroup::count());
        $this->assertSame(2, Messages::count());
    }

    /** @dataProvider rejectedMessages */
    public function test_invalid_or_group_messages_do_not_use_the_short_code_exception(string $provider, string $sender, array $to): void
    {
        try {
            $this->handle($this->event($provider, $sender, $to));
            $this->fail('Expected recipient validation to reject this message.');
        } catch (ValidationException $e) {
            $this->assertSame(['recipients' => ['Enter valid recipient phone numbers.']], $e->errors());
        }
        $this->assertSame(0, Messages::count());
        $this->assertSame(0, MessageGroup::count());
        $this->assertSame([], $this->forwarded);
        Bus::assertNothingDispatched();
    }

    public static function rejectedMessages(): array
    {
        $cases = [];
        foreach (['sinch', 'bandwidth'] as $provider) {
            foreach (['', 'abcde', '1234', '1234567', '+73981', "73981\n"] as $sender) {
                $cases[$provider.' invalid '.json_encode($sender)] = [$provider, $sender, [self::LOCAL]];
            }
            $cases[$provider.' short code group'] = [$provider, '73981', [self::LOCAL, self::OTHER]];
            $cases[$provider.' invalid recipient'] = [$provider, '73981', [self::LOCAL, 'invalid']];
        }
        // Bandwidth's local owner is separate from its full participant list.
        $cases['bandwidth missing roster'] = ['bandwidth', '73981', []];
        $cases['bandwidth other recipient only'] = ['bandwidth', '73981', [self::OTHER]];
        return $cases;
    }

    public function test_shared_outbound_recipient_validation_still_rejects_short_codes(): void
    {
        $this->expectException(ValidationException::class);
        app(MessageGroupService::class)->recipients(['73981'], self::LOCAL, 'US');
    }

    /** @dataProvider carriers */
    public function test_a_short_code_does_not_bypass_local_routing(string $provider): void
    {
        $event = $this->event($provider, '73981', [self::OTHER]);
        $event->localRecipients = null;
        $this->handle($event);
        $this->assertSame(0, Messages::count());
        $this->assertSame([], $this->forwarded);
        Bus::assertNothingDispatched();
    }

    public static function carriers(): array
    {
        return ['Sinch' => ['sinch'], 'Bandwidth' => ['bandwidth']];
    }

    private function event(string $provider, string $sender, array $to = [self::LOCAL]): InboundMessageEventData
    {
        $payload = $provider === 'sinch'
            ? ['type' => 'mo_text', 'deliveryReceipt' => false, 'from' => $sender, 'to' => $to, 'text' => 'Test message', 'referenceId' => 'test-reference']
            : [['type' => 'message-received', 'message' => [
                'id' => 'test-reference', 'from' => $sender, 'to' => $to, 'owner' => self::LOCAL,
                'text' => 'Test message', 'channel' => 'sms',
            ]]];
        return iterator_to_array($this->parser($provider)->parse(new WebhookCall(['payload' => $payload])))[0];
    }

    private function parser(string $provider): MessagingWebhookParser
    {
        return $provider === 'sinch' ? new SinchWebhookParser() : new BandwidthWebhookParser();
    }

    private function handle(InboundMessageEventData $event): void
    {
        $this->pipeline->handle($event, $this->parser($event->provider));
    }
}
