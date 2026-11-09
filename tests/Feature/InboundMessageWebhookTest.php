<?php

namespace Tests\Feature;

use App\Jobs\CallWebhooks\SafeCallWebhookJob;
use App\Jobs\DeliverMessageToEmail;
use App\Listeners\RecordMessageWebhookResult;
use App\Models\Messages;
use App\Models\MessageSetting;
use App\Models\MessageWebhookSetting;
use App\Models\SmsDestinations;
use App\Observers\MessageObserver;
use App\Services\CallWebhooks\PublicWebhookUrlValidator;
use App\Services\MessageMediaObjectStorageService;
use App\Services\Messaging\Data\InboundMessageEventData;
use App\Services\Messaging\Data\MessageRouteData;
use App\Services\Messaging\InboundMessagePipeline;
use App\Services\Messaging\MessageConversationVisibility;
use App\Services\Messaging\MessageDestinationResolver;
use App\Services\Messaging\MessageMediaIngestor;
use App\Services\Messaging\MessageReadService;
use App\Services\Messaging\MessageRepository;
use App\Services\Messaging\MessageSettingsService;
use App\Services\Messaging\MessageWebhookDeliveryService;
use App\Services\Messaging\MessageWebhookSettings;
use App\Services\Messaging\Providers\MessagingWebhookParser;
use App\Services\Messaging\RingotelSyncDispatcher;
use App\Services\PhoneNumberService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;
use Tests\TestCase;

