<?php

namespace Tests\Feature;

use App\Http\Webhooks\Jobs\ProcessPostmarkWebhookJob;
use App\Http\Webhooks\WebhookProfiles\PostmarkWebhookProfile;
use App\Models\EmailLog;
use App\Models\WhCall;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\WebhookClient\WebhookConfigRepository;
use Tests\TestCase;

class PostmarkWebhookAuthenticationTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        config([
            'database.default' => 'postmark_auth_test',
            'database.connections.postmark_auth_test' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            ],
            'services.postmark.webhook_username' => 'postmark-test',
            'services.postmark.webhook_password' => 'test-password:with-colon',
            'logging.default' => 'null',
            'app.debug' => false,
        ]);
        DB::purge('postmark_auth_test');
        Bus::fake();
        $this->withoutMiddleware();

        Schema::create('webhook_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('url');
            $table->text('headers')->nullable();
            $table->text('payload');
            $table->text('exception')->nullable();
            $table->timestamps();
        });
        Schema::create('email_log', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('status');
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('provider_message_stream')->nullable();
            $table->text('sent_debug_info')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('postmark_auth_test');
        config(['database.default' => $this->originalConnection]);
        parent::tearDown();
    }

    /** @dataProvider rejectedCredentials */
    public function test_invalid_credentials_are_rejected_before_any_event_processing(
        ?string $authorization,
        ?string $configuredUsername = 'postmark-test',
        ?string $configuredPassword = 'test-password:with-colon',
    ): void {
        config([
            'services.postmark.webhook_username' => $configuredUsername,
            'services.postmark.webhook_password' => $configuredPassword,
        ]);

        $profile = $this->mock(PostmarkWebhookProfile::class);
        $profile->shouldNotReceive('shouldProcess');
        app(WebhookConfigRepository::class)->getConfig('postmark')->webhookProfile = $profile;

        $log = EmailLog::create(['status' => 'sent']);
        $headers = $authorization === null ? [] : ['Authorization' => $authorization];
        $response = $this->postJson('/webhook/postmark', $this->bounce($log), $headers);

        $response->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Basic realm="Postmark webhook"');
        $this->assertSame('sent', $log->refresh()->status);
        $this->assertSame(0, WhCall::count());
        Bus::assertNothingDispatched();
        $this->assertStringNotContainsString('test-password', $response->getContent());
    }

    public static function rejectedCredentials(): array
    {
        $basic = fn ($user, $password) => 'Basic '.base64_encode($user.':'.$password);

        return [
            'missing header' => [null],
            'empty header' => [''],
            'wrong username' => [$basic('wrong', 'test-password:with-colon')],
            'wrong password' => [$basic('postmark-test', 'wrong')],
            'missing username' => [$basic('', 'test-password:with-colon')],
            'missing password' => [$basic('postmark-test', '')],
            'bearer instead of basic' => ['Bearer test-password:with-colon'],
            'malformed basic' => ['Basic !!!'],
            'no password separator' => ['Basic '.base64_encode('postmark-test')],
            'username not configured' => [$basic('postmark-test', 'test-password:with-colon'), null],
            'password not configured' => [$basic('postmark-test', 'test-password:with-colon'), 'postmark-test', null],
            'blank configuration and request' => [$basic('', ''), '', ''],
        ];
    }

    public function test_authenticated_bounce_is_queued_and_updates_the_matching_email(): void
    {
        $log = EmailLog::create(['status' => 'sent']);
        $payload = $this->bounce($log);

        $this->postJson('/webhook/postmark', $payload, $this->credentials())->assertOk();

        $call = WhCall::query()->sole();
        $this->assertSame([], $call->headers);
        $this->assertSame($payload, $call->payload);
        $this->assertStringNotContainsString('test-password', $call->getRawOriginal('payload'));
        $this->assertStringNotContainsString('postmark-test', $call->url);
        Bus::assertDispatched(ProcessPostmarkWebhookJob::class, function ($job) use ($call) {
            return $job->queue === 'emails' && $job->webhookCall->id === $call->id;
        });

        (new ProcessPostmarkWebhookJob($call))->handle();

        $this->assertSame('permanent_failed', $log->refresh()->status);
        $this->assertSame($payload['MessageID'], $log->provider_message_id);
    }

    public function test_inbound_fax_cannot_bypass_authentication(): void
    {
        $profile = $this->mock(PostmarkWebhookProfile::class);
        $profile->shouldNotReceive('shouldProcess');
        app(WebhookConfigRepository::class)->getConfig('postmark')->webhookProfile = $profile;

        $this->postJson('/webhook/postmark', [
            'RecordType' => 'Inbound',
            'FromFull' => ['Email' => 'sender@example.test'],
            'ToFull' => [['Email' => '12025550123@fax.example.test']],
        ])->assertUnauthorized();

        $this->assertSame(0, WhCall::count());
        Bus::assertNothingDispatched();
    }

    public function test_authenticated_inbound_fax_reaches_its_existing_profile_and_queue(): void
    {
        // Sender authorization/attachment handling belongs to the existing fax profile.
        $profile = $this->mock(PostmarkWebhookProfile::class);
        $profile->shouldReceive('shouldProcess')->once()->andReturnTrue();
        app(WebhookConfigRepository::class)->getConfig('postmark')->webhookProfile = $profile;

        $this->postJson('/webhook/postmark', [
            'RecordType' => 'Inbound', 'MessageID' => (string) Str::uuid(),
        ], $this->credentials())->assertOk();

        $this->assertSame(1, WhCall::count());
        Bus::assertDispatched(ProcessPostmarkWebhookJob::class, fn ($job) => $job->queue === 'faxes');
    }

    private function credentials(): array
    {
        return ['Authorization' => 'Basic '.base64_encode('postmark-test:test-password:with-colon')];
    }

    private function bounce(EmailLog $log): array
    {
        return [
            'RecordType' => 'Bounce', 'Type' => 'HardBounce',
            'MessageID' => (string) Str::uuid(), 'MessageStream' => 'outbound',
            'Metadata' => ['email_log_uuid' => $log->uuid],
            'Description' => 'Mailbox does not exist.',
        ];
    }
}
