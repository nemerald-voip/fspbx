<?php

namespace Tests\Feature\Api\V1;

use App\Events\ConversationUpdated;
use App\Events\MessageSent;
use App\Jobs\PrepareApiMessage;
use App\Jobs\SendOutboundSMSMessage;
use App\Models\MessageApiRequest;
use App\Models\Messages;
use App\Models\MessageSetting;
use App\Models\OutboundMessageWebhookEvent;
use App\Models\User;
use App\Services\CallWebhooks\PublicWebhookUrlValidator;
use App\Services\MessageMediaObjectStorageService;
use App\Services\Messaging\MessageReadService;
use App\Services\Messaging\MessageWebhookSettings;
use App\Services\Messaging\Outbound\ApiMessageMedia;
use App\Services\Messaging\Outbound\ApiMessageService;
use App\Services\Messaging\Outbound\ApiMessageStatus;
use App\Services\Messaging\Outbound\Contracts\OutboundProviderInterface;
use App\Services\Messaging\Outbound\Data\OutboundSendResultData;
use App\Services\Messaging\Outbound\OutboundProviderFactory;
use App\Services\Messaging\OutboundPhotoService;
use App\Services\PhoneNumberService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Encryption\Encrypter;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class OutboundMessageApiTest extends TestCase
{
    private const DOMAIN = '11111111-1111-4111-8111-111111111111';
    private const FOREIGN = '22222222-2222-4222-8222-222222222222';
    private const USER = '33333333-3333-4333-8333-333333333333';
    private const ROLE = '44444444-4444-4444-8444-444444444444';
    private const LOCAL = '+12025550100';
    private const REMOTE = '+12025550101';
    private ?string $schema = null;
    private MessageSetting $number;

    protected function setUp(): void
    {
        parent::setUp();
        $lab = getenv('FSPBX_OUTBOUND_MESSAGE_LAB');
        if (!$lab) $this->markTestSkipped('Run tests/Feature/Api/V1/run-outbound-messages-postgres.sh (disposable PostgreSQL only).');
        if (!str_starts_with(realpath($lab) ?: '', '/tmp/fspbx-message-settings.')) throw new \RuntimeException('Disposable PostgreSQL required.');
        $this->schema = 'outbound_'.bin2hex(random_bytes(8));
        config([
            'database.default' => 'outbound_test',
            'database.connections.outbound_test' => [
                'driver' => 'pgsql', 'host' => $lab, 'port' => 16551, 'database' => 'postgres',
                'username' => 'postgres', 'password' => '', 'charset' => 'utf8', 'prefix' => '',
                'search_path' => $this->schema, 'sslmode' => 'disable',
            ],
            'cache.default' => 'array', 'data.structure_caching.cache.store' => 'array',
            'messaging' => require base_path('config/messaging.php'),
            'webhook-server' => require base_path('config/webhook-server.php'),
            'messaging.webhook_debug' => false,
        ]);
        DB::purge('outbound_test');
        $this->assertSame($lab.'/data', DB::selectOne('show data_directory')->data_directory);
        DB::statement('CREATE SCHEMA '.$this->schema);
        $this->app->instance('encrypter', new Encrypter(str_repeat('x', 32), 'AES-256-CBC'));
        Log::spy()->shouldReceive('channel')->andReturnSelf();
        Bus::fake();
        Event::fake([MessageSent::class, ConversationUpdated::class]);
        $limiter = new RateLimiter(Cache::store('array'));
        $limiter->for('api', app(RateLimiter::class)->limiter('api'));
        $this->app->instance(RateLimiter::class, $limiter);

        foreach ([
            'v_domains' => ['domain_uuid', 'domain_name'], 'v_users' => ['user_uuid', 'domain_uuid'],
            'v_user_groups' => ['user_uuid', 'group_uuid'],
            'v_group_permissions' => ['domain_uuid', 'group_uuid', 'permission_name', 'permission_assigned'],
            'user_domain_permission' => ['user_uuid', 'domain_uuid'],
            'user_domain_group_permissions' => ['user_uuid', 'domain_group_uuid'],
            'domain_group_relations' => ['domain_group_uuid', 'domain_uuid'],
            'v_extensions' => ['extension_uuid', 'domain_uuid', 'extension'],
            'v_sms_destinations' => ['sms_destination_uuid', 'domain_uuid', 'destination', 'carrier', 'enabled', 'email', 'chatplan_detail_data'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns, $name) {
                foreach ($columns as $column) $table->string($column)->nullable();
                if (in_array($name, ['v_domains', 'v_users', 'v_extensions', 'v_sms_destinations'])) $table->primary($columns[0]);
            });
        }
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('message_uuid')->primary();
            foreach (['domain_uuid', 'extension_uuid', 'message_group_uuid'] as $column) $table->uuid($column)->nullable();
            foreach (['source', 'destination', 'direction', 'type', 'reference_id', 'status', 'message'] as $column) $table->text($column)->nullable();
            $table->jsonb('media')->nullable(); $table->jsonb('delivery_meta')->nullable();
            $table->timestamp('read_at')->nullable(); $table->timestamps();
        });
        Schema::create('message_groups', function (Blueprint $table) {
            $table->uuid('message_group_uuid')->primary(); $table->uuid('domain_uuid');
            $table->string('local_number'); $table->jsonb('recipients'); $table->timestamps();
        });
        Schema::create('message_user_reads', function (Blueprint $table) {
            $table->uuid('message_uuid'); $table->uuid('domain_uuid'); $table->uuid('user_uuid');
        });
        (require base_path('database/migrations/2026_11_04_000001_add_inbound_message_webhooks.php'))->up();
        (require base_path('database/migrations/2026_11_09_000001_create_outbound_messaging_tables.php'))->up();
        DB::table('v_domains')->insert([
            ['domain_uuid' => self::DOMAIN, 'domain_name' => 'local.test'], ['domain_uuid' => self::FOREIGN, 'domain_name' => 'foreign.test'],
        ]);
        DB::table('v_user_groups')->insert(['user_uuid' => self::USER, 'group_uuid' => self::ROLE]);
        DB::table('v_group_permissions')->insert(['group_uuid' => self::ROLE, 'permission_name' => 'messages_send', 'permission_assigned' => 'true']);
        $this->number = MessageSetting::create(['domain_uuid' => self::DOMAIN, 'destination' => self::LOCAL,
            'carrier' => 'sinch', 'enabled' => 'true', 'inbound_delivery' => 'inherit']);
        $phone = Mockery::mock(PhoneNumberService::class);
        $phone->shouldReceive('countryCodeForDomain')->with(self::DOMAIN)->andReturn('US');
        $phone->shouldReceive('countryCodeForDomain')->with(self::FOREIGN)->andReturn('GB');
        $this->app->instance(PhoneNumberService::class, $phone);
        $validator = Mockery::mock(PublicWebhookUrlValidator::class);
        $validator->shouldReceive('validateAndResolve')->andReturn('8.8.8.8');
        $this->app->instance(PublicWebhookUrlValidator::class, $validator);
        $photos = Mockery::mock(OutboundPhotoService::class);
        $photos->shouldReceive('prepare')->andReturnNull();
        $this->app->instance(OutboundPhotoService::class, $photos);
        Sanctum::actingAs((new User())->forceFill(['user_uuid' => self::USER, 'domain_uuid' => self::DOMAIN]));
        $this->withToken('test-token');
        $router = app('router');
        $router->setRoutes(new RouteCollection());
        $router->prefix('api/v1')->middleware('api')->group(base_path('routes/api_v1.php'));
    }

    protected function tearDown(): void
    {
        if ($this->schema) DB::statement('DROP SCHEMA '.$this->schema.' CASCADE');
        DB::disconnect('outbound_test');
        parent::tearDown();
    }

    private function url(bool $group = false, string $domain = self::DOMAIN): string
    {
        return '/api/v1/domains/'.$domain.'/messages'.($group ? '/group_mms' : '');
    }

    private function body(array $changes = []): array
    {
        return array_replace(['from' => self::LOCAL, 'to' => self::REMOTE, 'text' => 'Hello'], $changes);
    }

    private function submit(array $changes = [], bool $group = false): Messages
    {
        $id = $this->postJson($this->url($group), $this->body($changes))->assertOk()->json('data.id');
        return Messages::findOrFail($id);
    }

    private function provider(callable $callback): OutboundProviderFactory
    {
        $provider = Mockery::mock(OutboundProviderInterface::class);
        $provider->shouldReceive('send')->once()->andReturnUsing($callback);
        $factory = Mockery::mock(OutboundProviderFactory::class);
        $factory->shouldReceive('make')->once()->with('sinch')->andReturn($provider);
        return $factory;
    }

    public function test_sends_without_members_and_never_exposes_secrets_or_local_conversations(): void
    {
        session(['domain_uuid' => self::FOREIGN]);
        $message = $this->submit();
        $this->assertSame(self::DOMAIN, $message->domain_uuid);
        $this->assertSame(self::LOCAL, $message->source);
        $this->assertNull($message->extension_uuid);
        $this->assertTrue($message->isApiOutbound());
        $this->assertSame(0, Messages::forInbox()->count());
        Bus::assertDispatched(PrepareApiMessage::class, fn ($job) => $job->messageUuid === $message->getKey()
            && $job->connection === 'redis' && $job->queue === 'messages');
        Bus::assertNotDispatched(SendOutboundSMSMessage::class);
        Event::assertNotDispatched(MessageSent::class);
        Event::assertNotDispatched(ConversationUpdated::class);
    }

    public function test_authentication_permission_tenant_and_sender_validation(): void
    {
        $this->withHeader('Authorization', '')->postJson($this->url(), $this->body())->assertUnauthorized();
        $this->withToken('test-token')->postJson($this->url(domain: self::FOREIGN), $this->body())->assertForbidden();
        $this->postJson($this->url(), $this->body(['from' => '+12025550999']))->assertStatus(400)->assertJsonPath('error.param', 'from');
        $this->number->update(['enabled' => 'false']);
        $this->postJson($this->url(), $this->body())->assertStatus(400);
        DB::table('v_group_permissions')->delete();
        Cache::tags(['permissions'])->flush();
        $this->postJson($this->url(), $this->body())->assertForbidden()->assertJsonPath('error.permission', 'messages_send');
        $this->assertSame(0, Messages::count());
    }

    public function test_permission_seeding_refreshes_a_cached_send_denial(): void
    {
        Schema::create('v_groups', function (Blueprint $table) {
            $table->uuid('group_uuid')->primary();
            $table->string('group_name');
        });
        Schema::table('v_group_permissions', function (Blueprint $table) {
            $table->uuid('group_permission_uuid')->nullable();
            $table->string('group_name')->nullable();
            $table->string('permission_protected')->nullable();
            $table->timestampTz('insert_date')->nullable();
        });
        DB::table('v_groups')->insert(['group_uuid' => self::ROLE, 'group_name' => 'superadmin']);
        DB::table('v_group_permissions')->where('permission_name', 'messages_send')->delete();
        DB::table('v_group_permissions')->insert([
            'group_uuid' => self::ROLE, 'permission_name' => 'messages_view', 'permission_assigned' => 'false',
        ]);

        $this->postJson($this->url(), $this->body())->assertForbidden()
            ->assertJsonPath('error.permission', 'messages_send');

        $seeder = new \Database\Seeders\DatabaseSeeder;
        (new \ReflectionMethod($seeder, 'createGroupPermissions'))->invoke($seeder);

        $this->postJson($this->url(), $this->body())->assertOk();
        $this->assertSame('false', DB::table('v_group_permissions')->where('group_uuid', self::ROLE)
            ->where('permission_name', 'messages_view')->value('permission_assigned'));
    }

    public function test_validation_rejects_unsupported_empty_malformed_and_conflicting_inputs(): void
    {
        foreach ([['webhook_url' => 'https://example.test'], ['carrier' => 'twilio'], ['text' => '', 'media_urls' => []],
            ['text' => str_repeat('x', 1601)], ['type' => 'MMS'], ['to' => [self::REMOTE]], ['to' => 'nonsense'],
            ['media_urls' => ['http://example.test/a.jpg']], ['media_urls' => array_fill(0, 11, 'https://example.test/a.jpg')]] as $change) {
            $this->postJson($this->url(), $this->body($change))->assertStatus(400);
        }
        $this->call('POST', $this->url(), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer test-token'], '{"from":,}')->assertStatus(400)->assertJsonPath('error.code', 'invalid_json');
        $this->assertSame(0, Messages::count());
    }

    public function test_requires_e164_for_sender_and_each_recipient(): void
    {
        foreach (['2025550100', '12025550100', '(202) 555-0100', '+1 202 555 0100',
            '+1-202-555-0100', '+02025550100', '+12025550100 ext 1', '+1234567890123456'] as $number) {
            foreach ([false, true] as $group) {
                $this->postJson($this->url($group), $this->body([
                    'from' => $number,
                    'to' => $group ? [self::REMOTE, '+12025550102'] : self::REMOTE,
                ]))->assertStatus(400)->assertJsonPath('error.param', 'from')
                    ->assertJsonPath('error.message', 'Use E.164 format: + followed by the country code and phone number, for example +12025550100.');

                $this->postJson($this->url($group), $this->body([
                    'to' => $group ? [self::REMOTE, $number] : $number,
                ]))->assertStatus(400)->assertJsonPath('error.param', $group ? 'to.1' : 'to');
            }
        }
        $this->assertSame(0, Messages::count());
        Bus::assertNotDispatched(PrepareApiMessage::class);
    }

    public function test_rejects_invalid_numbers_even_with_e164_syntax(): void
    {
        foreach (['+123', '+4402079460123'] as $number) {
            foreach (['from', 'to'] as $field) {
                $this->postJson($this->url(), $this->body([$field => $number]))
                    ->assertStatus(400)->assertJsonPath('error.param', $field);
            }
            $this->postJson($this->url(true), $this->body(['to' => [self::REMOTE, $number]]))
                ->assertStatus(400)->assertJsonPath('error.param', 'to');
        }
        $this->assertSame(0, Messages::count());
        Bus::assertNotDispatched(PrepareApiMessage::class);
    }

    public function test_idempotent_replay_conflict_expiry_and_snapshot(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://original.test/hooks');
        $this->withHeader('Idempotency-Key', 'request-1');
        $first = $this->postJson($this->url(), $this->body())->assertOk()->json();
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://changed.test/hooks');
        $this->assertEquals($first, $this->postJson($this->url(), $this->body())->assertOk()->json());
        $this->assertSame(1, Messages::count());
        $this->postJson($this->url(), $this->body(['text' => 'changed']))->assertStatus(409);
        $this->assertSame('https://original.test/hooks', data_get(Messages::first()->delivery_meta, 'outbound.meta.api.endpoint_url'));
        $this->assertStringNotContainsString('test-token', json_encode(Messages::first()->delivery_meta));
        MessageApiRequest::query()->update(['expires_at' => now()->subSecond()]);
        $this->postJson($this->url(), $this->body())->assertOk();
        $this->assertSame(2, Messages::count());
    }

    public function test_named_uuid_keys_preserve_api_event_ids_and_message_logs(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://example.test/hook');
        $this->withHeader('Idempotency-Key', 'named-uuid-keys');
        $message = $this->submit();
        $submission = MessageApiRequest::sole();
        $this->assertSame('message_api_request_uuid', $submission->getKeyName());
        $this->assertTrue(Str::isUuid($submission->getKey()));
        $this->assertSame($message->getKey(), data_get($submission->response, 'data.id'));

        app(ApiMessageStatus::class)->failPreparation($message->getKey());
        $event = OutboundMessageWebhookEvent::sole();
        $this->assertSame('outbound_message_webhook_event_uuid', $event->getKeyName());
        $this->assertTrue(Str::isUuid($event->getKey()));
        $this->assertSame($event->getKey(), data_get($event->payload, 'data.id'));

        foreach ([$submission, $event] as $model) {
            $primary = collect(Schema::getIndexes($model->getTable()))->firstWhere('primary', true);
            $this->assertSame([$model->getKeyName()], $primary['columns']);
            $this->assertFalse(Schema::hasColumn($model->getTable(), 'id'));
        }

        app(PhoneNumberService::class)->shouldReceive('formatForDomain')->andReturnUsing(fn ($number) => $number);
        Cache::put(self::DOMAIN.'_timeZone', 'UTC');
        session(['domain_uuid' => self::DOMAIN]);
        $this->app->instance('request', \Illuminate\Http\Request::create('/message-logs', 'GET'));
        $row = app(\App\Http\Controllers\MessageController::class)->logs()->items()[0];
        $this->assertSame($event->getKey(), data_get($row, 'delivery_meta.outbound.webhooks.0.id'));
        $this->assertSame('sinch: sending_failed', $row['status_summary']);
    }

    public function test_group_deduplication_reuses_group_and_enforces_capabilities(): void
    {
        $a = $this->submit(['to' => [self::REMOTE, '+12025550102', self::REMOTE, self::LOCAL]], true);
        $b = $this->submit(['to' => ['+12025550102', self::REMOTE]], true);
        $this->assertSame($a->message_group_uuid, $b->message_group_uuid);
        $this->assertSame('mms', $a->type);
        $this->postJson($this->url(true), $this->body(['to' => [self::REMOTE, self::REMOTE]]))->assertStatus(400);
        $this->number->update(['carrier' => 'telnyx']);
        $this->postJson($this->url(true), $this->body(['to' => [self::REMOTE, '+12025550102']]))->assertStatus(400);
    }

    public function test_provider_attachment_counts_and_media_only_mms(): void
    {
        $this->number->update(['carrier' => 'clicksend']);
        $this->postJson($this->url(), $this->body(['media_urls' => ['https://example.test/a', 'https://example.test/b']]))->assertStatus(400);
        $message = $this->submit(['text' => '', 'media_urls' => ['https://example.test/a']]);
        $this->assertSame('mms', $message->type);
        $this->assertSame([], $message->media);
    }

    public function test_provider_accepted_once_even_when_jobs_replayed_and_callbacks_duplicated(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://example.test/hooks');
        $message = $this->submit();
        app(ApiMessageService::class)->prepare($message->getKey());
        $factory = $this->provider(fn () => new OutboundSendResultData(true, 'queued', 'provider-1'));
        $service = app(ApiMessageStatus::class);
        $service->send($message->getKey(), $factory);
        $service->send($message->getKey(), $factory);
        $this->assertSame('sent', Messages::find($message->getKey())->status);
        $receipt = ['provider' => 'sinch', 'referenceId' => 'provider-1', 'status' => 'delivered'];
        $service->receipt($receipt); $service->receipt($receipt);
        $this->assertSame(2, OutboundMessageWebhookEvent::count());
        $this->assertSame('delivered', Messages::find($message->getKey())->status);
        $this->assertSame(0, Messages::forInbox()->count());
        Event::assertNotDispatched(MessageSent::class);
    }

    public function test_provider_reported_unconfirmed_delivery_can_be_corrected_by_a_later_receipt(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://example.test/hooks');
        $message = $this->submit();
        app(ApiMessageService::class)->prepare($message->getKey());
        $factory = $this->provider(fn () => new OutboundSendResultData(true, 'success', 'provider-reference'));
        $service = app(ApiMessageStatus::class);
        $service->send($message->getKey(), $factory);
        $service->send($message->getKey(), $factory);
        $service->receipt(['provider' => 'sinch', 'referenceId' => 'provider-reference', 'status' => 'delivery_unconfirmed']);
        $this->assertSame('delivery_unconfirmed', $service->payload($message->fresh())['to'][0]['status']);
        $service->receipt(['provider' => 'sinch', 'referenceId' => 'provider-reference', 'status' => 'delivered']);
        $this->assertSame('delivered', $message->fresh()->status);
        $this->assertSame(2, OutboundMessageWebhookEvent::where('event_type', 'message.finalized')->count());
    }

    public static function carrierServerErrors(): array
    {
        return [[500], [502], [503], [504]];
    }

    public static function sendExceptions(): array
    {
        return [['adapter'], ['network'], ['local'], ['type_error']];
    }

    /** @dataProvider sendExceptions */
    public function test_send_exceptions_finalize_as_failures(string $source): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://example.test/hooks');
        $message = $this->submit();
        app(ApiMessageService::class)->prepare($message->getKey());
        $detail = 'Fixture submission exception';
        $factory = $this->provider(fn () => match ($source) {
            'adapter' => new OutboundSendResultData(false, 'failed', error: $detail, providerResponse: ['exception' => $detail]),
            'network' => throw new \Illuminate\Http\Client\ConnectionException($detail),
            'local' => throw new \RuntimeException($detail),
            'type_error' => throw new \TypeError($detail),
        });
        $status = app(ApiMessageStatus::class);
        $status->send($message->getKey(), $factory);
        $status->send($message->getKey(), $factory);
        $this->assertSame('failed', $message->fresh()->status);
        $this->assertSame('sending_failed', $status->payload($message->fresh())['to'][0]['status']);
        $this->assertSame($detail, data_get($message->fresh()->delivery_meta, 'outbound.provider.error'));
        $event = OutboundMessageWebhookEvent::sole();
        $this->assertSame('message.finalized', $event->event_type);
        $this->assertSame('sending_failed', data_get($event->payload, 'data.payload.to.0.status'));
        $this->assertSame('submission_failed', data_get($event->payload, 'data.payload.errors.0.code'));
        $this->assertStringNotContainsString($detail, json_encode($event->payload));
    }

    /** @dataProvider carrierServerErrors */
    public function test_carrier_server_error_finalizes_as_sending_failed(int $httpStatus): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://example.test/hooks');
        $this->number->update(['carrier' => 'telnyx']);
        config(['telnyx.message_base_url' => 'https://provider.example.test', 'telnyx.api_key' => 'test']);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake(['https://provider.example.test/*' => \Illuminate\Support\Facades\Http::response(
            ['errors' => [['detail' => 'Server error']], 'exception' => 'Carrier error detail'], $httpStatus)]);
        $message = $this->submit();
        app(ApiMessageService::class)->prepare($message->getKey());
        $status = app(ApiMessageStatus::class);
        $status->send($message->getKey(), app(OutboundProviderFactory::class));
        $this->assertSame('failed', $message->fresh()->status);
        $this->assertSame('sending_failed', $status->payload($message->fresh())['to'][0]['status']);
        $event = OutboundMessageWebhookEvent::sole();
        $this->assertSame('message.finalized', $event->event_type);
        $this->assertSame('sending_failed', data_get($event->payload, 'data.payload.to.0.status'));
        $this->assertSame('submission_failed', data_get($event->payload, 'data.payload.errors.0.code'));
        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public function test_group_finalizes_only_after_all_recipients_and_ignores_stale_receipts(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://example.test/hooks');
        $message = $this->submit(['to' => [self::REMOTE, '+12025550102']], true);
        app(ApiMessageService::class)->prepare($message->getKey());
        $service = app(ApiMessageStatus::class);
        $service->send($message->getKey(), $this->provider(fn () => new OutboundSendResultData(true, 'success', 'group')));
        $base = ['provider' => 'sinch', 'referenceId' => 'group', 'occurredAt' => '2026-11-05T12:00:00Z'];
        $service->receipt($base + ['status' => 'failed', 'recipient' => self::REMOTE]);
        $this->assertSame(0, OutboundMessageWebhookEvent::where('event_type', 'message.finalized')->count());
        $service->receipt($base + ['status' => 'delivered', 'recipient' => '+12025550102']);
        $service->receipt(array_replace($base, ['status' => 'sent', 'recipient' => self::REMOTE, 'occurredAt' => '2026-11-05T11:00:00Z']));
        $this->assertSame(1, OutboundMessageWebhookEvent::where('event_type', 'message.finalized')->count());
        $this->assertSame(['delivery_failed', 'delivered'], array_column($service->payload($message->fresh())['to'], 'status'));
    }

    public function test_api_reply_does_not_clear_member_unread_but_portal_reply_does(): void
    {
        $inbound = Messages::withoutEvents(fn () => Messages::create([
            'message_uuid' => (string) Str::uuid(),
            'domain_uuid' => self::DOMAIN, 'direction' => 'in', 'source' => self::REMOTE, 'destination' => self::LOCAL,
            'message' => 'Member message', 'type' => 'sms', 'status' => 'received', 'media' => [], 'delivery_meta' => [],
        ]));
        $message = $this->submit();
        app(ApiMessageService::class)->prepare($message->getKey());
        app(ApiMessageStatus::class)->send($message->getKey(), $this->provider(fn () => new OutboundSendResultData(true, 'success', 'accepted')));
        $this->assertSame(1, app(MessageReadService::class)->unreadForUsers(self::DOMAIN, [self::USER])->count());
        $meta = $message->fresh()->delivery_meta;
        $meta['outbound']['origin'] = 'portal';
        $message->delivery_meta = $meta;
        $message->saveQuietly();
        $this->assertSame(0, app(MessageReadService::class)->unreadForUsers(self::DOMAIN, [self::USER])->count());
    }

    public function test_members_custom_and_account_callback_routing(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://account.test/hook');
        $this->number->update(['inbound_delivery' => 'members']);
        $member = $this->submit();
        $this->assertNull(data_get($member->delivery_meta, 'outbound.meta.api.endpoint_url'));
        app(ApiMessageStatus::class)->failPreparation($member->getKey());
        $this->assertSame(0, OutboundMessageWebhookEvent::count());
        $this->number->update(['inbound_delivery' => 'webhook', 'inbound_webhook_url' => 'https://custom.test/hook']);
        $custom = $this->submit();
        $this->assertSame('https://custom.test/hook', data_get($custom->delivery_meta, 'outbound.meta.api.endpoint_url'));
        $this->number->update(['inbound_webhook_url' => 'https://changed.test/hook']);
        app(ApiMessageStatus::class)->failPreparation($custom->getKey());
        $this->assertSame('https://custom.test/hook', OutboundMessageWebhookEvent::sole()->endpoint_url);
    }

    public function test_e164_sending_needs_no_account_country_and_checks_sender_ownership(): void
    {
        $phone = Mockery::mock(PhoneNumberService::class);
        $phone->shouldReceive('countryCodeForDomain')->andReturn('ZZ');
        $this->app->instance(PhoneNumberService::class, $phone);
        DB::table('user_domain_permission')->insert(['user_uuid' => self::USER, 'domain_uuid' => self::FOREIGN]);
        MessageSetting::create(['domain_uuid' => self::FOREIGN, 'destination' => '+442079460123', 'carrier' => 'sinch', 'enabled' => 'true']);
        $response = $this->postJson($this->url(domain: self::FOREIGN), $this->body(['from' => '+442079460123', 'to' => '+442079460124']))
            ->assertOk()->assertJsonPath('data.from.phone_number', '+442079460123')->assertJsonPath('data.to.0.phone_number', '+442079460124');
        $this->assertSame(self::FOREIGN, Messages::find($response->json('data.id'))->domain_uuid);
        $this->postJson($this->url(domain: self::FOREIGN), $this->body())->assertStatus(400)->assertJsonPath('error.param', 'from');
    }

    public function test_group_provider_limits_and_toll_free_restriction(): void
    {
        $numbers = array_map(fn ($i) => '+1202555'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), range(200, 220));
        $this->postJson($this->url(true), $this->body(['to' => $numbers]))->assertStatus(400);
        $this->number->update(['carrier' => 'bandwidth']);
        $this->postJson($this->url(true), $this->body(['to' => array_slice($numbers, 0, 10)]))->assertOk();
        $this->postJson($this->url(true), $this->body(['to' => array_slice($numbers, 0, 11)]))->assertStatus(400);
        $this->number->update(['destination' => '+18005550100']);
        $this->postJson($this->url(true), $this->body(['from' => '+18005550100', 'to' => array_slice($numbers, 0, 2)]))->assertStatus(400);
    }

    public function test_queue_handoff_failure_replays_the_same_persisted_submission(): void
    {
        $this->withHeader('Idempotency-Key', 'queue-failure');
        $fake = Bus::getFacadeRoot();
        Bus::swap(Mockery::mock(\Illuminate\Contracts\Bus\Dispatcher::class)->shouldReceive('dispatch')
            ->andThrow(new \RuntimeException('Queue unavailable'))->getMock());
        $this->postJson($this->url(), $this->body())->assertStatus(503)->assertJsonPath('error.code', 'queue_unavailable');
        $id = Messages::sole()->getKey();
        Bus::swap($fake);
        $this->postJson($this->url(), $this->body())->assertOk()->assertJsonPath('data.id', $id);
        $this->assertSame(1, Messages::count());
        Bus::assertDispatched(PrepareApiMessage::class, fn ($job) => $job->messageUuid === $id);
    }

    public function test_concurrent_idempotent_submissions_create_one_message(): void
    {
        if (!function_exists('pcntl_fork')) $this->markTestSkipped('pcntl required.');
        $this->withHeader('Idempotency-Key', 'concurrent');
        $paths = [];
        $children = [];
        DB::disconnect('outbound_test');
        for ($i = 0; $i < 2; $i++) {
            $path = tempnam(sys_get_temp_dir(), 'outbound-concurrency-');
            $paths[] = $path;
            $pid = pcntl_fork();
            if ($pid === 0) {
                try {
                    DB::purge('outbound_test');
                    $response = $this->postJson($this->url(), $this->body());
                    file_put_contents($path, json_encode(['status' => $response->status(), 'body' => $response->json()]));
                    DB::disconnect('outbound_test');
                    exit(0);
                } catch (\Throwable $e) {
                    file_put_contents($path, json_encode(['error' => $e->getMessage()]));
                    exit(1);
                }
            }
            if ($pid < 0) throw new \RuntimeException('Unable to fork test process.');
            $children[] = $pid;
        }
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status));
        }
        $responses = array_map(fn ($path) => json_decode(file_get_contents($path), true), $paths);
        foreach ($paths as $path) unlink($path);
        foreach ($responses as $response) $this->assertSame(200, $response['status'], json_encode($response));
        $this->assertSame($responses[0]['body']['data']['id'], $responses[1]['body']['data']['id']);
        $this->assertSame(1, Messages::count());
        $this->assertSame(1, MessageApiRequest::count());
    }

    public function test_signed_callbacks_have_stable_payloads_and_isolated_outcomes(): void
    {
        $account = app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://example.test/hook');
        $message = $this->submit();
        app(ApiMessageStatus::class)->failPreparation($message->getKey());
        $event = OutboundMessageWebhookEvent::sole();
        $delivery = app(\App\Services\Messaging\Outbound\OutboundMessageWebhookDelivery::class);
        $delivery->dispatch($event->getKey());
        $job = Bus::dispatched(\App\Jobs\CallWebhooks\SafeCallWebhookJob::class)->last();
        $this->assertSame($event->getKey(), $job->uuid);
        $this->assertSame('message.finalized', $job->payload['data']['event_type']);
        $body = json_encode($job->payload);
        $this->assertTrue(hash_equals(hash_hmac('sha256', $body, $account->signing_secret), $job->headers['Signature']));
        $this->assertFalse(hash_equals(hash_hmac('sha256', $body.' ', $account->signing_secret), $job->headers['Signature']));
        $this->assertStringEndsWith('-Message-Webhooks/1.0', $job->headers['User-Agent']);
        $this->assertContains('message-webhook', $job->tags);

        $history = [];
        $handler = \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(503), new \GuzzleHttp\Psr7\Response(503), new \GuzzleHttp\Psr7\Response(503),
        ]));
        $handler->push(\GuzzleHttp\Middleware::history($history));
        $this->app->instance(\GuzzleHttp\Client::class, new \GuzzleHttp\Client(['handler' => $handler]));
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $queue = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
            $queue->shouldReceive('attempts')->andReturn($attempt);
            $queue->shouldReceive('release')->zeroOrMoreTimes();
            $queue->shouldReceive('delete')->zeroOrMoreTimes();
            $job->setJob($queue);
            $job->handle();
        }
        $this->assertSame('failed', $event->fresh()->status);
        $this->assertSame(3, $event->fresh()->attempts);
        foreach ($history as $request) $this->assertSame($body, (string) $request['request']->getBody());
        $this->assertSame('sending_failed', app(ApiMessageStatus::class)->payload($message->fresh())['to'][0]['status']);
        $this->assertSame($body, json_encode($job->payload));
    }

    public function test_receipts_do_not_match_other_providers_or_ambiguous_accounts(): void
    {
        $message = $this->submit();
        app(ApiMessageService::class)->prepare($message->getKey());
        $status = app(ApiMessageStatus::class);
        $status->send($message->getKey(), $this->provider(fn () => new OutboundSendResultData(true, 'success', 'same-reference')));
        $this->assertFalse($status->receipt(['provider' => 'twilio', 'referenceId' => 'same-reference', 'status' => 'delivered']));
        $copy = $message->fresh()->replicate(); $copy->message_uuid = (string) Str::uuid();
        $copy->domain_uuid = self::FOREIGN; $copy->saveQuietly();
        $status->receipt(['provider' => 'sinch', 'referenceId' => 'same-reference', 'status' => 'delivered']);
        $this->assertSame('sent', $message->fresh()->status);
        $this->assertSame('sent', $copy->fresh()->status);
    }

    public function test_killed_send_worker_reports_failure_without_another_carrier_request(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://example.test/hook');
        $message = $this->submit();
        app(ApiMessageService::class)->prepare($message->getKey());
        $message->refresh(); $meta = $message->delivery_meta;
        data_set($meta, 'outbound.meta.api.attempt_started_at', now()->toIso8601String());
        $message->delivery_meta = $meta; $message->saveQuietly();
        (new SendOutboundSMSMessage($message->getKey()))->failed(new \RuntimeException('Worker killed'));
        $this->assertSame('sending_failed', app(ApiMessageStatus::class)->payload($message->fresh())['to'][0]['status']);
        $factory = Mockery::mock(OutboundProviderFactory::class); $factory->shouldNotReceive('make');
        app(ApiMessageStatus::class)->send($message->getKey(), $factory);
        $this->assertSame(1, OutboundMessageWebhookEvent::count());
    }

    public function test_interrupted_send_recovery_reports_a_failure(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://example.test/hook');
        $message = $this->submit();
        app(ApiMessageService::class)->prepare($message->getKey());
        $message->refresh(); $meta = $message->delivery_meta;
        data_set($meta, 'outbound.meta.api.attempt_started_at', now()->toIso8601String());
        $message->delivery_meta = $meta; $message->saveQuietly();
        $factory = Mockery::mock(OutboundProviderFactory::class); $factory->shouldNotReceive('make');
        app(ApiMessageStatus::class)->send($message->getKey(), $factory);
        $this->assertSame('failed', $message->fresh()->status);
        $this->assertSame('sending_failed', app(ApiMessageStatus::class)->payload($message->fresh())['to'][0]['status']);
        $this->assertSame('sending_failed', data_get(OutboundMessageWebhookEvent::sole()->payload, 'data.payload.to.0.status'));
    }

    public function test_media_failure_never_downgrades_to_sms_or_sends_a_partial_message(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://example.test/hook');
        $message = $this->submit(['media_urls' => ['https://example.test/a.jpg']]);
        $media = Mockery::mock(ApiMessageMedia::class);
        $media->shouldReceive('prepare')->once()->andThrow(new \App\Services\Messaging\Outbound\MediaPreparationException('Download failed'));
        $this->app->instance(ApiMessageMedia::class, $media);
        (new PrepareApiMessage($message->getKey()))->handle(app(ApiMessageService::class));
        Bus::assertNotDispatched(SendOutboundSMSMessage::class);
        $this->assertSame('mms', $message->fresh()->type);
        $this->assertSame('sending_failed', app(ApiMessageStatus::class)->payload($message->fresh())['to'][0]['status']);
    }

    public function test_media_only_preparation_reuses_storage_and_callbacks_have_frozen_day_long_links(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://example.test/hook');
        $message = $this->submit(['text' => null, 'media_urls' => ['https://files.example.test/photo.jpg']]);
        $item = ['bucket' => 'account-media', 'object_key' => 'photo', 'original_name' => 'photo.jpg',
            'stored_name' => 'stored.jpg', 'mime_type' => 'image/jpeg', 'size' => 5];
        $storage = Mockery::mock(MessageMediaObjectStorageService::class);
        $storage->shouldReceive('storeBinary')->once()->with(self::DOMAIN, 'image', 'photo.jpg', 'api', 'image/jpeg')->andReturn($item);
        $storage->shouldReceive('generateTemporaryDownloadUrl')->once()
            ->with(self::DOMAIN, 'account-media', 'photo', '+24 hours', 'image/jpeg', 'photo.jpg')
            ->andReturn('https://storage.example.test/photo?signed=day-long');
        $this->app->instance(MessageMediaObjectStorageService::class, $storage);
        $media = Mockery::mock(ApiMessageMedia::class, [app(PublicWebhookUrlValidator::class), $storage])->makePartial();
        $media->shouldReceive('download')->once()->with('https://files.example.test/photo.jpg', 50 * 1024 * 1024, Mockery::on(fn ($s) => $s > 0 && $s <= 60))
            ->andReturn(['binary' => 'image', 'name' => 'photo.jpg', 'mime' => 'image/jpeg']);
        $this->app->instance(ApiMessageMedia::class, $media);
        app(ApiMessageService::class)->prepare($message->getKey());
        app(ApiMessageService::class)->prepare($message->getKey());
        $this->assertSame(5, data_get($message->fresh()->delivery_meta, 'outbound.meta.api.downloaded_bytes'));
        $this->assertStringContainsString($message->getKey(), $message->fresh()->media[0]['access_path']);
        $status = app(ApiMessageStatus::class);
        $status->send($message->getKey(), $this->provider(function ($prepared) {
            $this->assertSame('mms', $prepared->type);
            $this->assertCount(1, $prepared->media);
            return new OutboundSendResultData(true, 'success', 'media-reference');
        }));
        $event = OutboundMessageWebhookEvent::sole();
        $delivery = app(\App\Services\Messaging\Outbound\OutboundMessageWebhookDelivery::class);
        $delivery->dispatch($event->getKey());
        $payload = $event->fresh()->payload;
        $this->assertSame('https://storage.example.test/photo?signed=day-long', data_get($payload, 'data.payload.media.0.url'));
        // A queue handoff retry reuses the already prepared payload and URLs.
        $event->update(['status' => 'pending']);
        $delivery->dispatch($event->getKey());
        $this->assertSame($payload, $event->fresh()->payload);
        $this->assertFalse(app(\App\Services\Messaging\RingotelSyncDispatcher::class)->dispatch($message->fresh()));
        Event::assertNotDispatched(MessageSent::class);
        Event::assertNotDispatched(ConversationUpdated::class);
    }

    public function test_partial_attachment_preparation_enforces_total_budget_on_retry(): void
    {
        $message = $this->submit(['media_urls' => ['https://files.example.test/a.jpg', 'https://files.example.test/b.jpg']]);
        $message->media = [['bucket' => 'account-media', 'object_key' => 'a', 'size' => 4]];
        $meta = $message->delivery_meta;
        data_set($meta, 'outbound.meta.api.downloaded_bytes', 4);
        $message->delivery_meta = $meta;
        $message->saveQuietly();
        config(['messaging.api.media_max_bytes' => 6]);
        $storage = Mockery::mock(MessageMediaObjectStorageService::class);
        $storage->shouldNotReceive('storeBinary');
        $media = Mockery::mock(ApiMessageMedia::class, [app(PublicWebhookUrlValidator::class), $storage])->makePartial();
        $media->shouldReceive('download')->once()->with('https://files.example.test/b.jpg', 2, Mockery::any())
            ->andThrow(new \App\Services\Messaging\Outbound\MediaPreparationException('Too large'));
        $this->app->instance(ApiMessageMedia::class, $media);
        (new PrepareApiMessage($message->getKey()))->handle(app(ApiMessageService::class));
        $this->assertCount(1, $message->fresh()->media);
        Bus::assertNotDispatched(SendOutboundSMSMessage::class);
        $this->assertSame('sending_failed', app(ApiMessageStatus::class)->payload($message->fresh())['to'][0]['status']);
    }

    public function test_sinch_parser_tracks_individual_group_receipts_and_preserves_unknown_states(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://example.test/hook');
        $message = $this->submit(['to' => [self::REMOTE, '+12025550102']], true);
        app(ApiMessageService::class)->prepare($message->getKey());
        $status = app(ApiMessageStatus::class);
        $status->send($message->getKey(), $this->provider(fn () => new OutboundSendResultData(true, 'success', 'group-reference')));
        foreach ([['12025550101', 'accepted'], ['12025550101', 'unexpected'], ['12025550101', 'delivered'], ['12025550102', 'failed']] as [$recipient, $state]) {
            $call = new \Spatie\WebhookClient\Models\WebhookCall;
            $call->payload = ['referenceId' => 'group-reference', 'deliveryReceipt' => true, 'status' => $state,
                'to' => [$recipient], 'time' => now()->toIso8601String()];
            $receipt = iterator_to_array(app(\App\Services\Messaging\Providers\SinchWebhookParser::class)->parse($call))[0];
            app(\App\Services\Messaging\MessageRepository::class)->applyProviderStatus(
                $receipt->provider, $receipt->referenceId, $receipt->status, recipient: $receipt->recipient,
                occurredAt: $receipt->occurredAt, rawStatus: $receipt->rawStatus);
            if ($state !== 'failed') $this->assertSame(0, OutboundMessageWebhookEvent::where('event_type', 'message.finalized')->count());
        }
        $this->assertSame(['delivered', 'delivery_failed'], array_column($status->payload($message->fresh())['to'], 'status'));
        $this->assertSame(1, OutboundMessageWebhookEvent::where('event_type', 'message.finalized')->count());
    }

    public function test_portal_sending_and_matching_provider_receipts_still_work(): void
    {
        $participants = Mockery::mock(\App\Services\Messaging\MessageParticipantService::class);
        $participants->shouldReceive('sender')->andReturn('Portal user');
        $participants->shouldReceive('members')->andReturn(collect());
        $this->app->instance(\App\Services\Messaging\MessageParticipantService::class, $participants);
        $message = app(\App\Services\Messaging\Outbound\CreateOutboundMessageService::class)->create(
            new \App\Services\Messaging\Outbound\Data\CreateOutboundMessageData(
                domainUuid: self::DOMAIN, extensionUuid: null, source: self::LOCAL, destination: self::REMOTE,
                message: 'Portal message', origin: 'portal', carrier: 'sinch',
            )
        );
        Bus::assertDispatched(SendOutboundSMSMessage::class);
        $this->assertFalse($message->isExternalOnly());
        $this->assertSame(1, Messages::forInbox()->count());
        $message->reference_id = 'portal-reference'; $message->saveQuietly();
        Messages::withoutEvents(fn () => app(\App\Services\Messaging\MessageRepository::class)
            ->applyProviderStatus('sinch', 'portal-reference', 'delivered'));
        $this->assertSame('delivered', $message->fresh()->status);
        $this->assertSame(0, OutboundMessageWebhookEvent::count());
    }

    public function test_administrative_retry_preserves_failed_outcomes_and_uses_preparation(): void
    {
        $message = $this->submit();
        app(ApiMessageService::class)->prepare($message->getKey());
        app(ApiMessageStatus::class)->send($message->getKey(), $this->provider(fn () => new OutboundSendResultData(false, 'failed')));
        Bus::fake();
        app(\App\Services\Messaging\RetryMessageService::class)->retryOne($message->fresh());
        Bus::assertDispatched(PrepareApiMessage::class, fn ($job) => $job->messageUuid === $message->getKey());
        Bus::assertNotDispatched(SendOutboundSMSMessage::class);
        $this->assertSame('failed', $message->fresh()->status);
        $this->assertSame('sending_failed', app(ApiMessageStatus::class)->payload($message->fresh())['to'][0]['status']);
    }

    public function test_twilio_queued_receipt_does_not_finalize_and_failed_is_sending_failed(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://example.test/hook');
        $message = $this->submit();
        app(ApiMessageService::class)->prepare($message->getKey());
        app(ApiMessageStatus::class)->send($message->getKey(), $this->provider(fn () => new OutboundSendResultData(true, 'success', 'twilio-reference')));
        $message->refresh(); $meta = $message->delivery_meta;
        data_set($meta, 'outbound.provider.name', 'twilio');
        $message->delivery_meta = $meta; $message->saveQuietly();
        foreach (['queued', 'sent', 'failed'] as $state) {
            $call = new \Spatie\WebhookClient\Models\WebhookCall;
            $call->payload = ['MessageSid' => 'twilio-reference', 'MessageStatus' => $state];
            $receipt = iterator_to_array(app(\App\Services\Messaging\Providers\TwilioWebhookParser::class)->parse($call))[0];
            app(\App\Services\Messaging\MessageRepository::class)->applyProviderStatus($receipt->provider, $receipt->referenceId, $receipt->status, rawStatus: $receipt->rawStatus);
            if ($state !== 'failed') {
                $this->assertSame('sent', $message->fresh()->status);
                $this->assertSame(0, OutboundMessageWebhookEvent::where('event_type', 'message.finalized')->count());
            }
        }
        $this->assertSame('sending_failed', app(ApiMessageStatus::class)->payload($message->fresh())['to'][0]['status']);
        $this->assertSame(1, OutboundMessageWebhookEvent::where('event_type', 'message.finalized')->count());
    }

    public function test_idempotency_is_scoped_to_account_user_and_endpoint(): void
    {
        $this->withHeader('Idempotency-Key', 'same-key');
        $first = $this->submit();
        $group = $this->submit(['to' => [self::REMOTE, '+12025550102']], true);
        $secondUser = '55555555-5555-4555-8555-555555555555';
        DB::table('v_user_groups')->insert(['user_uuid' => $secondUser, 'group_uuid' => self::ROLE]);
        Sanctum::actingAs((new User())->forceFill(['user_uuid' => $secondUser, 'domain_uuid' => self::DOMAIN]));
        $otherUser = $this->submit();
        DB::table('user_domain_permission')->insert(['user_uuid' => $secondUser, 'domain_uuid' => self::FOREIGN]);
        MessageSetting::create(['domain_uuid' => self::FOREIGN, 'destination' => self::LOCAL, 'carrier' => 'sinch', 'enabled' => 'true']);
        $otherAccount = $this->postJson($this->url(domain: self::FOREIGN), $this->body())->assertOk()->json('data.id');
        $this->assertCount(4, array_unique([$first->getKey(), $group->getKey(), $otherUser->getKey(), $otherAccount]));
        $this->assertSame(4, MessageApiRequest::count());
    }

    public function test_public_attachment_validation_rejects_private_hosts_before_acceptance(): void
    {
        $this->app->instance(PublicWebhookUrlValidator::class, new PublicWebhookUrlValidator);
        foreach (['127.0.0.1', '169.254.169.254', '100.64.0.1'] as $host) {
            $this->postJson($this->url(), $this->body(['media_urls' => ['https://'.$host.'/file']]))
                ->assertStatus(400)->assertJsonPath('error.param', 'media_urls');
        }
        $this->assertSame(0, Messages::count());
        Bus::assertNotDispatched(PrepareApiMessage::class);
    }

    public function test_callback_results_are_tenant_scoped_and_success_cannot_be_downgraded(): void
    {
        app(MessageWebhookSettings::class)->save(self::DOMAIN, 'https://example.test/hook');
        $message = $this->submit();
        app(ApiMessageStatus::class)->failPreparation($message->getKey());
        $event = OutboundMessageWebhookEvent::sole();
        app(\App\Services\Messaging\Outbound\OutboundMessageWebhookDelivery::class)->dispatch($event->getKey());
        $job = Bus::dispatched(\App\Jobs\CallWebhooks\SafeCallWebhookJob::class)->last();
        $result = new \Spatie\WebhookServer\Events\WebhookCallSucceededEvent('POST', $job->webhookUrl, $job->payload,
            $job->headers, array_replace($job->meta, ['domain_uuid' => self::FOREIGN]), $job->tags,
            1, new \GuzzleHttp\Psr7\Response(204), null, null, $job->uuid, null);
        $listener = app(\App\Listeners\RecordMessageWebhookResult::class);
        $listener->handle($result);
        $this->assertSame('queued', $event->fresh()->status);
        $result->meta = $job->meta;
        $listener->handle($result);
        $this->assertSame('delivered', $event->fresh()->status);
        $failure = new \Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent('POST', $job->webhookUrl, $job->payload,
            $job->headers, $job->meta, $job->tags, 3, new \GuzzleHttp\Psr7\Response(503), null, null, $job->uuid, null);
        $listener->handle($failure);
        $this->assertSame('delivered', $event->fresh()->status);
        $this->assertSame('sending_failed', app(ApiMessageStatus::class)->payload($message->fresh())['to'][0]['status']);
    }
}
