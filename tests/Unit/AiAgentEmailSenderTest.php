<?php

namespace Tests\Unit;

use App\Http\Requests\StoreAiAgentRequest;
use App\Http\Requests\UpdateAiAgentRequest;
use App\Jobs\SendAiToolEmail;
use App\Mail\AiAgentToolEmail;
use App\Models\AiAgent;
use App\Services\AiTools\AiSendEmailToolService;
use App\Services\EmailTemplateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Tests\TestCase;

class AiAgentEmailSenderTest extends TestCase
{
    private const DOMAIN_UUID = '11111111-1111-4111-8111-111111111111';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'ai_email_test',
            'database.connections.ai_email_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
            'cache.default' => 'array',
            'mail.from.address' => 'config@example.com',
            'mail.from.name' => 'Configured Sender',
        ]);
        DB::purge('ai_email_test');

        foreach (['default', 'domain'] as $scope) {
            Schema::create("v_{$scope}_settings", function (Blueprint $table) use ($scope) {
                $table->uuid('domain_uuid')->nullable();
                $table->string("{$scope}_setting_category");
                $table->string("{$scope}_setting_subcategory");
                $table->string("{$scope}_setting_value");
                $table->boolean("{$scope}_setting_enabled")->default(true);
            });
        }

        $this->mock(EmailTemplateService::class)
            ->shouldReceive('render')
            ->andReturn(['available' => false]);
    }

    protected function tearDown(): void
    {
        DB::purge('ai_email_test');

        parent::tearDown();
    }

    /** @dataProvider senderCases */
    public function test_agent_address_takes_precedence_with_existing_fallbacks(
        ?string $override,
        ?string $domainAddress,
        ?string $systemAddress,
        string $expected,
    ): void {
        if ($domainAddress !== null) {
            $this->setting('domain', 'smtp_from', $domainAddress);
        }
        if ($systemAddress !== null) {
            $this->setting('default', 'smtp_from', $systemAddress);
        }
        $this->setting('domain', 'smtp_from_name', 'Account Name');
        $this->setting('default', 'smtp_from_name', 'System Name');

        $email = new AiAgentToolEmail([
            'domain_uuid' => self::DOMAIN_UUID,
            'from_email' => $override,
        ]);

        $this->assertSame($expected, $email->envelope()->from->address);
        $this->assertSame('Account Name', $email->envelope()->from->name);
    }

    public static function senderCases(): array
    {
        return [
            'agent override' => ['agent@example.com', 'account@example.com', 'system@example.com', 'agent@example.com'],
            'account fallback' => [null, 'account@example.com', 'system@example.com', 'account@example.com'],
            'system fallback' => [null, null, 'system@example.com', 'system@example.com'],
            'mail config fallback' => [null, null, null, 'config@example.com'],
        ];
    }

    public function test_fallback_ignores_other_accounts_and_disabled_settings(): void
    {
        $this->setting('domain', 'smtp_from', 'other@example.com');
        DB::table('v_domain_settings')->update(['domain_uuid' => '22222222-2222-4222-8222-222222222222']);
        $this->setting('domain', 'smtp_from', 'disabled@example.com', false);
        $this->setting('default', 'smtp_from', 'system@example.com');

        $email = new AiAgentToolEmail(['domain_uuid' => self::DOMAIN_UUID]);

        $this->assertSame('system@example.com', $email->envelope()->from->address);
    }

    /** @dataProvider addressCases */
    public function test_create_and_update_validate_the_optional_sender(mixed $address, bool $valid): void
    {
        foreach ([new StoreAiAgentRequest(), new UpdateAiAgentRequest()] as $request) {
            $validator = Validator::make(
                ['email_from_address' => $address],
                ['email_from_address' => $request->rules()['email_from_address']],
            );

            $this->assertSame($valid, $validator->passes(), get_class($request));
        }
    }

    public static function addressCases(): array
    {
        return [
            'address' => ['reception@example.com', true],
            'cleared' => [null, true],
            'empty' => ['', true],
            'invalid' => ['not-an-email', false],
            'multiple addresses' => ['first@example.com,second@example.com', false],
            'header injection' => ["first@example.com\r\nBcc: second@example.com", false],
            'array' => [['reception@example.com'], false],
        ];
    }

    /** @dataProvider queuedSenderCases */
    public function test_queued_email_uses_the_saved_agent_sender_and_survives_serialization(?string $override): void
    {
        $migration = require base_path('database/migrations/2026_08_11_000001_create_ai_agents_tables.php');
        $migration->up();
        $this->setting('default', 'smtp_from', 'system@example.com');
        Bus::fake();
        Mail::fake();

        $limiter = Mockery::mock();
        Redis::shouldReceive('throttle')->once()->with('emails')->andReturn($limiter);
        $limiter->shouldReceive('allow')->once()->with(2)->andReturnSelf();
        $limiter->shouldReceive('every')->once()->with(1)->andReturnSelf();
        $limiter->shouldReceive('then')->once()->andReturnUsing(fn ($success, $failure) => $success());

        $agent = new AiAgent([
            'ai_agent_uuid' => '33333333-3333-4333-8333-333333333333',
            'domain_uuid' => self::DOMAIN_UUID,
            'provider' => 'retell',
            'email_from_address' => $override,
        ]);
        $result = app(AiSendEmailToolService::class)->queue($agent, 'call_sender_test', [
            'recipient' => 'team@example.com',
            'subject' => 'Caller follow-up',
            'fields' => [['label' => 'Name', 'value' => 'Jordan']],
            'from_email' => 'untrusted@example.com',
            'email_from_address' => 'untrusted@example.com',
        ]);

        $agent->email_from_address = 'changed-after-queueing@example.com';
        Bus::assertDispatched(SendAiToolEmail::class, function (SendAiToolEmail $job) {
            // A null override is omitted by SerializesModels, matching older queued jobs.
            unserialize(serialize($job))->handle();

            return true;
        });
        Mail::assertSent(AiAgentToolEmail::class, function (AiAgentToolEmail $email) use ($override) {
            return $email->hasTo('team@example.com')
                && $email->envelope()->from->address === ($override ?? 'system@example.com');
        });
        $this->assertSame('sent', $result['invocation']->refresh()->status);
    }

    public static function queuedSenderCases(): array
    {
        return [
            'agent override' => ['agent@example.com'],
            'legacy job without override' => [null],
        ];
    }

    private function setting(string $scope, string $subcategory, string $value, bool $enabled = true): void
    {
        DB::table("v_{$scope}_settings")->insert([
            'domain_uuid' => $scope === 'domain' ? self::DOMAIN_UUID : null,
            "{$scope}_setting_category" => 'email',
            "{$scope}_setting_subcategory" => $subcategory,
            "{$scope}_setting_value" => $value,
            "{$scope}_setting_enabled" => $enabled,
        ]);
    }
}
