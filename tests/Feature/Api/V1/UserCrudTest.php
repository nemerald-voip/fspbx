<?php

namespace Tests\Feature\Api\V1;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\UserController as WebUserController;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Knuckles\Scribe\Extracting\Extractor;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseTag;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    private const DOMAIN = '11111111-1111-4111-8111-111111111111';
    private const FOREIGN = '22222222-2222-4222-8222-222222222222';
    private const ACTOR = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const ROLE = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const EXTENSION = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
    private const GROUP = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
    private const LOCATION = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';
    private const DIRECTORY = 'ffffffff-ffff-4fff-8fff-ffffffffffff';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.user_crud_test', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        config()->set('database.default', 'user_crud_test');
        config()->set('cache.default', 'array');
        config()->set('data.structure_caching.cache.store', 'array');
        config()->set('auth.defaults.passwords', 'users');
        config()->set('auth.passwords.users', [
            'provider' => 'users', 'table' => 'password_resets', 'expire' => 60, 'throttle' => 60,
        ]);
        DB::purge('user_crud_test');
        Log::spy()->shouldReceive('channel')->andReturnSelf();
        $limiter = new RateLimiter(Cache::store('array'));
        $limiter->for('api', app(RateLimiter::class)->limiter('api'));
        $this->app->instance(RateLimiter::class, $limiter);

        foreach ([
            'v_domains' => ['domain_uuid', 'domain_name'],
            'v_users' => ['user_uuid', 'domain_uuid', 'username', 'user_email', 'user_enabled', 'extension_uuid', 'password', 'api_key', 'add_date', 'add_user'],
            'users_adv_fields' => ['id', 'user_uuid', 'first_name', 'last_name', 'two_factor_secret'],
            'v_user_settings' => ['user_setting_uuid', 'user_uuid', 'domain_uuid', 'user_setting_category', 'user_setting_subcategory', 'user_setting_name', 'user_setting_value', 'user_setting_enabled'],
            'v_user_groups' => ['user_group_uuid', 'user_uuid', 'domain_uuid', 'group_uuid', 'group_name'],
            'v_groups' => ['group_uuid', 'domain_uuid', 'group_name', 'group_level'],
            'v_group_permissions' => ['group_uuid', 'permission_name', 'permission_assigned'],
            'user_domain_permission' => ['id', 'user_uuid', 'domain_uuid', 'created_at', 'updated_at'],
            'user_domain_group_permissions' => ['id', 'user_uuid', 'domain_group_uuid', 'created_at', 'updated_at'],
            'domain_group_relations' => ['domain_group_uuid', 'domain_uuid'],
            'domain_groups' => ['domain_group_uuid', 'group_name'],
            'v_extensions' => ['extension_uuid', 'domain_uuid', 'extension'],
            'locations' => ['location_uuid', 'domain_uuid', 'name'],
            'locationables' => ['locationable_id', 'locationable_type', 'location_uuid'],
            'personal_access_tokens' => ['id', 'tokenable_id', 'tokenable_type', 'name', 'token'],
            'sessions' => ['id', 'user_id'],
            'password_resets' => ['email', 'token', 'created_at'],
            'v_domain_settings' => ['domain_uuid', 'domain_setting_category', 'domain_setting_subcategory', 'domain_setting_enabled', 'domain_setting_value'],
            'v_default_settings' => ['default_setting_category', 'default_setting_subcategory', 'default_setting_enabled', 'default_setting_value'],
            'ldap_directories' => ['directory_uuid', 'name', 'priority', 'manage_groups_locally'],
            'ldap_directory_users' => ['directory_user_uuid', 'directory_uuid', 'domain_uuid', 'user_uuid', 'email', 'extension'],
            'ldap_directory_user_group_assignments' => ['directory_user_uuid', 'group_uuid'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns, $name) {
                foreach ($columns as $column) {
                    if ($name === 'ldap_directories' && $column === 'priority') {
                        $table->integer($column)->nullable();
                        continue;
                    }
                    if ($name === 'ldap_directories' && $column === 'manage_groups_locally') {
                        $table->boolean($column)->nullable();
                        continue;
                    }
                    $table->string($column)->nullable();
                }
            });
        }
        DB::table('v_domains')->insert([
            ['domain_uuid' => self::DOMAIN, 'domain_name' => 'local.example.com'],
            ['domain_uuid' => self::FOREIGN, 'domain_name' => 'foreign.example.com'],
        ]);
        DB::table('v_groups')->insert(['group_uuid' => self::ROLE, 'domain_uuid' => null, 'group_name' => 'admin', 'group_level' => 50]);
        DB::table('v_user_groups')->insert(['user_uuid' => self::ACTOR, 'domain_uuid' => self::DOMAIN, 'group_uuid' => self::ROLE]);
        DB::table('v_extensions')->insert(['extension_uuid' => self::EXTENSION, 'domain_uuid' => self::DOMAIN, 'extension' => '1001']);
        DB::table('locations')->insert(['location_uuid' => self::LOCATION, 'domain_uuid' => self::DOMAIN, 'name' => 'Office']);
        DB::table('domain_groups')->insert(['domain_group_uuid' => self::GROUP, 'group_name' => 'Accounts']);
        DB::table('domain_group_relations')->insert(['domain_group_uuid' => self::GROUP, 'domain_uuid' => self::DOMAIN]);
        $this->grant('user_view', 'user_add', 'user_edit', 'user_delete', 'user_group_edit', 'user_status', 'user_update_managed_accounts', 'user_update_managed_account_groups');
        Sanctum::actingAs((new User())->forceFill(['user_uuid' => self::ACTOR, 'domain_uuid' => self::DOMAIN]));
        $this->withToken('test-token');

        $router = app('router');
        $router->setRoutes(new RouteCollection());
        $router->prefix('api/v1')->middleware('api')->group(base_path('routes/api_v1.php'));
    }

    protected function tearDown(): void
    {
        DB::disconnect('user_crud_test');
        parent::tearDown();
    }

    public static function routes(): array
    {
        return [['GET', '', 'index', 'user_view'], ['GET', '/'.self::ACTOR, 'show', 'user_view'],
            ['POST', '', 'store', 'user_add'], ['PATCH', '/'.self::ACTOR, 'update', 'user_edit'],
            ['DELETE', '/'.self::ACTOR, 'destroy', 'user_delete'],
            ['POST', '/'.self::ACTOR.'/password-reset', 'sendPasswordReset', 'user_edit']];
    }

    /** @dataProvider routes */
    public function test_source_routes_and_action_permissions(string $method, string $suffix, string $action, string $permission): void
    {
        $route = app('router')->getRoutes()->match(Request::create($this->endpoint().$suffix, $method));
        $this->assertSame(UserController::class.'@'.$action, $route->getActionName());
        foreach (['auth:sanctum', 'api.token.auth', 'user.authorize:'.$permission] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
        DB::table('v_group_permissions')->where('permission_name', $permission)->delete();
        $this->json($method, $this->endpoint().$suffix)->assertForbidden()->assertJsonPath('error.permission', $permission);
    }

    public function test_create_needs_only_name_and_email_and_ignores_body_domain_and_secrets(): void
    {
        $response = $this->postJson($this->endpoint(), [
            'first_name' => 'Jane', 'user_email' => ' JANE@Example.com ',
            'domain_uuid' => self::FOREIGN, 'password' => 'unexpected', 'api_key' => 'unexpected',
            'username' => 'client_supplied', 'language' => 'fr-fr',
        ])->assertCreated()->assertJsonPath('first_name', 'Jane')->assertJsonPath('user_email', 'jane@example.com')
            ->assertJsonPath('domain_uuid', self::DOMAIN)->assertJsonPath('extension_uuid', null)
            ->assertJsonPath('groups', [])->assertJsonPath('user_enabled', true);
        $uuid = $response->json('user_uuid');
        $this->assertTrue(Str::isUuid($uuid));
        $response->assertHeader('Location', $this->endpoint($uuid));
        $this->assertNull(DB::table('v_users')->where('user_uuid', $uuid)->value('password'));
        $this->assertDatabaseHas('v_users', ['user_uuid' => $uuid, 'username' => 'jane']);
        $this->assertDatabaseHas('v_user_settings', [
            'user_uuid' => $uuid, 'domain_uuid' => self::DOMAIN,
            'user_setting_category' => 'domain', 'user_setting_subcategory' => 'language',
            'user_setting_name' => 'code', 'user_setting_value' => 'en-us', 'user_setting_enabled' => '1',
        ]);
        $this->assertArrayNotHasKey('password', $response->json());
        $this->assertArrayNotHasKey('api_key', $response->json());
        $this->assertArrayNotHasKey('username', $response->json());
        $this->assertArrayNotHasKey('language', $response->json());
    }

    public function test_create_defaults_timezone_to_the_target_account_and_updates_preserve_or_clear_it(): void
    {
        session(['domain_uuid' => self::FOREIGN]);
        DB::table('v_domain_settings')->insert([
            ['domain_uuid' => self::DOMAIN, 'domain_setting_subcategory' => 'time_zone', 'domain_setting_enabled' => 'true', 'domain_setting_value' => 'Europe/Paris'],
            ['domain_uuid' => self::FOREIGN, 'domain_setting_subcategory' => 'time_zone', 'domain_setting_enabled' => 'true', 'domain_setting_value' => 'Asia/Tokyo'],
        ]);
        DB::table('v_default_settings')->insert([
            'default_setting_subcategory' => 'time_zone', 'default_setting_enabled' => 'true',
            'default_setting_value' => 'America/New_York',
        ]);

        foreach ([[], ['time_zone' => null]] as $index => $fields) {
            $response = $this->postJson($this->endpoint(), array_merge([
                'first_name' => 'Jane', 'user_email' => "jane{$index}@example.com",
            ], $fields))->assertCreated()->assertJsonPath('time_zone', 'Europe/Paris');
            $uuid = $response->json('user_uuid');
            $this->assertDatabaseHas('v_user_settings', [
                'user_uuid' => $uuid, 'domain_uuid' => self::DOMAIN,
                'user_setting_category' => 'domain', 'user_setting_subcategory' => 'time_zone',
                'user_setting_name' => 'name', 'user_setting_value' => 'Europe/Paris', 'user_setting_enabled' => '1',
            ]);
            $this->patchJson($this->endpoint($uuid), ['last_name' => 'Smith'])->assertOk()->assertJsonPath('time_zone', 'Europe/Paris');
            $this->patchJson($this->endpoint($uuid), ['time_zone' => null])->assertOk()->assertJsonPath('time_zone', null);
            $this->patchJson($this->endpoint($uuid), ['last_name' => 'Jones'])->assertOk()->assertJsonPath('time_zone', null);
            $this->assertDatabaseHas('v_user_settings', [
                'user_uuid' => $uuid, 'user_setting_subcategory' => 'time_zone', 'user_setting_value' => null,
            ]);
        }
    }

    public function test_create_timezone_falls_back_to_enabled_system_setting_then_utc(): void
    {
        DB::table('v_domain_settings')->insert([
            'domain_uuid' => self::DOMAIN, 'domain_setting_subcategory' => 'time_zone',
            'domain_setting_enabled' => 'false', 'domain_setting_value' => 'Europe/Paris',
        ]);
        DB::table('v_default_settings')->insert([
            'default_setting_subcategory' => 'time_zone', 'default_setting_enabled' => 'true',
            'default_setting_value' => 'America/Chicago',
        ]);
        $this->postJson($this->endpoint(), ['first_name' => 'Jane', 'user_email' => 'system@example.com'])
            ->assertCreated()->assertJsonPath('time_zone', 'America/Chicago');

        DB::table('v_default_settings')->update(['default_setting_enabled' => 'false']);
        Cache::forget(self::DOMAIN.'_timeZone');
        $this->postJson($this->endpoint(), ['first_name' => 'Jane', 'user_email' => 'utc@example.com'])
            ->assertCreated()->assertJsonPath('time_zone', 'UTC');
    }

    public function test_supplied_roles_must_be_non_empty_valid_uuids_that_exist(): void
    {
        $uuid = $this->seedUser();
        foreach ([[], null, [null], ['invalid-uuid'], [(string) Str::uuid()]] as $groups) {
            $param = $groups === [] || $groups === null ? 'groups' : 'groups.0';
            $this->postJson($this->endpoint(), ['first_name' => 'Jane', 'user_email' => 'jane@example.com', 'groups' => $groups])
                ->assertStatus(400)->assertJsonPath('error.param', $param);
            $this->patchJson($this->endpoint($uuid), ['groups' => $groups])
                ->assertStatus(400)->assertJsonPath('error.param', $param);
        }
        $this->assertDatabaseMissing('v_users', ['user_email' => 'jane@example.com']);
        $this->postJson($this->endpoint(), ['first_name' => 'Jane', 'user_email' => 'jane@example.com', 'groups' => [self::ROLE], 'extension_uuid' => null])
            ->assertCreated()->assertJsonPath('groups', [self::ROLE])->assertJsonPath('extension_uuid', null);
    }

    public function test_full_create_and_partial_update_preserve_assignments(): void
    {
        $this->grant('domain_all');
        DB::table('v_domain_settings')->insert([
            'domain_uuid' => self::DOMAIN, 'domain_setting_subcategory' => 'time_zone',
            'domain_setting_enabled' => 'true', 'domain_setting_value' => 'Europe/Paris',
        ]);
        $response = $this->postJson($this->endpoint(), [
            'first_name' => 'Jane', 'last_name' => 'Smith', 'user_email' => 'jane@example.com',
            'groups' => [self::ROLE], 'extension_uuid' => self::EXTENSION,
            'accounts' => [self::FOREIGN], 'account_groups' => [self::GROUP], 'locations' => [self::LOCATION],
            'time_zone' => 'America/New_York', 'user_enabled' => false,
        ])->assertCreated()->assertJsonPath('time_zone', 'America/New_York');
        $uuid = $response->json('user_uuid');
        DB::table('v_user_settings')->where('user_uuid', $uuid)->where('user_setting_subcategory', 'language')
            ->update(['user_setting_value' => 'de-de']);
        $this->patchJson($this->endpoint($uuid), ['first_name' => 'Janet', 'language' => 'fr-fr', 'username' => 'client_supplied'])->assertOk()
            ->assertJsonPath('first_name', 'Janet')->assertJsonPath('last_name', 'Smith')
            ->assertJsonPath('groups', [self::ROLE])->assertJsonPath('extension_uuid', self::EXTENSION)
            ->assertJsonPath('accounts', [self::FOREIGN])->assertJsonPath('account_groups', [self::GROUP])
            ->assertJsonPath('locations', [self::LOCATION])->assertJsonPath('time_zone', 'America/New_York')
            ->assertJsonMissingPath('language')->assertJsonMissingPath('username')->assertJsonPath('user_enabled', false);
        $this->assertDatabaseHas('v_user_settings', ['user_uuid' => $uuid, 'user_setting_subcategory' => 'language', 'user_setting_value' => 'de-de']);
        $this->assertDatabaseHas('v_users', ['user_uuid' => $uuid, 'username' => 'jane_smith']);
        $this->patchJson($this->endpoint($uuid), ['user_email' => 'JANE@example.com'])->assertOk();
        $this->patchJson($this->endpoint($uuid), [
            'groups' => [self::ROLE], 'extension_uuid' => null, 'accounts' => [], 'account_groups' => [], 'locations' => [],
        ])->assertOk()->assertJsonPath('groups', [self::ROLE])->assertJsonPath('extension_uuid', null)
            ->assertJsonPath('accounts', [])->assertJsonPath('account_groups', [])->assertJsonPath('locations', []);
    }

    public function test_list_cursor_search_status_and_secret_fields(): void
    {
        $first = $this->seedUser('10000000-0000-4000-8000-000000000001');
        $second = $this->seedUser('10000000-0000-4000-8000-000000000002', self::DOMAIN, ['user_enabled' => 'false']);
        $this->seedUser('10000000-0000-4000-8000-000000000003', self::FOREIGN);
        DB::table('users_adv_fields')->where('user_uuid', $second)->update(['first_name' => 'UniqueName', 'two_factor_secret' => 'sensitive']);
        $this->getJson($this->endpoint().'?limit=1')->assertOk()->assertJsonPath('has_more', true)->assertJsonPath('data.0.user_uuid', $first)
            ->assertJsonMissingPath('data.0.language')->assertJsonMissingPath('data.0.username');
        $this->getJson($this->endpoint().'?limit=1&starting_after='.$first)->assertOk()->assertJsonPath('has_more', false)->assertJsonPath('data.0.user_uuid', $second);
        $this->getJson($this->endpoint().'?search=uniquename&user_enabled=false')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user_uuid', $second);
        $response = $this->getJson($this->endpoint($second))->assertOk();
        foreach (['password', 'api_key', 'two_factor_secret', 'settings', 'user_adv_fields', 'username', 'language'] as $secret) {
            $this->assertArrayNotHasKey($secret, $response->json());
        }
        $this->assertStringNotContainsString('sensitive', $response->getContent());
    }

    public function test_list_returns_summaries_without_querying_user_details(): void
    {
        $this->grant('domain_all');
        $details = [
            'time_zone' => 'America/New_York', 'accounts' => [self::FOREIGN],
            'account_groups' => [self::GROUP], 'locations' => [self::LOCATION],
        ];
        $created = $this->postJson($this->endpoint(), array_merge([
            'first_name' => 'Jane', 'last_name' => 'Smith', 'user_email' => 'jane@example.com',
            'groups' => [self::ROLE], 'extension_uuid' => self::EXTENSION,
        ], $details))->assertCreated();
        foreach ($details as $field => $value) {
            $created->assertJsonPath($field, $value);
        }
        $uuid = $created->json('user_uuid');
        DB::table('ldap_directory_users')->insert([
            'directory_user_uuid' => (string) Str::uuid(), 'directory_uuid' => self::DIRECTORY,
            'domain_uuid' => self::DOMAIN, 'user_uuid' => $uuid,
        ]);
        $summary = [
            'user_uuid' => $uuid, 'object' => 'user', 'domain_uuid' => self::DOMAIN,
            'first_name' => 'Jane', 'last_name' => 'Smith', 'user_email' => 'jane@example.com',
            'user_enabled' => true, 'extension_uuid' => self::EXTENSION,
            'groups' => [self::ROLE], 'directory_managed' => true,
        ];

        DB::enableQueryLog();
        try {
            $this->getJson($this->endpoint())->assertOk()->assertExactJson([
                'object' => 'list', 'url' => $this->endpoint(), 'has_more' => false, 'data' => [$summary],
            ]);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $detailQueries = collect($queries)->filter(fn ($query) =>
            in_array($uuid, $query['bindings'], true) && Str::contains($query['query'], [
                'v_user_settings', 'user_domain_permission', 'user_domain_group_permissions',
                'locations', 'locationables',
            ])
        );
        $this->assertCount(0, $detailQueries, 'Listing users must not load their settings or detail assignments.');

        $this->getJson($this->endpoint($uuid))->assertOk()->assertExactJson(array_merge($summary, $details));
    }

    public function test_password_reset_uses_saved_email_without_changing_password_status_or_sessions(): void
    {
        Notification::fake();
        $uuid = $this->seedUser(attributes: ['user_enabled' => 'false']);
        $user = User::findOrFail($uuid);
        DB::table('sessions')->insert(['id' => 'existing-session', 'user_id' => $uuid]);
        DB::table('personal_access_tokens')->insert(['id' => 'existing-token', 'tokenable_id' => $uuid, 'tokenable_type' => User::class]);

        $response = $this->postJson($this->endpoint($uuid).'/password-reset', [
            'email' => 'other@example.com', 'user_email' => 'other@example.com',
            'domain_uuid' => self::FOREIGN, 'password' => 'client-supplied',
        ])->assertOk()->assertExactJson(['object' => 'password_reset', 'user_uuid' => $uuid, 'sent' => true]);

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
        $token = Notification::sent($user, ResetPassword::class)->sole()->token;
        $storedToken = DB::table('password_resets')->where('email', $user->user_email)->value('token');
        $this->assertNotSame($token, $storedToken);
        $this->assertTrue(Hash::check($token, $storedToken));
        $this->assertTrue(Password::broker()->tokenExists($user, $token));
        $this->assertStringNotContainsString($token, $response->getContent());
        $this->assertDatabaseCount('password_resets', 1);
        $this->assertDatabaseHas('v_users', ['user_uuid' => $uuid, 'password' => 'sensitive', 'user_enabled' => 'false']);
        $this->assertDatabaseHas('sessions', ['id' => 'existing-session', 'user_id' => $uuid]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => 'existing-token', 'tokenable_id' => $uuid]);
    }

    public function test_password_reset_throttles_resends_and_uses_the_existing_broker_cooldown(): void
    {
        Notification::fake();
        $uuid = $this->seedUser();
        $user = User::findOrFail($uuid);
        $endpoint = $this->endpoint($uuid).'/password-reset';
        $this->postJson($endpoint)->assertOk();
        $firstToken = Notification::sent($user, ResetPassword::class)->sole()->token;
        $this->postJson($endpoint)->assertStatus(429)->assertJsonPath('error.code', 'password_reset_throttled');
        Notification::assertCount(1);
        $this->assertTrue(Password::broker()->tokenExists($user, $firstToken));

        try {
            $this->travel(61)->seconds();
            $this->postJson($endpoint)->assertOk();
            Notification::assertCount(2);
            $this->assertFalse(Password::broker()->tokenExists($user, $firstToken));
            $this->assertTrue(Password::broker()->tokenExists($user, Notification::sent($user, ResetPassword::class)->last()->token));
        } finally {
            $this->travelBack();
        }
    }

    public function test_password_setup_tokens_work_with_the_existing_reset_action_and_are_single_use_and_expiring(): void
    {
        Notification::fake();
        // Keep the password policy, but isolate its external breached-password lookup.
        $this->mock(UncompromisedVerifier::class)->shouldReceive('verify')->once()->andReturnTrue();
        $created = $this->postJson($this->endpoint(), ['first_name' => 'Jane', 'user_email' => 'jane@example.com'])->assertCreated();
        $uuid = $created->json('user_uuid');
        Notification::assertNothingSent();
        $this->postJson($this->endpoint($uuid).'/password-reset')->assertOk();
        $user = User::findOrFail($uuid);
        $this->assertNull($user->password);
        $credentials = [
            'user_email' => $user->user_email,
            'token' => Notification::sent($user, ResetPassword::class)->sole()->token,
            'password' => 'New-Secure-Password-123!',
        ];
        $reset = function (User $target, string $password): void {
            app(ResetUserPassword::class)->reset($target, [
                'password' => $password, 'password_confirmation' => $password,
            ]);
        };
        $this->assertSame(Password::PASSWORD_RESET, Password::broker()->reset($credentials, $reset));
        $this->assertTrue(Hash::check($credentials['password'], $user->fresh()->password));
        $this->assertSame(Password::INVALID_TOKEN, Password::broker()->reset($credentials, $reset));
        $this->assertDatabaseCount('password_resets', 0);

        $this->postJson($this->endpoint($uuid).'/password-reset')->assertOk();
        $credentials['token'] = Notification::sent($user, ResetPassword::class)->last()->token;
        try {
            $this->travel(61)->minutes();
            $this->assertSame(Password::INVALID_TOKEN, Password::broker()->reset($credentials, $reset));
        } finally {
            $this->travelBack();
        }
    }

    public function test_password_reset_requires_bearer_auth_and_access_to_the_target_domain(): void
    {
        Notification::fake();
        $uuid = $this->seedUser(domain: self::FOREIGN);
        $this->postJson('/api/v1/domains/'.self::FOREIGN.'/users/'.$uuid.'/password-reset')->assertForbidden();
        $this->withHeaders(['Authorization' => ''])->postJson($this->endpoint($uuid).'/password-reset')->assertUnauthorized();
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_resets', 0);
    }

    public function test_password_reset_rejects_foreign_missing_and_invalid_targets_before_sending(): void
    {
        Notification::fake();
        $this->grant('domain_all');
        $uuid = $this->seedUser(domain: self::FOREIGN);
        $this->postJson($this->endpoint($uuid).'/password-reset')->assertNotFound();
        $this->postJson($this->endpoint((string) Str::uuid()).'/password-reset')->assertNotFound();
        $this->postJson($this->endpoint('invalid').'/password-reset')->assertStatus(400)->assertJsonPath('error.param', 'user_uuid');
        $this->postJson('/api/v1/domains/invalid/users/'.$uuid.'/password-reset')->assertStatus(400)->assertJsonPath('error.param', 'domain_uuid');
        $this->postJson('/api/v1/domains/'.Str::uuid().'/users/'.$uuid.'/password-reset')->assertNotFound();
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_resets', 0);
    }

    public function test_password_reset_protects_higher_level_users_and_superadmins(): void
    {
        Notification::fake();
        $uuid = $this->seedUser();
        $role = (string) Str::uuid();
        DB::table('v_groups')->insert(['group_uuid' => $role, 'domain_uuid' => null, 'group_name' => 'senior', 'group_level' => 70]);
        DB::table('v_user_groups')->insert(['user_uuid' => $uuid, 'domain_uuid' => self::DOMAIN, 'group_uuid' => $role]);
        $this->postJson($this->endpoint($uuid).'/password-reset')->assertForbidden();
        DB::table('v_groups')->where('group_uuid', $role)->update(['group_name' => 'SuPeRaDmIn', 'group_level' => 10]);
        $this->postJson($this->endpoint($uuid).'/password-reset')->assertForbidden();
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_resets', 0);
    }

    public function test_password_reset_rejects_directory_users_and_missing_email_addresses(): void
    {
        Notification::fake();
        $uuid = $this->seedUser();
        DB::table('ldap_directory_users')->insert([
            'directory_user_uuid' => self::DIRECTORY, 'directory_uuid' => self::DIRECTORY,
            'domain_uuid' => self::DOMAIN, 'user_uuid' => $uuid,
        ]);
        // An orphaned directory link still protects the user from local resets.
        $this->postJson($this->endpoint($uuid).'/password-reset')->assertStatus(400)->assertJsonPath('error.param', 'user_uuid');
        DB::table('ldap_directories')->insert(['directory_uuid' => self::DIRECTORY, 'name' => 'Directory', 'priority' => 1]);
        $this->postJson($this->endpoint($uuid).'/password-reset')->assertStatus(400)->assertJsonPath('error.param', 'user_uuid');
        foreach ([null, '', 'invalid-email'] as $email) {
            $localUuid = $this->seedUser(attributes: ['user_email' => $email]);
            $this->postJson($this->endpoint($localUuid).'/password-reset')->assertStatus(400)->assertJsonPath('error.param', 'user_email');
        }
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_resets', 0);
    }

    public function test_password_reset_mail_transport_failure_returns_a_safe_error(): void
    {
        $uuid = $this->seedUser();
        Notification::shouldReceive('send')->once()->andThrow(new TransportException('Sensitive SMTP diagnostic'));
        $response = $this->postJson($this->endpoint($uuid).'/password-reset')
            ->assertStatus(503)->assertJsonPath('error.code', 'mail_delivery_failed');
        $this->assertStringNotContainsString('Sensitive SMTP diagnostic', $response->getContent());
        $response->assertJsonMissingPath('sent');
        $this->assertDatabaseHas('v_users', ['user_uuid' => $uuid, 'password' => 'sensitive']);
    }

    public function test_foreign_users_cannot_be_read_changed_or_deleted_even_with_domain_all(): void
    {
        $uuid = $this->seedUser(domain: self::FOREIGN);
        $this->grant('domain_all');
        $this->getJson($this->endpoint($uuid))->assertNotFound();
        $this->patchJson($this->endpoint($uuid), ['first_name' => 'Changed'])->assertNotFound();
        $this->deleteJson($this->endpoint($uuid))->assertNotFound();
        $this->assertDatabaseHas('v_users', ['user_uuid' => $uuid, 'domain_uuid' => self::FOREIGN]);
    }

    public function test_explicit_domain_access_and_bearer_header_are_required(): void
    {
        $this->getJson('/api/v1/domains/'.self::FOREIGN.'/users')->assertForbidden()->assertJsonPath('error.code', 'forbidden_domain');
        $this->withHeaders(['Authorization' => ''])->getJson($this->endpoint())->assertUnauthorized();
    }

    public function test_assigned_domain_user_can_manage_that_domain(): void
    {
        DB::table('user_domain_permission')->insert(['user_uuid' => self::ACTOR, 'domain_uuid' => self::FOREIGN]);
        $response = $this->postJson('/api/v1/domains/'.self::FOREIGN.'/users', ['first_name' => 'Remote', 'user_email' => 'remote@example.com'])->assertCreated();
        $this->patchJson('/api/v1/domains/'.self::FOREIGN.'/users/'.$response->json('user_uuid'), ['last_name' => 'Updated'])->assertOk();
    }

    public function test_foreign_extension_role_and_location_are_rejected_without_writes(): void
    {
        $uuid = $this->seedUser();
        DB::table('v_extensions')->where('extension_uuid', self::EXTENSION)->update(['domain_uuid' => self::FOREIGN]);
        DB::table('locations')->where('location_uuid', self::LOCATION)->update(['domain_uuid' => self::FOREIGN]);
        DB::table('v_groups')->where('group_uuid', self::ROLE)->update(['domain_uuid' => self::FOREIGN]);
        foreach ([['extension_uuid' => self::EXTENSION], ['locations' => [self::LOCATION]]] as $data) {
            $this->patchJson($this->endpoint($uuid), $data)->assertStatus(400);
        }
        $this->patchJson($this->endpoint($uuid), ['groups' => [self::ROLE]])->assertForbidden();
        $this->assertDatabaseMissing('v_user_groups', ['user_uuid' => $uuid]);
    }

    public function test_role_hierarchy_prevents_escalation_and_protects_targets(): void
    {
        $role = (string) Str::uuid();
        DB::table('v_groups')->insert(['group_uuid' => $role, 'group_name' => 'higher', 'group_level' => 70]);
        $uuid = $this->seedUser();
        $this->patchJson($this->endpoint($uuid), ['groups' => [$role]])->assertForbidden();
        DB::table('v_user_groups')->insert(['user_uuid' => $uuid, 'group_uuid' => $role, 'domain_uuid' => self::DOMAIN]);
        $this->patchJson($this->endpoint($uuid), ['first_name' => 'Changed'])->assertForbidden();
        $this->deleteJson($this->endpoint($uuid))->assertForbidden();
        DB::table('v_groups')->where('group_uuid', $role)->update(['group_name' => 'SuPeRaDmIn', 'group_level' => 10]);
        $this->patchJson($this->endpoint($uuid), ['first_name' => 'Changed'])->assertForbidden();
        $this->deleteJson($this->endpoint($uuid))->assertForbidden();
    }

    public function test_optional_fields_require_their_own_permissions_only_when_changed(): void
    {
        $uuid = $this->seedUser();
        DB::table('v_group_permissions')->whereIn('permission_name', ['user_group_edit', 'user_status', 'user_update_managed_accounts'])->delete();
        $this->patchJson($this->endpoint($uuid), ['first_name' => 'Changed'])->assertOk();
        $this->patchJson($this->endpoint($uuid), ['groups' => [self::ROLE]])->assertForbidden();
        $this->patchJson($this->endpoint($uuid), ['user_enabled' => false])->assertForbidden();
        $this->patchJson($this->endpoint($uuid), ['accounts' => [self::DOMAIN]])->assertForbidden();
    }

    public function test_managed_accounts_cannot_grant_foreign_access(): void
    {
        $uuid = $this->seedUser();
        $this->patchJson($this->endpoint($uuid), ['accounts' => [self::FOREIGN]])->assertForbidden();
        $this->patchJson($this->endpoint($uuid), ['account_groups' => [self::GROUP]])->assertForbidden();
        $this->assertDatabaseMissing('user_domain_permission', ['user_uuid' => $uuid]);
        $this->assertDatabaseMissing('user_domain_group_permissions', ['user_uuid' => $uuid]);
    }

    public function test_directory_identity_cannot_change_or_be_deleted_and_mapped_roles_survive(): void
    {
        $uuid = $this->seedUser();
        DB::table('ldap_directories')->insert(['directory_uuid' => self::DIRECTORY, 'name' => 'Directory', 'priority' => 1, 'manage_groups_locally' => false]);
        DB::table('ldap_directory_users')->insert(['directory_user_uuid' => self::DIRECTORY, 'directory_uuid' => self::DIRECTORY, 'domain_uuid' => self::DOMAIN, 'user_uuid' => $uuid, 'email' => 'remote@example.com', 'extension' => '1001']);
        DB::table('ldap_directory_user_group_assignments')->insert(['directory_user_uuid' => self::DIRECTORY, 'group_uuid' => self::ROLE]);
        DB::table('v_user_groups')->insert(['user_uuid' => $uuid, 'domain_uuid' => self::DOMAIN, 'group_uuid' => self::ROLE]);
        foreach ([['first_name' => 'Changed'], ['last_name' => null], ['user_email' => 'changed@example.com'], ['user_enabled' => false], ['extension_uuid' => null]] as $data) {
            $this->patchJson($this->endpoint($uuid), $data)->assertStatus(400);
        }
        $this->patchJson($this->endpoint($uuid), ['groups' => []])->assertStatus(400)->assertJsonPath('error.param', 'groups');
        $this->patchJson($this->endpoint($uuid), ['groups' => [self::ROLE], 'time_zone' => 'UTC'])->assertOk()
            ->assertJsonPath('directory_managed', true)->assertJsonPath('groups', [self::ROLE]);
        $this->deleteJson($this->endpoint($uuid))->assertStatus(400);
        $this->assertDatabaseHas('v_users', ['user_uuid' => $uuid]);
    }

    public function test_directory_selection_uses_priority_then_name_within_the_users_domain(): void
    {
        $uuid = $this->seedUser();
        $other = $this->seedUser();
        foreach ([
            ['Later', 10, self::DOMAIN, $uuid],
            ['Beta', 2, self::DOMAIN, $uuid],
            ['Alpha', 2, self::DOMAIN, $uuid],
            ['Foreign', 0, self::FOREIGN, $uuid],
            ['Other user', 0, self::DOMAIN, $other],
        ] as [$name, $priority, $domain, $userUuid]) {
            $directoryUuid = (string) Str::uuid();
            DB::table('ldap_directories')->insert([
                'directory_uuid' => $directoryUuid, 'name' => $name, 'priority' => $priority, 'manage_groups_locally' => true,
            ]);
            DB::table('ldap_directory_users')->insert([
                'directory_user_uuid' => (string) Str::uuid(), 'directory_uuid' => $directoryUuid,
                'domain_uuid' => $domain, 'user_uuid' => $userUuid, 'email' => 'directory@example.com',
            ]);
        }

        $management = app(UserService::class)->directoryManagement(User::findOrFail($uuid));

        $this->assertSame('Alpha', $management['directory_name']);
        $this->assertTrue($management['manage_groups_locally']);
        $this->assertTrue($management['email_managed']);
    }

    public function test_orphan_directory_link_keeps_identity_and_deletion_protected(): void
    {
        $uuid = $this->seedUser();
        DB::table('ldap_directory_users')->insert([
            'directory_user_uuid' => (string) Str::uuid(), 'directory_uuid' => self::DIRECTORY,
            'domain_uuid' => self::DOMAIN, 'user_uuid' => $uuid,
        ]);

        $management = app(UserService::class)->directoryManagement(User::findOrFail($uuid));
        $this->assertTrue($management['managed']);
        $this->assertNull($management['directory_name']);
        $this->patchJson($this->endpoint($uuid), ['first_name' => 'Changed'])->assertStatus(400);
        $this->deleteJson($this->endpoint($uuid))->assertStatus(400);
        $this->assertDatabaseHas('v_users', ['user_uuid' => $uuid]);
    }

    public function test_delete_cleans_related_data_tokens_and_sessions(): void
    {
        $uuid = $this->seedUser();
        $other = $this->seedUser();
        DB::table('personal_access_tokens')->insert(['id' => 'token', 'tokenable_type' => User::class, 'tokenable_id' => $uuid]);
        DB::table('sessions')->insert([['id' => 'target-session', 'user_id' => $uuid], ['id' => 'other-session', 'user_id' => $other]]);
        DB::table('locationables')->insert(['locationable_type' => User::class, 'locationable_id' => $uuid, 'location_uuid' => self::LOCATION]);
        DB::table('v_user_groups')->insert(['user_uuid' => $uuid, 'domain_uuid' => self::DOMAIN, 'group_uuid' => self::ROLE]);
        Cache::tags(['auth', "user:{$uuid}", 'permissions'])->put('perm:user_edit', true);
        $this->deleteJson($this->endpoint($uuid))->assertOk()->assertExactJson(['uuid' => $uuid, 'object' => 'user', 'deleted' => true]);
        foreach (['v_users', 'users_adv_fields', 'v_user_groups'] as $table) {
            $this->assertDatabaseMissing($table, ['user_uuid' => $uuid]);
        }
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $uuid]);
        $this->assertDatabaseMissing('locationables', ['locationable_id' => $uuid]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $uuid]);
        $this->assertDatabaseHas('sessions', ['user_id' => $other]);
        $this->assertNull(Cache::tags(['auth', "user:{$uuid}", 'permissions'])->get('perm:user_edit'));
    }

    public function test_creation_limit_and_validation_prevent_writes(): void
    {
        $this->postJson($this->endpoint(), [])->assertStatus(400)->assertJsonPath('error.param', 'first_name');
        $this->postJson($this->endpoint(), ['first_name' => 'Jane', 'user_email' => 'not-email'])->assertStatus(400);
        DB::table('v_domain_settings')->insert(['domain_uuid' => self::DOMAIN, 'domain_setting_category' => 'limit', 'domain_setting_subcategory' => 'users', 'domain_setting_value' => '0', 'domain_setting_enabled' => 'true']);
        $this->postJson($this->endpoint(), ['first_name' => 'Jane', 'user_email' => 'jane@example.com'])->assertStatus(400)->assertJsonPath('error.param', 'users');
        $this->assertSame(0, DB::table('v_users')->count());
    }

    public function test_bad_ids_and_unknown_resources_return_api_errors(): void
    {
        $this->grant('domain_all');
        $this->getJson('/api/v1/domains/bad/users')->assertStatus(400)->assertJsonPath('error.param', 'domain_uuid');
        $this->getJson($this->endpoint('bad'))->assertStatus(400)->assertJsonPath('error.param', 'user_uuid');
        $this->getJson($this->endpoint((string) Str::uuid()))->assertNotFound();
        $this->getJson('/api/v1/domains/'.Str::uuid().'/users')->assertNotFound();
        $this->getJson($this->endpoint().'?starting_after=bad')->assertStatus(400);
    }

    public function test_web_mutations_share_optional_assignments_and_preserve_partial_assignments(): void
    {
        session(['domain_uuid' => self::DOMAIN, 'permissions' => collect(['user_add', 'user_edit', 'user_delete'])->map(fn ($name) => (object) ['permission_name' => $name])->all()]);
        Route::post('/test-web-users', [WebUserController::class, 'store']);
        Route::put('/test-web-users/{user}', [WebUserController::class, 'update'])->middleware('api');
        Route::post('/test-web-users/bulk-delete', [WebUserController::class, 'bulkDelete']);
        $created = $this->postJson('/test-web-users', ['first_name' => 'Web', 'user_email' => 'web@example.com', 'domain_uuid' => self::DOMAIN, 'user_enabled' => 'true', 'groups' => [self::ROLE], 'language' => 'en-us'])->assertCreated();
        $uuid = $created->json('user_uuid');
        foreach ([[], null, [null], ['invalid-uuid'], [(string) Str::uuid()]] as $groups) {
            $field = $groups === [] || $groups === null ? 'groups' : 'groups.0';
            $this->postJson('/test-web-users', ['first_name' => 'Rejected', 'user_email' => 'rejected@example.com', 'domain_uuid' => self::DOMAIN, 'user_enabled' => 'true', 'groups' => $groups])
                ->assertStatus(422)->assertJsonValidationErrors($field);
            $this->putJson('/test-web-users/'.$uuid, ['first_name' => 'Web', 'user_email' => 'web@example.com', 'groups' => $groups])
                ->assertStatus(422)->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseMissing('v_users', ['user_email' => 'rejected@example.com']);
        $this->assertDatabaseHas('v_user_settings', ['user_uuid' => $uuid, 'user_setting_subcategory' => 'language', 'user_setting_value' => 'en-us']);
        $this->putJson('/test-web-users/'.$uuid, ['first_name' => 'Updated', 'user_email' => 'web@example.com', 'extension_uuid' => self::EXTENSION, 'groups' => [self::ROLE], 'language' => 'fr-fr'])->assertOk();
        $this->putJson('/test-web-users/'.$uuid, ['first_name' => 'Updated again', 'user_email' => 'web@example.com'])->assertOk();
        $this->assertDatabaseHas('v_user_settings', ['user_uuid' => $uuid, 'user_setting_subcategory' => 'language', 'user_setting_value' => 'fr-fr']);
        $this->getJson($this->endpoint($uuid))->assertOk()->assertJsonPath('groups', [self::ROLE])->assertJsonPath('extension_uuid', self::EXTENSION);
        $this->postJson('/test-web-users/bulk-delete', ['items' => [$uuid]])->assertOk();
        $this->assertDatabaseMissing('v_users', ['user_uuid' => $uuid]);
    }

    public function test_scribe_extracts_all_endpoints_and_optional_assignments_without_response_calls(): void
    {
        config()->set('scribe.strategies.responses', [UseResponseTag::class]);
        foreach (self::routes() as [$method, $suffix, $action]) {
            $route = app('router')->getRoutes()->match(Request::create($this->endpoint().$suffix, $method));
            $endpoint = (new Extractor())->processRoute($route);
            $this->assertSame('Users', $endpoint->metadata->groupName);
            $this->assertTrue($endpoint->metadata->authenticated);
            $this->assertNotEmpty($endpoint->responses);
            $this->assertArrayNotHasKey('language', $endpoint->bodyParameters);
            $this->assertArrayNotHasKey('username', $endpoint->bodyParameters);
            if (in_array($action, ['index', 'show', 'store', 'update'])) {
                $response = json_decode($endpoint->responses->first()->content, true, 512, JSON_THROW_ON_ERROR);
                $user = $action === 'index' ? $response['data'][0] : $response;
                foreach (['time_zone', 'accounts', 'account_groups', 'locations'] as $field) {
                    if ($action === 'index') {
                        $this->assertArrayNotHasKey($field, $user);
                    } else {
                        $this->assertArrayHasKey($field, $user);
                    }
                }
            }
            if ($action === 'sendPasswordReset') {
                $this->assertSame([], $endpoint->bodyParameters);
                $this->assertSame([200, 400, 401, 403, 404, 429, 503], $endpoint->responses->pluck('status')->all());
                $response = json_decode($endpoint->responses->first()->content, true, 512, JSON_THROW_ON_ERROR);
                $this->assertSame(['object' => 'password_reset', 'user_uuid' => 'c9a76140-0ca4-4ea3-95af-7e12c2ff0df5', 'sent' => true], $response);
            }
            if (in_array($action, ['store', 'update'])) {
                foreach (['groups', 'accounts', 'account_groups', 'locations'] as $field) {
                    $this->assertFalse($endpoint->bodyParameters[$field]->required, $field);
                    $this->assertSame('string[]', $endpoint->bodyParameters[$field]->type);
                }
                $this->assertFalse($endpoint->bodyParameters['extension_uuid']->required);
                $this->assertSame($action === 'store', $endpoint->bodyParameters['first_name']->required);
                $this->assertSame($action === 'store', $endpoint->bodyParameters['user_email']->required);
                $this->assertStringContainsString('at least one existing role UUID', $endpoint->bodyParameters['groups']->description);
                $this->assertNotEmpty($endpoint->bodyParameters['groups']->example);
            }
        }
    }

    private function grant(string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('v_group_permissions')->insert(['group_uuid' => self::ROLE, 'permission_name' => $permission, 'permission_assigned' => 'true']);
        }
    }

    private function seedUser(?string $uuid = null, string $domain = self::DOMAIN, array $attributes = []): string
    {
        $uuid ??= (string) Str::uuid();
        DB::table('v_users')->insert(array_merge(['user_uuid' => $uuid, 'domain_uuid' => $domain, 'username' => 'test_user', 'user_email' => $uuid.'@example.com', 'user_enabled' => 'true', 'password' => 'sensitive', 'api_key' => 'sensitive'], $attributes));
        DB::table('users_adv_fields')->insert(['id' => (string) Str::uuid(), 'user_uuid' => $uuid, 'first_name' => 'Test', 'last_name' => 'User']);

        return $uuid;
    }

    private function endpoint(string $uuid = ''): string
    {
        return '/api/v1/domains/'.self::DOMAIN.'/users'.($uuid !== '' ? '/'.$uuid : '');
    }
}
