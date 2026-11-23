<?php

namespace Tests\Feature\Api\V1;

use App\Events\ExtensionUpdated;
use App\Http\Controllers\Api\V1\ExtensionController;
use App\Jobs\UpdateAppSettings;
use App\Models\Extensions;
use App\Models\FusionCache;
use App\Models\User;
use App\Services\ExtensionPasswordService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Knuckles\Scribe\Extracting\Extractor;
use ReflectionProperty;
use Tests\TestCase;

class ExtensionSipCredentialsTest extends TestCase
{
    private const DOMAIN = '11111111-1111-4111-8111-111111111111';
    private const FOREIGN = '22222222-2222-4222-8222-222222222222';
    private const EXTENSION = '33333333-3333-4333-8333-333333333333';
    private const FOREIGN_EXTENSION = '44444444-4444-4444-8444-444444444444';
    private const ACTOR = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const ROLE = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private string $cacheDirectory;
    private array $previousCache = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sip_credentials_test',
            'database.connections.sip_credentials_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array',
            'data.structure_caching.cache.store' => 'array',
            'activitylog.enabled' => false,
        ]);
        DB::purge('sip_credentials_test');
        $limiter = new RateLimiter(Cache::store('array'));
        $limiter->for('api', app(RateLimiter::class)->limiter('api'));
        $this->app->instance(RateLimiter::class, $limiter);
        Event::fake([ExtensionUpdated::class]);
        Queue::fake();
        Http::preventStrayRequests();
        Log::spy()->shouldReceive('channel')->andReturnSelf();

        foreach ([
            'v_domains' => ['domain_uuid', 'domain_name'],
            'v_users' => ['user_uuid', 'domain_uuid', 'username', 'user_enabled'],
            'users_adv_fields' => ['id', 'user_uuid', 'first_name', 'last_name'],
            'v_user_settings' => ['user_setting_uuid', 'user_uuid'],
            'v_user_groups' => ['user_group_uuid', 'user_uuid', 'domain_uuid', 'group_uuid'],
            'v_group_permissions' => ['group_uuid', 'permission_name', 'permission_assigned'],
            'user_domain_permission' => ['user_uuid', 'domain_uuid'],
            'user_domain_group_permissions' => ['user_uuid', 'domain_group_uuid'],
            'domain_group_relations' => ['domain_group_uuid', 'domain_uuid'],
            'v_extensions' => ['extension_uuid', 'domain_uuid', 'extension', 'password', 'user_context'],
            'extension_advanced_settings' => ['setting_uuid', 'extension_uuid', 'suspended'],
            'v_voicemails' => ['voicemail_uuid', 'domain_uuid', 'voicemail_id', 'voicemail_mail_to'],
            'v_device_lines' => ['device_line_uuid', 'domain_uuid', 'auth_id', 'password', 'external_line'],
            'mobile_app_users' => ['mobile_app_user_uuid', 'domain_uuid', 'extension_uuid', 'org_id', 'user_id', 'status'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
            });
        }
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->string('tokenable_id');
            $table->string('name');
            $table->string('token', 64);
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        DB::table('v_users')->insert(['user_uuid' => self::ACTOR, 'domain_uuid' => self::DOMAIN, 'username' => 'api-user', 'user_enabled' => 'true']);
        DB::table('v_user_groups')->insert(['user_uuid' => self::ACTOR, 'domain_uuid' => self::DOMAIN, 'group_uuid' => self::ROLE]);
        DB::table('personal_access_tokens')->insert([
            'id' => 1, 'tokenable_type' => User::class, 'tokenable_id' => self::ACTOR, 'name' => 'SIP test',
            'token' => hash('sha256', 'sip-api-test-token'), 'abilities' => '["*"]', 'created_at' => now(),
        ]);
        $this->withToken('1|sip-api-test-token');
        $this->grant('extension_view', 'extension_edit', 'extension_password');

        foreach ([self::EXTENSION => self::DOMAIN, self::FOREIGN_EXTENSION => self::FOREIGN] as $extension => $domain) {
            $context = $domain === self::DOMAIN ? 'local.example.test' : 'foreign.example.test';
            DB::table('v_domains')->insert(['domain_uuid' => $domain, 'domain_name' => $context]);
            DB::table('v_extensions')->insert([
                'extension_uuid' => $extension, 'domain_uuid' => $domain, 'extension' => '1001',
                'password' => 'original-'.$domain, 'user_context' => $context,
            ]);
            DB::table('v_device_lines')->insert([
                'device_line_uuid' => (string) Str::uuid(), 'domain_uuid' => $domain,
                'auth_id' => '1001', 'password' => 'original-'.$domain,
            ]);
        }

        $this->cacheDirectory = sys_get_temp_dir().'/fspbx-sip-api-'.bin2hex(random_bytes(6));
        mkdir($this->cacheDirectory);
        foreach (['cacheType' => 'file', 'cacheLocation' => $this->cacheDirectory] as $property => $value) {
            $reflection = new ReflectionProperty(FusionCache::class, $property);
            $this->previousCache[$property] = $reflection->getValue();
            $reflection->setValue(null, $value);
        }
        file_put_contents($this->cacheFile(), 'local-cache');
        file_put_contents($this->cacheFile(true), 'foreign-cache');

        $router = app('router');
        $router->setRoutes(new RouteCollection());
        $router->prefix('api/v1')->middleware('api')->group(base_path('routes/api_v1.php'));
    }

    protected function tearDown(): void
    {
        DB::disconnect('sip_credentials_test');
        foreach ($this->previousCache as $property => $value) {
            (new ReflectionProperty(FusionCache::class, $property))->setValue(null, $value);
        }
        if (isset($this->cacheDirectory)) {
            File::deleteDirectory($this->cacheDirectory);
        }
        parent::tearDown();
    }

    public static function actions(): array
    {
        return [['GET', 'sip-credentials', 'sipCredentials', 'extension_view'],
            ['POST', 'regenerate-sip-credentials', 'regenerateSipCredentials', 'extension_edit']];
    }

    /** @dataProvider actions */
    public function test_source_routes_require_bearer_auth_domain_authorization_and_both_permissions(string $method, string $suffix, string $action, string $permission): void
    {
        $route = app('router')->getRoutes()->match(Request::create($this->endpoint($suffix), $method));
        $this->assertSame(ExtensionController::class.'@'.$action, $route->getActionName());
        foreach (['auth:sanctum', 'api.token.auth', 'throttle:api', 'user.authorize:'.$permission, 'user.authorize:extension_password'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }

        foreach ([$permission, 'extension_password'] as $missing) {
            DB::table('v_group_permissions')->where('permission_name', $missing)->delete();
            Cache::flush();
            $this->json($method, $this->endpoint($suffix))->assertForbidden()
                ->assertJsonPath('error.code', 'forbidden_permission')->assertJsonPath('error.permission', $missing);
            $this->grant($missing);
        }
        $this->assertUnchanged();
    }

    /** @dataProvider actions */
    public function test_missing_invalid_and_expired_tokens_cannot_read_or_reset(string $method, string $suffix): void
    {
        $this->flushHeaders();
        $this->json($method, $this->endpoint($suffix))->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken('1|incorrect-token')->json($method, $this->endpoint($suffix))->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        DB::table('personal_access_tokens')->update(['expires_at' => now()->subMinute()]);
        $this->withToken('1|sip-api-test-token')->json($method, $this->endpoint($suffix))->assertUnauthorized();
        $this->assertUnchanged();
    }

    /** @dataProvider actions */
    public function test_a_browser_login_without_a_bearer_token_is_rejected(string $method, string $suffix): void
    {
        $this->flushHeaders();
        $this->actingAs(User::findOrFail(self::ACTOR), 'web');
        $this->json($method, $this->endpoint($suffix))->assertUnauthorized()->assertJsonPath('message', 'Bearer token required.');
        $this->assertUnchanged();
    }

    /** @dataProvider actions */
    public function test_foreign_domains_and_mismatched_extension_ids_are_rejected(string $method, string $suffix): void
    {
        $this->json($method, $this->endpoint($suffix, self::FOREIGN, self::FOREIGN_EXTENSION))
            ->assertForbidden()->assertJsonPath('error.code', 'forbidden_domain');
        $this->json($method, $this->endpoint($suffix, self::DOMAIN, self::FOREIGN_EXTENSION))
            ->assertNotFound()->assertJsonPath('error.code', 'resource_missing')->assertJsonPath('error.param', 'extension_uuid');

        // Even a caller with explicit all-account access cannot mix the URL's account and extension.
        $this->grant('domain_all');
        $this->json($method, $this->endpoint($suffix, self::DOMAIN, self::FOREIGN_EXTENSION))->assertNotFound();
        $this->assertUnchanged();
    }

    /** @dataProvider actions */
    public function test_invalid_and_missing_identifiers_follow_the_api_error_contract(string $method, string $suffix): void
    {
        $this->grant('domain_all');
        foreach ([['bad-uuid', self::EXTENSION, 'domain_uuid'], [self::DOMAIN, str_repeat('-', 36), 'extension_uuid']] as [$domain, $extension, $param]) {
            $this->json($method, $this->endpoint($suffix, $domain, $extension))->assertStatus(400)
                ->assertJsonPath('error.code', 'invalid_request')->assertJsonPath('error.param', $param);
        }
        foreach ([[(string) Str::uuid(), self::EXTENSION, 'domain_uuid'], [self::DOMAIN, (string) Str::uuid(), 'extension_uuid']] as [$domain, $extension, $param]) {
            $this->json($method, $this->endpoint($suffix, $domain, $extension))->assertNotFound()
                ->assertJsonPath('error.code', 'resource_missing')->assertJsonPath('error.param', $param);
        }
        $this->assertUnchanged();
    }

    public function test_read_returns_only_the_target_credentials_with_no_store_headers(): void
    {
        session(['domain_uuid' => self::FOREIGN]);
        $response = $this->getJson($this->endpoint('sip-credentials').'?domain_uuid='.self::FOREIGN.'&extension_uuid='.self::FOREIGN_EXTENSION)
            ->assertOk()->assertExactJson([
                'extension_uuid' => self::EXTENSION, 'object' => 'extension_sip_credentials',
                'domain_uuid' => self::DOMAIN, 'extension' => '1001',
                'password' => 'original-'.self::DOMAIN, 'domain' => 'local.example.test',
            ])->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Pragma', 'no-cache');
        $this->assertStringNotContainsString('original-'.self::FOREIGN, $response->getContent());
        $this->assertUnchanged();
    }

    public function test_reset_updates_only_local_internal_device_lines_and_ringotel_users(): void
    {
        session(['domain_uuid' => self::FOREIGN]);
        DB::table('v_device_lines')->insert([
            'device_line_uuid' => (string) Str::uuid(), 'domain_uuid' => self::DOMAIN,
            'auth_id' => '1001', 'password' => 'external-password', 'external_line' => 't',
        ]);
        foreach ([self::DOMAIN, self::FOREIGN] as $domain) {
            DB::table('mobile_app_users')->insert([
                'mobile_app_user_uuid' => (string) Str::uuid(), 'domain_uuid' => $domain,
                'extension_uuid' => self::EXTENSION, 'org_id' => 'org-'.$domain, 'user_id' => 'user-'.$domain, 'status' => '-1',
            ]);
        }

        $response = $this->postJson($this->endpoint('regenerate-sip-credentials'), [
            'domain_uuid' => self::FOREIGN, 'extension_uuid' => self::FOREIGN_EXTENSION, 'password' => 'client-chosen-password',
        ])->assertOk()->assertJsonPath('object', 'extension_sip_credentials')->assertJsonPath('domain_uuid', self::DOMAIN)
            ->assertJsonPath('extension_uuid', self::EXTENSION)->assertJsonPath('extension', '1001')
            ->assertJsonPath('domain', 'local.example.test')->assertHeader('Cache-Control', 'no-store, private');
        $password = $response->json('password');
        $this->assertNotEmpty($password);
        $this->assertNotSame('original-'.self::DOMAIN, $password);
        $this->assertNotSame('client-chosen-password', $password);
        $this->assertDatabaseHas('v_extensions', ['extension_uuid' => self::EXTENSION, 'password' => $password]);
        $this->assertDatabaseHas('v_device_lines', ['domain_uuid' => self::DOMAIN, 'external_line' => null, 'password' => $password]);
        $this->assertDatabaseHas('v_extensions', ['extension_uuid' => self::FOREIGN_EXTENSION, 'password' => 'original-'.self::FOREIGN]);
        $this->assertDatabaseHas('v_device_lines', ['domain_uuid' => self::FOREIGN, 'password' => 'original-'.self::FOREIGN]);
        $this->assertDatabaseHas('v_device_lines', ['external_line' => 't', 'password' => 'external-password']);
        Queue::assertPushed(UpdateAppSettings::class, 1);
        Queue::assertPushed(UpdateAppSettings::class, fn ($job) => $job->attributes === [
            'user_id' => 'user-'.self::DOMAIN, 'org_id' => 'org-'.self::DOMAIN, 'password' => $password, 'no_email' => true,
        ] && $job->afterCommit === true);
        $this->assertDatabaseHas('mobile_app_users', ['domain_uuid' => self::DOMAIN, 'status' => '-1']);
        $this->assertFileDoesNotExist($this->cacheFile());
        $this->assertFileExists($this->cacheFile(true));
        $this->getJson($this->endpoint('sip-credentials'))->assertOk()->assertJsonPath('password', $password);
    }

    public function test_explicit_managed_account_access_uses_the_target_account(): void
    {
        DB::table('user_domain_permission')->insert(['user_uuid' => self::ACTOR, 'domain_uuid' => self::FOREIGN]);
        $this->getJson($this->endpoint('sip-credentials', self::FOREIGN, self::FOREIGN_EXTENSION))
            ->assertOk()->assertJsonPath('password', 'original-'.self::FOREIGN)->assertJsonPath('domain', 'foreign.example.test');
        $this->getJson($this->endpoint('sip-credentials'))->assertForbidden()->assertJsonPath('error.code', 'forbidden_domain');
    }

    public function test_create_and_patch_context_use_the_route_account_and_preserve_omission(): void
    {
        $this->createExtensionWriteTables();
        $this->grant('extension_add');
        $url = '/api/v1/domains/'.self::DOMAIN.'/extensions';
        $payload = ['extension' => '2111', 'directory_first_name' => 'FXO', 'voicemail_enabled' => false];

        $uuid = $this->postJson($url, $payload)->assertCreated()->json('extension_uuid');
        $this->assertDatabaseHas('v_extensions', ['extension_uuid' => $uuid, 'user_context' => 'local.example.test']);
        $this->patchJson($url.'/'.$uuid, ['description' => 'Port one'])->assertOk();
        $this->patchJson($url.'/'.$uuid, ['user_context' => 'public'])->assertForbidden()->assertJsonPath('error.param', 'user_context');
        $payload['extension'] = '2112';
        $this->postJson($url, $payload + ['user_context' => 'public'])->assertForbidden();
        $this->assertDatabaseMissing('v_extensions', ['extension' => '2112']);
        $this->assertDatabaseHas('v_extensions', ['extension_uuid' => $uuid, 'user_context' => 'local.example.test']);

        $this->grant('extension_user_context');
        $gateway = $this->postJson($url, $payload + ['user_context' => 'public'])->assertCreated()->json('extension_uuid');
        $this->assertDatabaseHas('v_extensions', ['extension_uuid' => $gateway, 'domain_uuid' => self::DOMAIN, 'user_context' => 'public']);
        $this->patchJson($url.'/'.$uuid, ['user_context' => 'public'])->assertOk();
        $this->patchJson($url.'/'.$uuid, ['description' => 'Renamed port'])->assertOk();
        $this->assertDatabaseHas('v_extensions', ['extension_uuid' => $uuid, 'user_context' => 'public']);
        $this->patchJson($url.'/'.$uuid, ['user_context' => 'local.example.test'])->assertOk();
        $this->assertDatabaseHas('v_extensions', ['extension_uuid' => $uuid, 'user_context' => 'local.example.test']);
    }

    public function test_foreign_and_unsupported_contexts_are_rejected_on_create_and_update(): void
    {
        $this->createExtensionWriteTables();
        $this->grant('extension_add', 'extension_user_context', 'domain_all');
        $url = '/api/v1/domains/'.self::DOMAIN.'/extensions';
        $payload = ['extension' => '2120', 'directory_first_name' => 'FXO', 'voicemail_enabled' => false];
        session(['domain_uuid' => self::FOREIGN, 'domain_name' => 'foreign.example.test']);

        foreach (['foreign.example.test', 'custom', null, ''] as $context) {
            $this->postJson($url, $payload + ['user_context' => $context])->assertStatus(400)->assertJsonPath('error.param', 'user_context');
            $this->patchJson($url.'/'.self::EXTENSION, ['user_context' => $context])->assertStatus(400)->assertJsonPath('error.param', 'user_context');
        }
        $this->patchJson($url.'/'.self::FOREIGN_EXTENSION, ['user_context' => 'public'])->assertNotFound();
        $this->assertDatabaseMissing('v_extensions', ['extension' => '2120']);
        $this->assertDatabaseHas('v_extensions', ['extension_uuid' => self::EXTENSION, 'user_context' => 'local.example.test']);
        $this->assertDatabaseHas('v_extensions', ['extension_uuid' => self::FOREIGN_EXTENSION, 'user_context' => 'foreign.example.test']);
    }

    public function test_password_reset_clears_the_registration_cache_for_a_public_context_extension(): void
    {
        DB::table('v_extensions')->where('extension_uuid', self::EXTENSION)->update(['user_context' => 'public']);
        $this->postJson($this->endpoint('regenerate-sip-credentials'))->assertOk()->assertJsonPath('domain', 'public');
        $this->assertFileDoesNotExist($this->cacheFile());
        $this->assertFileExists($this->cacheFile(true));
    }

    public function test_scribe_describes_the_optional_context_on_both_extension_write_routes(): void
    {
        $url = '/api/v1/domains/'.self::DOMAIN.'/extensions';
        foreach (['POST' => $url, 'PATCH' => $url.'/'.self::EXTENSION] as $method => $path) {
            $route = app('router')->getRoutes()->match(Request::create($path, $method));
            $parameters = (new Extractor())->processRoute($route)->bodyParameters;
            $this->assertSame('user_context', array_key_last($parameters));
            if ($method === 'POST') {
                $this->assertSame(['extension', 'directory_first_name', 'voicemail_enabled'], array_slice(array_keys($parameters), 0, 3));
            }
            $field = $parameters['user_context'];
            $this->assertFalse($field->required);
            $this->assertSame('public', $field->example);
            $this->assertStringContainsString('extension_user_context', $field->description);
        }
    }

    private function createExtensionWriteTables(): void
    {
        $missing = array_diff((new Extensions())->getFillable(), Schema::getColumnListing('v_extensions'));
        Schema::table('v_extensions', function (Blueprint $table) use ($missing) {
            foreach ($missing as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::table('mobile_app_users', function (Blueprint $table) {
            $table->string('conn_id')->nullable();
            $table->boolean('exclude_from_stale_report')->nullable();
        });
        // Extension creation checks the number against these other dialable resources.
        foreach ([
            'v_ring_groups' => 'ring_group_extension', 'v_call_center_queues' => 'queue_extension',
            'v_fax' => 'fax_extension', 'v_ivr_menus' => 'ivr_menu_extension', 'v_call_flows' => 'call_flow_extension',
            'v_conference_centers' => 'conference_center_extension', 'v_conferences' => 'conference_extension',
            'business_hours' => 'extension', 'ai_agents' => 'extension', 'dynamic_routes' => 'extension', 'paging_groups' => 'extension',
        ] as $tableName => $numberColumn) {
            Schema::create($tableName, function (Blueprint $table) use ($numberColumn) {
                $table->string('domain_uuid');
                $table->string($numberColumn);
            });
        }
        Schema::create('v_groups', fn (Blueprint $table) => $table->string('group_uuid'));
        Schema::create('v_domain_settings', function (Blueprint $table) {
            $table->string('domain_uuid');
            $table->string('domain_setting_subcategory');
            $table->string('domain_setting_enabled');
        });
        Schema::create('v_default_settings', function (Blueprint $table) {
            $table->string('default_setting_subcategory');
            $table->string('default_setting_enabled');
        });
    }

    public function test_reset_failure_does_not_expose_passwords_in_response_or_logs(): void
    {
        $this->mock(ExtensionPasswordService::class, function ($mock) {
            $mock->shouldReceive('update')->once()->andThrow(new \RuntimeException('sensitive-password-in-exception'));
        });
        $response = $this->postJson($this->endpoint('regenerate-sip-credentials'))->assertStatus(500)
            ->assertJsonPath('error.code', 'internal_error');
        $this->assertStringNotContainsString('sensitive-password-in-exception', $response->getContent());
        Log::shouldHaveReceived('error')->with('API SIP password reset failed.', [
            'domain_uuid' => self::DOMAIN, 'extension_uuid' => self::EXTENSION, 'exception_class' => \RuntimeException::class,
        ])->once();
        $this->assertUnchanged();
    }

    public function test_database_failure_rolls_back_the_extension_and_device_passwords(): void
    {
        // Fail after the extension observer updates the linked device lines.
        Schema::drop('mobile_app_users');
        $this->postJson($this->endpoint('regenerate-sip-credentials'))->assertStatus(500)
            ->assertJsonPath('error.message', 'Unable to regenerate SIP credentials.');
        $this->assertUnchanged();
    }

    public function test_scribe_extracts_static_examples_without_fetching_or_resetting_credentials(): void
    {
        foreach (self::actions() as [$method, $suffix]) {
            $route = app('router')->getRoutes()->match(Request::create($this->endpoint($suffix), $method));
            $endpoint = (new Extractor())->processRoute($route);
            $this->assertSame('Extensions', $endpoint->metadata->groupName);
            $this->assertTrue($endpoint->metadata->authenticated);
            $this->assertSame([], $endpoint->bodyParameters);
            $this->assertArrayHasKey('domain_uuid', $endpoint->urlParameters);
            $this->assertArrayHasKey('extension_uuid', $endpoint->urlParameters);
            $example = json_decode($endpoint->responses->first()->content, true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('extension_sip_credentials', $example['object']);
            $this->assertSame('pbx.example.com', $example['domain']);
            $this->assertStringStartsWith('example-only-', $example['password']);
        }
        $this->assertUnchanged();
    }

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('v_group_permissions')->insert(['group_uuid' => self::ROLE, 'permission_name' => $permission, 'permission_assigned' => 'true']);
        }
        Cache::flush();
    }

    private function endpoint(string $suffix, string $domain = self::DOMAIN, string $extension = self::EXTENSION): string
    {
        return '/api/v1/domains/'.$domain.'/extensions/'.$extension.'/'.$suffix;
    }

    private function cacheFile(bool $foreign = false): string
    {
        return $this->cacheDirectory.'/directory.1001@'.($foreign ? 'foreign' : 'local').'.example.test';
    }

    private function assertUnchanged(): void
    {
        foreach ([self::DOMAIN, self::FOREIGN] as $domain) {
            $this->assertDatabaseHas('v_extensions', ['domain_uuid' => $domain, 'password' => 'original-'.$domain]);
            $this->assertDatabaseHas('v_device_lines', ['domain_uuid' => $domain, 'password' => 'original-'.$domain]);
        }
        $this->assertFileExists($this->cacheFile());
        $this->assertFileExists($this->cacheFile(true));
        Queue::assertNothingPushed();
    }
}