class InboundMessageWebhookTest extends TestCase
{
    private const DOMAIN = '11111111-1111-4111-8111-111111111111';
    private const OTHER = '22222222-2222-4222-8222-222222222222';
    private const LOCAL = '+12025550100';
    private const REMOTE = '+12025550101';
    private SmsDestinations $number;
    private array $media = [];
    private InboundMessagePipeline $pipeline;
    private ?string $postgresSchema = null;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'message_webhook_test',
            'database.connections.message_webhook_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'data.structure_caching.cache.store' => 'array',
            'messaging.webhook_debug' => false,
            'webhook-server' => require base_path('config/webhook-server.php'),
        ]);
        $lab = getenv('FSPBX_OUTBOUND_MESSAGE_LAB');
        if ($lab) {
            if (! str_starts_with(realpath($lab) ?: '', '/tmp/fspbx-message-settings.')) throw new \RuntimeException('Disposable PostgreSQL required.');
            $this->postgresSchema = 'inbound_'.bin2hex(random_bytes(8));
            config(['database.connections.message_webhook_test' => [
                'driver' => 'pgsql', 'host' => $lab, 'port' => 16551, 'database' => 'postgres',
                'username' => 'postgres', 'password' => '', 'charset' => 'utf8', 'prefix' => '',
                'search_path' => $this->postgresSchema, 'sslmode' => 'disable',
            ]]);
        }
        DB::purge('message_webhook_test');
        if ($lab) {
            $this->assertSame($lab.'/data', DB::selectOne('show data_directory')->data_directory);
            DB::statement('CREATE SCHEMA '.$this->postgresSchema);
        }
        $this->app->instance('encrypter', new Encrypter(str_repeat('x', 32), 'AES-256-CBC'));
        Bus::fake();
        Log::spy();

        Schema::create('v_domains', function (Blueprint $table) {
            $table->uuid('domain_uuid')->primary();
        });
        DB::table('v_domains')->insert([['domain_uuid' => self::DOMAIN], ['domain_uuid' => self::OTHER]]);
        Schema::create('v_sms_destinations', function (Blueprint $table) {
            $table->uuid('sms_destination_uuid')->primary();
            foreach (['domain_uuid', 'destination', 'carrier', 'enabled', 'email', 'chatplan_detail_data'] as $column) {
                $table->string($column)->nullable();
            }
        });
        (require base_path('database/migrations/2026_11_04_000001_add_inbound_message_webhooks.php'))->up();
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('message_uuid')->primary();
            foreach (['domain_uuid', 'extension_uuid', 'message_group_uuid'] as $column) $table->uuid($column)->nullable();
            foreach (['source', 'destination', 'direction', 'type', 'reference_id', 'status', 'message'] as $column) {
                $table->string($column)->nullable();
            }
            $table->json('media')->nullable();
            $table->json('delivery_meta')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('message_groups', function (Blueprint $table) {
            $table->uuid('message_group_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->string('local_number');
            $table->json('recipients');
            $table->timestamps();
        });
        foreach (['message_user_reads', 'message_user_hides'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                foreach (['message_uuid', 'domain_uuid'] as $column) $table->uuid($column);
                $table->string('user_uuid');
                $table->boolean('history_deleted')->default(false);
            });
        }
        Messages::flushEventListeners();
        Messages::creating(function (Messages $message) {
            $message->message_uuid ??= (string) \Illuminate\Support\Str::uuid();
        });
        $this->number = (new SmsDestinations())->forceFill([
            'domain_uuid' => self::DOMAIN, 'destination' => self::LOCAL, 'carrier' => 'sinch',
            'enabled' => 'true', 'email' => 'saved@example.test', 'chatplan_detail_data' => '101',
        ]);
        $this->number->save();
        $phone = Mockery::mock(PhoneNumberService::class);
        $phone->shouldReceive('countryCodeForDomain')->andReturn('US');
        $this->app->instance(PhoneNumberService::class, $phone);
        $resolver = Mockery::mock(MessageDestinationResolver::class);
        $resolver->shouldReceive('isLocal')->andReturnUsing(fn ($number) => $number === self::LOCAL);
        $resolver->shouldReceive('resolve')->andReturnUsing(fn () => new MessageRouteData(
            domainUuid: self::DOMAIN, destination: self::LOCAL, email: 'saved@example.test',
            inboundDelivery: app(MessageWebhookSettings::class)->resolve($this->number->refresh()),
            smsDestinationUuid: $this->number->sms_destination_uuid,
        ));
        $ingestor = Mockery::mock(MessageMediaIngestor::class);
        $ingestor->shouldReceive('store')->andReturnUsing(fn () => $this->media);
        $ringotel = Mockery::mock(RingotelSyncDispatcher::class);
        $ringotel->shouldReceive('dispatch')->andReturn(false)->byDefault();
        $this->app->instance(RingotelSyncDispatcher::class, $ringotel);
        $storage = Mockery::mock(MessageMediaObjectStorageService::class);
        $storage->shouldReceive('generateTemporaryDownloadUrl')->with(self::DOMAIN, 'media', 'photo', '+24 hours', 'image/jpeg', 'photo.jpg')
            ->andReturn('https://storage.example.test/photo?signed=1');
        $this->app->instance(MessageMediaObjectStorageService::class, $storage);
        $this->pipeline = new InboundMessagePipeline($resolver, $ingestor, new MessageRepository());
    }

    protected function tearDown(): void
    {
        if ($this->postgresSchema) DB::statement('DROP SCHEMA '.$this->postgresSchema.' CASCADE');
        DB::disconnect('message_webhook_test');
        parent::tearDown();
    }

    public function test_settings_inherit_override_and_preserve_an_encrypted_account_secret(): void
    {
        $settings = app(MessageWebhookSettings::class);
        $this->assertSame(['method' => 'members'], $settings->resolve($this->number));
        $account = $settings->save(self::DOMAIN, 'https://account.example.test/inbound');
        $secret = $account->signing_secret;
        $this->assertNotSame($secret, DB::table('message_webhook_settings')->value('signing_secret'));
        $this->assertArrayNotHasKey('signing_secret', $account->toArray());
        $this->assertNull($settings->props(self::DOMAIN)['signing_secret']);
        $this->assertSame($secret, $settings->props(self::DOMAIN, true)['signing_secret']);
        $this->assertSame('https://account.example.test/inbound', $settings->resolve($this->number)['endpoint_url']);
        $this->assertSame(['method' => 'members'], $settings->resolve((new SmsDestinations())->forceFill(['domain_uuid' => self::OTHER])));
        $other = $settings->ensureSecret(self::OTHER);
        $this->assertNotSame($secret, $other->signing_secret);
        Schema::create('v_extensions', function (Blueprint $table) {
            $table->uuid('extension_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->string('extension');
        });
        Schema::create('sms_destination_members', function (Blueprint $table) {
            foreach (['sms_destination_member_uuid', 'domain_uuid', 'sms_destination_uuid', 'extension_uuid'] as $column) $table->uuid($column);
        });
        DB::table('sms_destination_members')->insert([
            'sms_destination_member_uuid' => '8d0f4c1e-0a4b-4a1c-9d3e-3f1b2c4d5e6f', 'domain_uuid' => self::DOMAIN,
            'sms_destination_uuid' => $this->number->sms_destination_uuid, 'extension_uuid' => '5b8e2a90-6c1d-4e7f-8a2b-1c3d4e5f6a7b',
        ]);
        $model = MessageSetting::findOrFail($this->number->getKey());
        app(MessageSettingsService::class)->update($model, ['inbound_delivery' => 'members']);
        $this->assertSame(['method' => 'members'], $settings->resolve($this->number->refresh()));
        $this->assertSame('saved@example.test', $this->number->email);
        $this->assertSame(1, DB::table('sms_destination_members')->count());
        // A custom webhook replaces extension and email delivery, so their assignments are cleared.
        app(MessageSettingsService::class)->update($model, ['inbound_delivery' => 'webhook', 'inbound_webhook_url' => 'https://number.example.test/inbound', 'email' => 'new@example.test']);
        $this->assertSame('https://number.example.test/inbound', $settings->resolve($this->number->refresh())['endpoint_url']);
        $this->assertNull($this->number->email);
        $this->assertNull($this->number->chatplan_detail_data);
        $this->assertSame(0, DB::table('sms_destination_members')->count());
        $this->assertSame($secret, $settings->save(self::DOMAIN, null)->signing_secret);
        (require base_path('database/migrations/2026_11_04_000001_add_inbound_message_webhooks.php'))->up();
        $this->assertSame(2, MessageWebhookSetting::count());
    }

    /** @dataProvider providers */
    public function test_webhook_route_uses_spatie_and_never_delivers_to_members(string $provider): void
    {
        $account = app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://account.example.test/inbound');
        $this->app->make(RingotelSyncDispatcher::class)->shouldReceive('dispatch')->never();
        Event::fake([\App\Events\MessageSent::class, \App\Events\ConversationUpdated::class]);
        Messages::observe(MessageObserver::class);
        $this->receive($provider);
        $message = Messages::sole();
        $job = $this->job();
        $this->assertSame('received', $message->status);
        $this->assertSame('queued', data_get($message->delivery_meta, 'webhook.status'));
        $this->assertSame('message.received', $job->payload['data']['event_type']);
        $this->assertSame($message->message_uuid, $job->payload['data']['payload']['id']);
        $this->assertSame('Hello ✓', $job->payload['data']['payload']['text']);
        $this->assertSame(self::REMOTE, $job->payload['data']['payload']['from']['phone_number']);
        $this->assertSame('webhooks', $job->queue);
        $this->assertSame('redis', $job->connection);
        $this->assertSame(3, $job->tries);
        $this->assertContains('message-webhook', $job->tags);
        $this->assertNotContains('call-webhook', $job->tags);
        $this->assertStringEndsWith('-Message-Webhooks/1.0', $job->headers['User-Agent']);
        $signature = hash_hmac('sha256', json_encode($job->payload), $account->signing_secret);
        $this->assertTrue(hash_equals($signature, $job->headers['Signature']));
        $this->assertFalse(hash_equals(hash_hmac('sha256', json_encode($job->payload).' ', $account->signing_secret), $signature));
        $this->assertFalse(hash_equals(hash_hmac('sha256', json_encode($job->payload), 'wrong-secret'), $signature));
        Bus::assertNotDispatched(DeliverMessageToEmail::class);
        Event::assertNotDispatched(\App\Events\MessageSent::class);
        Event::assertNotDispatched(\App\Events\ConversationUpdated::class);
        Log::shouldNotHaveReceived('error');
        $this->assertSame(0, Messages::forInbox()->count());
        $visibility = app(MessageConversationVisibility::class);
        $this->assertSame(0, $visibility->visible(Messages::query(), 'user')->count());
        $this->assertSame(0, $visibility->history(Messages::query(), 'user')->count());
        $this->assertSame(0, app(MessageReadService::class)->unread(self::DOMAIN, 'user')->count());
        $this->receive($provider);
        $this->assertSame(1, Messages::count());
        Bus::assertDispatchedTimes(SafeCallWebhookJob::class, 1);
    }

    public static function providers(): array
    {
        return [['sinch'], ['bandwidth'], ['twilio'], ['telnyx'], ['voipms'], ['fibernetics'],
            ['commio'], ['voxutel'], ['clicksend'], ['bulkvs'], ['apidaze']];
    }

    public function test_list_describes_where_each_number_delivers(): void
    {
        $settings = app(MessageWebhookSettings::class);
        $numbers = [
            (new SmsDestinations())->forceFill(['domain_uuid' => self::DOMAIN]),
            (new SmsDestinations())->forceFill(['domain_uuid' => self::DOMAIN, 'inbound_delivery' => 'members']),
            (new SmsDestinations())->forceFill(['domain_uuid' => self::DOMAIN, 'inbound_delivery' => 'webhook', 'inbound_webhook_url' => 'https://number.example.test/inbound']),
            (new SmsDestinations())->forceFill(['domain_uuid' => self::OTHER]),
        ];
        $custom = ['type' => 'custom', 'url' => 'https://number.example.test/inbound'];

        $settings->describe($numbers);
        $this->assertSame([['type' => 'members'], ['type' => 'members'], $custom, ['type' => 'members']], array_map(fn ($number) => $number->delivery, $numbers));

        $settings->save(self::DOMAIN, 'https://account.example.test/inbound');
        $settings->describe($numbers);
        $this->assertSame([['type' => 'account', 'url' => 'https://account.example.test/inbound'], ['type' => 'members'], $custom, ['type' => 'members']], array_map(fn ($number) => $number->delivery, $numbers));
    }

    public function test_members_delivery_is_unchanged_and_custom_routing_does_not_require_account_url(): void
    {
        $this->receive();
        $this->assertFalse(Messages::sole()->isWebhookInbound());
        $this->assertSame('members', data_get(Messages::sole()->delivery_meta, 'inbound.method'));
        Bus::assertDispatched(DeliverMessageToEmail::class);
        Bus::assertNotDispatched(SafeCallWebhookJob::class);
        $this->assertSame(1, Messages::forInbox()->count());
        app(MessageWebhookSettings::class)->ensureSecret(self::DOMAIN);
        $this->number->forceFill(['inbound_delivery' => 'webhook', 'inbound_webhook_url' => 'https://custom.example.test/inbound'])->save();
        $this->receive(reference: 'custom');
        $this->assertSame('https://custom.example.test/inbound', $this->job()->webhookUrl);
        $this->assertSame(2, Messages::count());
        $this->assertSame(1, Messages::forInbox()->count());
    }

    /** @dataProvider groupProviders */
    public function test_mms_group_roster_and_presigned_media_are_preserved(string $provider): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://account.example.test/inbound');
        $this->media = [['bucket' => 'media', 'object_key' => 'photo', 'mime_type' => 'image/jpeg', 'original_name' => 'photo.jpg', 'size' => 123]];
        $this->receive(provider: $provider, recipients: [self::LOCAL, '+12025550102'], mediaUrls: ['https://carrier.example.test/photo']);
        $payload = $this->job()->payload['data']['payload'];
        $this->assertSame('MMS', $payload['type']);
        $this->assertSame(Messages::sole()->message_group_uuid, $payload['group_id']);
        $this->assertEqualsCanonicalizing([self::LOCAL, self::REMOTE, '+12025550102'], $payload['participants']);
        $this->assertSame('https://storage.example.test/photo?signed=1', $payload['media'][0]['url']);
        $this->assertSame('image/jpeg', $payload['media'][0]['content_type']);
        $this->assertSame(123, $payload['media'][0]['size']);
    }

    public static function groupProviders(): array
    {
        return [['sinch'], ['bandwidth']];
    }

    public function test_queue_dispatch_failure_preserves_pending_event_for_duplicate_callback_recovery(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://account.example.test/inbound');
        $dispatcher = Mockery::mock(\Illuminate\Contracts\Bus\Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Queue unavailable'));
        $this->app->instance(\Illuminate\Contracts\Bus\Dispatcher::class, $dispatcher);
        try {
            $this->receive();
            $this->fail('Queue dispatch must fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Queue unavailable', $exception->getMessage());
        }
        $message = Messages::sole();
        $eventId = data_get($message->delivery_meta, 'webhook.event_id');
        $this->assertSame('pending', data_get($message->delivery_meta, 'webhook.status'));
        $this->assertSame(0, Messages::forInbox()->count());
        Bus::fake();
        $this->receive();
        $this->assertSame(1, Messages::count());
        $this->assertSame($eventId, $this->job()->uuid);
        $this->assertSame('queued', data_get(Messages::sole()->delivery_meta, 'webhook.status'));
    }

    public function test_provider_reference_from_another_account_does_not_suppress_delivery(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://account.example.test/inbound');
        $this->receive();
        Messages::query()->update(['domain_uuid' => self::OTHER]);
        Bus::fake();
        $this->receive();
        $this->assertSame(2, Messages::count());
        $this->assertSame(self::DOMAIN, $this->job()->meta['domain_uuid']);
        Bus::assertDispatchedTimes(SafeCallWebhookJob::class, 1);
    }

    public function test_short_codes_and_missing_media_are_not_disguised(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://account.example.test/inbound');
        $this->receive(sender: '12345', mediaUrls: ['https://carrier.example.test/missing']);
        $payload = $this->job()->payload['data']['payload'];
        $this->assertSame('12345', $payload['from']['phone_number']);
        $this->assertSame('MMS', $payload['type']);
        $this->assertSame('media_unavailable', $payload['errors'][0]['code']);
    }

    public function test_recipient_numbers_use_the_receiving_accounts_country_format(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://account.example.test/inbound');
        $this->receive(provider: 'twilio', recipients: ['2025550100']);
        $this->assertSame([['phone_number' => self::LOCAL]], $this->job()->payload['data']['payload']['to']);
    }

    public function test_delivery_failure_retries_through_spatie_then_records_final_failure(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://account.example.test/inbound');
        $this->receive();
        $job = $this->job();
        $this->mockTransport([new Response(503), new Response(503), new Response(503)]);
        // Set the attempt explicitly for each run; event constructors also read it.
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $attemptJob = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
            $attemptJob->shouldReceive('attempts')->andReturn($attempt);
            $attemptJob->shouldReceive('release')->times($attempt < 3 ? 1 : 0);
            $attemptJob->shouldReceive('delete')->times($attempt === 3 ? 1 : 0);
            $job->setJob($attemptJob);
            $job->handle();
        }
        $message = Messages::sole();
        $this->assertSame('failed', data_get($message->delivery_meta, 'webhook.status'));
        $this->assertSame(3, data_get($message->delivery_meta, 'webhook.attempts'));
        $this->assertSame(503, data_get($message->delivery_meta, 'webhook.http_status'));
        $this->assertSame('received', $message->status);
        Bus::assertNotDispatched(DeliverMessageToEmail::class);
        Log::shouldNotHaveReceived('error');
    }

    public function test_success_is_recorded_and_late_failure_cannot_overwrite_it(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://account.example.test/inbound');
        $this->receive();
        $job = $this->job();
        $history = [];
        $this->mockTransport([new Response(204)], $history);
        $job->handle();
        $this->assertSame('delivered', data_get(Messages::sole()->delivery_meta, 'webhook.status'));
        $this->assertNotEmpty($history[0]['request']->getHeaderLine('Timestamp'));
        $this->assertSame(json_encode($job->payload), (string) $history[0]['request']->getBody());
        $this->assertFalse($history[0]['options']['allow_redirects']);
        $this->assertArrayHasKey(CURLOPT_RESOLVE, $history[0]['options']['curl']);
        $listener = app(RecordMessageWebhookResult::class);
        $listener->handle($this->result($job, FinalWebhookCallFailedEvent::class));
        $this->assertSame('delivered', data_get(Messages::sole()->delivery_meta, 'webhook.status'));
    }

    public function test_result_metadata_is_account_scoped_and_pending_events_keep_the_original_route(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://original.example.test/inbound');
        $this->receive();
        $job = $this->job();
        $eventId = $job->uuid;
        $event = $this->result($job, WebhookCallSucceededEvent::class);
        $event->meta['domain_uuid'] = self::OTHER;
        app(RecordMessageWebhookResult::class)->handle($event);
        $this->assertSame('queued', data_get(Messages::sole()->delivery_meta, 'webhook.status'));
        Messages::query()->update(['delivery_meta->webhook->status' => 'pending']);
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://changed.example.test/inbound');
        Bus::fake();
        $this->receive();
        $this->assertSame($eventId, $this->job()->uuid);
        $this->assertSame('https://original.example.test/inbound', $this->job()->webhookUrl);
        $this->assertSame(1, Messages::count());
    }

    public function test_private_webhook_targets_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PublicWebhookUrlValidator())->validateAndResolve('https://127.0.0.1/messages');
    }

    public function test_number_routing_is_validated_on_the_server(): void
    {
        session(['domain_uuid' => self::DOMAIN]);
        foreach ([
            ['inbound_delivery' => 'webhook'],
            ['inbound_delivery' => 'webhook', 'inbound_webhook_url' => 'https://127.0.0.1/inbound'],
            ['inbound_delivery' => 'webhook', 'inbound_webhook_url' => 'http://93.184.216.34/inbound'],
        ] as $data) {
            $request = \App\Http\Requests\CreateMessageSettingRequest::create('/', 'POST', $data + [
                'destination' => self::LOCAL, 'domain_uuid' => self::DOMAIN,
            ]);
            $validator = app('validator')->make($request->all(), $request->rules());
            $request->withValidator($validator);
            $this->assertTrue($validator->fails());
            $this->assertTrue($validator->errors()->has('inbound_webhook_url'));
        }
    }

    public function test_configuration_uses_the_authorized_session_account_and_rejects_token_access(): void
    {
        Schema::create('v_group_permissions', function (Blueprint $table) {
            foreach (['group_uuid', 'domain_uuid', 'permission_name', 'permission_assigned'] as $column) $table->string($column)->nullable();
        });
        DB::table('v_group_permissions')->insert([
            'group_uuid' => 'manager', 'domain_uuid' => self::DOMAIN,
            'permission_name' => 'message_settings_manage', 'permission_assigned' => 'true',
        ]);
        \Illuminate\Support\Facades\Auth::setUser((new \App\Models\User())->forceFill([
            'user_uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'domain_uuid' => self::DOMAIN,
        ]));
        session([
            'domain_uuid' => self::DOMAIN, 'domains' => [['domain_uuid' => self::DOMAIN]],
            'user.groups' => [['group_uuid' => 'manager']],
            'permissions' => [(object) ['permission_name' => 'account_settings_list_view']],
        ]);
        $request = \Illuminate\Http\Request::create('/', 'PUT', [
            'domain_uuid' => self::OTHER, 'endpoint_url' => 'https://93.184.216.34/inbound',
        ]);
        $controller = new \App\Http\Controllers\MessageWebhookSettingsController();
        $response = $controller->update($request, app(MessageWebhookSettings::class));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($response->getData(true)['signing_secret']);
        $this->assertSame(self::DOMAIN, MessageWebhookSetting::sole()->domain_uuid);
        app('router')->setRoutes(new \Illuminate\Routing\RouteCollection());
        app('router')->prefix('api')->middleware('api')->group(base_path('routes/api.php'));
        app('router')->getRoutes()->refreshNameLookups();
        $route = app('router')->getRoutes()->getByName('account-settings.message-webhook.update');
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
        $this->assertContains('api.cookie.auth', $route->gatherMiddleware());
        $request->headers->set('Authorization', 'Bearer test');
        $response = (new \App\Http\Middleware\RequireCookieAuthForSpaRequests())->handle($request, fn () => abort(500));
        $this->assertSame(401, $response->getStatusCode());

        $request->headers->remove('Authorization');
        DB::table('v_group_permissions')->delete();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $controller->update($request, app(MessageWebhookSettings::class));
    }

    public function test_signing_secret_is_created_on_first_view_for_managed_accounts_only(): void
    {
        Schema::create('v_group_permissions', function (Blueprint $table) {
            foreach (['group_uuid', 'domain_uuid', 'permission_name', 'permission_assigned'] as $column) $table->string($column)->nullable();
        });
        DB::table('v_group_permissions')->insert([
            'group_uuid' => 'manager', 'domain_uuid' => self::DOMAIN,
            'permission_name' => 'message_settings_manage', 'permission_assigned' => 'true',
        ]);
        \Illuminate\Support\Facades\Auth::setUser((new \App\Models\User())->forceFill([
            'user_uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'domain_uuid' => self::DOMAIN,
        ]));
        session([
            'domain_uuid' => self::DOMAIN, 'domains' => [['domain_uuid' => self::DOMAIN]],
            'user.groups' => [['group_uuid' => 'manager']],
        ]);
        $controller = new \App\Http\Controllers\MessageWebhookSettingsController();

        $this->assertSame(0, MessageWebhookSetting::count());
        $first = $controller->secret(\Illuminate\Http\Request::create('/', 'POST'), app(MessageWebhookSettings::class));
        $secret = $first->getData(true)['signing_secret'];
        $this->assertSame(64, strlen($secret));
        $this->assertNull(MessageWebhookSetting::sole()->endpoint_url);
        $again = $controller->secret(\Illuminate\Http\Request::create('/', 'POST', ['domain_uuid' => self::DOMAIN]), app(MessageWebhookSettings::class));
        $this->assertSame($secret, $again->getData(true)['signing_secret']);

        try {
            $controller->secret(\Illuminate\Http\Request::create('/', 'POST', ['domain_uuid' => self::OTHER]), app(MessageWebhookSettings::class));
            $this->fail('Another account’s secret must not be returned.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame(1, MessageWebhookSetting::count());
    }

    public function test_existing_call_webhooks_keep_their_delivery_contract(): void
    {
        $subscription = (new \App\Models\CallWebhookSubscription())->forceFill([
            'call_webhook_uuid' => '33333333-3333-4333-8333-333333333333',
            'domain_uuid' => self::DOMAIN, 'endpoint_url' => 'https://calls.example.test/inbound',
            'signing_secret' => 'call-secret', 'enabled' => true, 'events' => ['call.ringing'],
        ]);
        $payload = ['id' => '44444444-4444-4444-8444-444444444444', 'type' => 'call.ringing', 'data' => []];
        app(\App\Services\CallWebhooks\CallWebhookDeliveryService::class)->dispatch($subscription, $payload);
        $job = $this->job();
        $this->assertSame($payload, $job->payload);
        $this->assertSame(hash_hmac('sha256', json_encode($payload), 'call-secret'), $job->headers['Signature']);
        $this->assertContains('call-webhook', $job->tags);
        $this->assertStringEndsWith('-Call-Webhooks/1.0', $job->headers['User-Agent']);
        app(RecordMessageWebhookResult::class)->handle($this->result($job, WebhookCallSucceededEvent::class));
        $this->assertSame(0, Messages::count());
    }

    private function receive(string $provider = 'sinch', string $reference = 'provider-reference', array $recipients = [self::LOCAL], array $mediaUrls = [], string $sender = self::REMOTE): void
    {
        $event = new InboundMessageEventData(provider: $provider, providerReferenceId: $reference, from: $sender,
            to: $recipients, text: 'Hello ✓', mediaUrls: $mediaUrls);
        $this->pipeline->handle($event, Mockery::mock(MessagingWebhookParser::class));
    }

    private function job(): SafeCallWebhookJob
    {
        return Bus::dispatched(SafeCallWebhookJob::class)->last();
    }

    private function mockTransport(array $responses, array &$history = []): void
    {
        $validator = Mockery::mock(PublicWebhookUrlValidator::class);
        $validator->shouldReceive('validateAndResolve')->andReturn('93.184.216.34');
        $this->app->instance(PublicWebhookUrlValidator::class, $validator);
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($history));
        $this->app->instance(Client::class, new Client(['handler' => $handler]));
    }

    private function result(SafeCallWebhookJob $job, string $class)
    {
        return new $class('POST', $job->webhookUrl, $job->payload, $job->headers, $job->meta, $job->tags,
            1, new Response(200), null, null, $job->uuid, null);
    }
}
