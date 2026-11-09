<?php

namespace Tests\Feature;

use App\Http\Controllers\OutboundWebhookLogsController;
use App\Listeners\RecordOutboundWebhookHistory;
use App\Models\Messages;
use App\Models\OutboundWebhookLog;
use App\Services\OutboundWebhookHistory;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\WebhookServer\Events\DispatchingWebhookCallEvent;
use Spatie\WebhookServer\Events\FinalWebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallFailedEvent;
use Spatie\WebhookServer\Events\WebhookCallSucceededEvent;
use Spatie\WebhookServer\WebhookCall;
use Tests\TestCase;

class OutboundWebhookHistoryTest extends TestCase
{
    private const DOMAIN = '11111111-1111-4111-8111-111111111111';
    private const OTHER = '22222222-2222-4222-8222-222222222222';
    private string $eventId;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'outbound_history_test',
            'database.connections.outbound_history_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array',
        ]);
        DB::purge('outbound_history_test');
        (require base_path('database/migrations/2026_11_09_000001_create_outbound_messaging_tables.php'))->up();
        Schema::create('v_domains', function (Blueprint $table) {
            $table->uuid('domain_uuid')->primary();
            $table->string('domain_name');
            $table->string('domain_description')->nullable();
        });
        DB::table('v_domains')->insert([
            ['domain_uuid' => self::DOMAIN, 'domain_name' => 'first.test'],
            ['domain_uuid' => self::OTHER, 'domain_name' => 'other.test'],
        ]);
        session([
            'domain_uuid' => self::DOMAIN,
            'domains' => [(object) ['domain_uuid' => self::DOMAIN]],
            'permissions' => [(object) ['permission_name' => 'logs_list_view']],
        ]);
        Route::middleware(\Illuminate\Session\Middleware\StartSession::class)
            ->get('/test-outbound-webhooks', [OutboundWebhookLogsController::class, 'index']);
        $this->eventId = (string) Str::uuid();
        Bus::fake();
        Log::spy()->shouldReceive('channel')->andReturnSelf();
    }

    protected function tearDown(): void
    {
        DB::purge('outbound_history_test');
        parent::tearDown();
    }

    private function record(string $class, int $attempt = 1, ?int $http = 200, array $meta = [], ?string $url = null): void
    {
        app(OutboundWebhookHistory::class)->record(new $class(
            'POST', $url ?? 'https://receiver.test/hook',
            ['data' => ['event_type' => 'message.sent', 'payload' => ['text' => 'private message']]],
            ['Signature' => 'private-signature'], $meta + ['domain_uuid' => self::DOMAIN], [],
            $attempt, $http ? new Response($http) : null, null,
            'Sensitive provider error containing private-token', $this->eventId, null,
        ));
    }

    public function test_spatie_dispatch_is_logged_without_sending_or_storing_secrets(): void
    {
        WebhookCall::create()->url('https://user:password@receiver.test/hook?token=private-token#secret')
            ->payload(['type' => 'call.test', 'secret' => 'private-payload'])->useSecret('private-signature')
            ->meta(['domain_uuid' => self::DOMAIN])->uuid($this->eventId)->dispatch();
        $row = OutboundWebhookLog::sole();
        $this->assertSame('pending', $row->status);
        $this->assertSame('call.test', $row->event_type);
        $this->assertSame('https://receiver.test/hook?[redacted]', $row->endpoint_url);
        $this->assertSame(0, $row->attempts);
        $this->assertStringNotContainsString('private-', $row->toJson());
        $this->assertStringNotContainsString('password', $row->toJson());
        Bus::assertDispatched(\App\Jobs\CallWebhooks\SafeCallWebhookJob::class);
    }

    public function test_retries_keep_attempt_history_and_success_cannot_regress(): void
    {
        $this->record(WebhookCallFailedEvent::class, 1, 503);
        $this->record(WebhookCallSucceededEvent::class, 2, 204);
        $this->record(FinalWebhookCallFailedEvent::class, 1, 503);
        $this->record(WebhookCallFailedEvent::class, 3, null);
        $row = OutboundWebhookLog::sole();
        $this->assertSame('delivered', $row->status);
        $this->assertSame(2, $row->attempts);
        $this->assertSame(204, $row->http_status);
        $this->assertCount(2, $row->attempt_history);
        $this->assertSame(503, $row->attempt_history[0]['http_status']);
        $this->assertNull($row->error);
        $this->assertStringNotContainsString('private-', $row->toJson());
    }

    public function test_final_failure_updates_the_same_attempt_without_duplicates(): void
    {
        $this->record(WebhookCallFailedEvent::class, 3, null);
        $this->record(FinalWebhookCallFailedEvent::class, 3, null);
        $this->record(WebhookCallFailedEvent::class, 3, null);
        $row = OutboundWebhookLog::sole();
        $this->assertSame('failed', $row->status);
        $this->assertSame('failed', $row->attempt_history[0]['status']);
        $this->assertCount(1, $row->attempt_history);
        $this->assertSame('No HTTP response received.', $row->error);
    }

    public function test_same_event_to_different_accounts_or_endpoints_stays_separate(): void
    {
        $this->record(WebhookCallSucceededEvent::class);
        $this->record(WebhookCallSucceededEvent::class, meta: ['domain_uuid' => self::OTHER]);
        $this->record(WebhookCallSucceededEvent::class, url: 'https://second.test/hook');
        $this->assertSame(3, OutboundWebhookLog::count());
    }

    public function test_system_and_future_webhook_types_are_recorded_without_module_dependencies(): void
    {
        $this->record(WebhookCallSucceededEvent::class, meta: ['domain_uuid' => null, 'event_type' => 'contact_center.synchronize']);
        $row = OutboundWebhookLog::sole();
        $this->assertNull($row->domain_uuid);
        $this->assertSame('contact_center.synchronize', $row->event_type);
        $this->assertSame('delivered', $row->status);
    }

    public function test_logging_failures_do_not_fail_webhook_delivery(): void
    {
        $this->mock(OutboundWebhookHistory::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('private-error'));
        app(RecordOutboundWebhookHistory::class)->handle(new DispatchingWebhookCallEvent('POST',
            'https://receiver.test', [], [], [], [], $this->eventId));
        Log::shouldHaveReceived('warning')->once()->with('Unable to record outbound webhook history.',
            ['event_id' => $this->eventId, 'error_type' => \RuntimeException::class]);
    }

    public function test_history_without_migration_does_not_interrupt_webhooks(): void
    {
        Schema::drop('outbound_webhook_logs');
        $this->record(WebhookCallSucceededEvent::class);
        $this->assertFalse(Schema::hasTable('outbound_webhook_logs'));
    }

    private function url(array $filters = []): string
    {
        return '/test-outbound-webhooks?'.http_build_query(['filter' => $filters + [
            'dateRange' => [now()->subDay()->toIso8601String(), now()->addDay()->toIso8601String()],
        ]]);
    }

    public function test_history_requires_permission_and_filters_to_accessible_accounts(): void
    {
        $this->record(WebhookCallSucceededEvent::class);
        $this->record(WebhookCallSucceededEvent::class, meta: ['domain_uuid' => self::OTHER]);
        $this->record(WebhookCallSucceededEvent::class, meta: ['domain_uuid' => null, 'event_type' => 'system.test']);
        $this->getJson($this->url())->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.domain_uuid', self::DOMAIN);
        $this->getJson($this->url(['domain_uuid' => 'all']))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->url(['domain_uuid' => self::OTHER]))->assertForbidden();
        session(['permissions' => []]);
        $this->getJson($this->url())->assertForbidden();
    }

    public function test_superadmins_can_include_system_webhooks_in_all_accounts(): void
    {
        session(['user.groups' => [(object) ['group_name' => 'superadmin', 'group_level' => 80]]]);
        $this->record(WebhookCallSucceededEvent::class);
        $this->record(WebhookCallSucceededEvent::class, meta: ['domain_uuid' => null, 'event_type' => 'system.test']);
        $this->getJson($this->url(['domain_uuid' => 'all']))->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_search_status_dates_and_validation(): void
    {
        $this->record(WebhookCallSucceededEvent::class);
        $this->getJson($this->url(['search' => 'MESSAGE.SENT', 'status' => 'delivered']))
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->url(['search' => $this->eventId]))->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($this->url(['search' => 'message,sent']))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->url(['status' => 'failed']))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->url(['dateRange' => [now()->subDays(3)->toIso8601String(), now()->subDays(2)->toIso8601String()]]))
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->url(['status' => 'unknown']))->assertUnprocessable();
        $this->getJson($this->url(['timezone' => 'UTC']))->assertUnprocessable();
        $this->getJson($this->url(['dateRange' => ['bad', 'dates']]))->assertUnprocessable();
    }

    public function test_message_status_excludes_all_notification_delivery_states(): void
    {
        $controller = new class extends \App\Http\Controllers\MessageController {
            public function summary(Messages $message, array $meta): string { return $this->buildStatusSummary($message, $meta); }
        };
        $message = new Messages(['status' => 'sent']);
        $meta = ['outbound' => ['provider' => ['name' => 'voxutel', 'status' => 'sent'],
            'webhooks' => [['event_type' => 'message.sent', 'status' => 'delivered']]],
            'webhook' => ['status' => 'delivered'], 'email' => ['status' => 'sent'], 'ringotel' => ['status' => 'failed']];
        $this->assertSame('voxutel: sent', $controller->summary($message, $meta));
        $this->assertSame('sent', $controller->summary($message, []));
    }
}
