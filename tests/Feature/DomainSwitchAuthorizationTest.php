<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SessionDomainService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Auth, Bus, DB, File, Http, Process, Schema};
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class DomainSwitchAuthorizationTest extends TestCase
{
    private const A = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const B = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const C = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
    private const DISABLED = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
    private const MISSING = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';
    private const USER = '11111111-1111-4111-8111-111111111111';
    private const OTHER_USER = '22222222-2222-4222-8222-222222222222';
    private const GROUP = '33333333-3333-4333-8333-333333333333';
    private string $sessionPath;

    protected function setUp(): void
    {
        // Exercise the real web middleware and legacy PHP session in an isolated process.
        $this->sessionPath = sys_get_temp_dir().'/fspbx-domain-switch-tests-'.getmypid();
        mkdir($this->sessionPath, 0700, true);
        session_save_path($this->sessionPath);
        session_start();

        parent::setUp();
        config([
            'database.default' => 'domain_switch_authorization',
            'database.connections.domain_switch_authorization' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'session.driver' => 'array', 'logging.default' => 'null',
        ]);
        DB::purge('domain_switch_authorization');
        Http::preventStrayRequests();
        Bus::fake();
        Process::fake();

        foreach ([
            'v_domains' => ['domain_uuid', 'domain_name', 'domain_description', 'domain_parent_uuid', 'domain_enabled'],
            'v_groups' => ['group_uuid', 'group_name'],
            'v_user_groups' => ['group_uuid', 'user_uuid', 'domain_uuid', 'group_name'],
            'user_domain_permission' => ['user_uuid', 'domain_uuid'],
            'domain_groups' => ['domain_group_uuid', 'group_name'],
            'domain_group_relations' => ['domain_group_uuid', 'domain_uuid'],
            'user_domain_group_permissions' => ['user_uuid', 'domain_group_uuid'],
        ] as $table => $columns) {
            Schema::create($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->string($column)->nullable();
                }
            });
        }
        foreach ([self::A, self::B, self::C, self::DISABLED] as $uuid) {
            DB::table('v_domains')->insert([
                'domain_uuid' => $uuid, 'domain_name' => $uuid.'.invalid', 'domain_description' => 'Account '.$uuid,
                'domain_enabled' => $uuid === self::DISABLED ? 'f' : 't',
            ]);
        }

        $this->actingAs((new User)->forceFill(['user_uuid' => self::USER, 'domain_uuid' => self::A, 'user_enabled' => 'true']));
        $this->withSession([
            'domain_uuid' => self::A, 'domain_name' => self::A.'.invalid', 'domain_description' => 'Account '.self::A,
            'context' => self::A.'.invalid', 'domain.language.code' => 'en-us', 'permissions' => [],
            'domain_select' => false, 'domains' => [],
        ]);
        $_SESSION = [
            'user' => ['domain_uuid' => self::A], 'domain_uuid' => self::A,
            'domain_name' => self::A.'.invalid', 'domain_description' => 'Account '.self::A, 'context' => self::A.'.invalid',
        ];
    }

    protected function tearDown(): void
    {
        DB::disconnect('domain_switch_authorization');
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        File::deleteDirectory($this->sessionPath);
        parent::tearDown();
    }

    public static function methods(): array
    {
        return [['POST'], ['GET']];
    }

    /** @dataProvider methods */
    public function test_switch_requires_domain_select_even_with_an_account_grant(string $method): void
    {
        $this->grant(self::B);
        $this->withSession(['domain_select' => true]);
        $this->switchTo($method, self::B)->assertForbidden();
        $this->assertCurrentAccount(self::A);
    }

    /** @dataProvider methods */
    public function test_permission_alone_or_another_users_grant_does_not_allow_a_switch(string $method): void
    {
        $this->permissions('domain_select');
        DB::table('user_domain_permission')->insert(['user_uuid' => self::OTHER_USER, 'domain_uuid' => self::B]);
        $this->switchTo($method, self::B)->assertNotFound();
        $this->assertCurrentAccount(self::A);
    }

    /** @dataProvider methods */
    public function test_explicit_grants_allow_switching_and_returning_to_the_own_account(string $method): void
    {
        $this->permissions('domain_select');
        $this->grant(self::B);
        $this->switchTo($method, self::B)->assertStatus($method === 'POST' ? 200 : 302);
        $this->assertCurrentAccount(self::B);
        $this->assertSame(self::A, Auth::user()->domain_uuid);
        $this->switchTo($method, self::A)->assertStatus($method === 'POST' ? 200 : 302);
        $this->assertCurrentAccount(self::A);
        $this->switchTo($method, self::A)->assertStatus($method === 'POST' ? 200 : 302);
    }

    /** @dataProvider methods */
    public function test_account_group_grants_are_checked_fresh(string $method): void
    {
        $this->permissions('domain_select');
        $this->grantGroup(self::B);
        $this->switchTo($method, self::B)->assertStatus($method === 'POST' ? 200 : 302);
        $this->assertCurrentAccount(self::B);
        $this->switchTo($method, self::A)->assertStatus($method === 'POST' ? 200 : 302);

        DB::table('user_domain_group_permissions')->delete();
        $this->withSession(['domains' => collect([(object) ['domain_uuid' => self::B]])]);
        $this->switchTo($method, self::B)->assertNotFound();
        $this->assertCurrentAccount(self::A);
    }

    /** @dataProvider methods */
    public function test_revoked_explicit_grants_are_not_restored_by_a_stale_menu(string $method): void
    {
        $this->permissions('domain_select');
        $this->grant(self::B);
        app(SessionDomainService::class)->refreshForUser(Auth::user());
        $this->assertTrue(session('domains')->contains('domain_uuid', self::B));
        DB::table('user_domain_permission')->delete();
        $this->switchTo($method, self::B)->assertNotFound();
        $this->assertCurrentAccount(self::A);
    }

    /** @dataProvider methods */
    public function test_superadmins_can_switch_to_enabled_accounts_only(string $method): void
    {
        $this->permissions('domain_select');
        $this->makeSuperadmin();
        $this->switchTo($method, self::B)->assertStatus($method === 'POST' ? 200 : 302);
        $this->assertCurrentAccount(self::B);
        foreach ([self::DISABLED, self::MISSING] as $uuid) {
            $this->switchTo($method, $uuid)->assertNotFound();
            $this->assertCurrentAccount(self::B);
        }
    }

    /** @dataProvider methods */
    public function test_disabled_accounts_are_rejected_even_with_an_explicit_grant(string $method): void
    {
        $this->permissions('domain_select');
        $this->grant(self::DISABLED);
        $this->switchTo($method, self::DISABLED)->assertNotFound();
        $this->assertCurrentAccount(self::A);
    }

    /** @dataProvider methods */
    public function test_session_role_labels_do_not_grant_superadmin_access(string $method): void
    {
        $this->permissions('domain_select');
        $this->withSession(['user.groups' => [(object) ['group_name' => 'superadmin']]]);
        $this->switchTo($method, self::B)->assertNotFound();
        $this->assertCurrentAccount(self::A);
    }

    /** @dataProvider methods */
    public function test_invalid_ids_and_unauthenticated_requests_cannot_switch(string $method): void
    {
        $this->permissions('domain_select');
        $this->switchTo($method, 'invalid')->assertStatus($method === 'POST' ? 422 : 404);
        $this->assertCurrentAccount(self::A);
        Auth::logout();
        $this->switchTo($method, self::B)->assertRedirect(route('login'));
        $this->assertCurrentAccount(self::A);
    }

    public function test_post_requires_a_uuid_and_preserves_the_redirect_response(): void
    {
        $this->permissions('domain_select');
        $this->grant(self::B);
        foreach ([[], ['domain_uuid' => null], ['domain_uuid' => [self::B]]] as $payload) {
            $this->postJson('/domains/switch', $payload)->assertUnprocessable()->assertJsonValidationErrors('domain_uuid');
            $this->assertCurrentAccount(self::A);
        }
        $this->switchTo('POST', self::B)->assertOk()->assertJsonPath('redirectUrl', '/dashboard');
    }

    public function test_session_refresh_uses_the_same_access_rules_as_switching(): void
    {
        $this->permissions('domain_select');
        $this->grant(self::B);
        $this->grantGroup(self::C);
        app(SessionDomainService::class)->refreshForUser(Auth::user());
        $this->assertSame([self::A, self::B, self::C], session('domains')->pluck('domain_uuid')->sort()->values()->all());
        $this->assertSame([self::A, self::B, self::C], array_keys($_SESSION['domains']));
        $this->assertCurrentAccount(self::A);

        $this->permissions();
        app(SessionDomainService::class)->refreshForUser(Auth::user());
        $this->assertNull(session('domains'));
        $this->assertArrayNotHasKey('domains', $_SESSION);
        $this->assertCurrentAccount(self::A);
    }

    /** @dataProvider methods */
    public function test_an_unauthorized_switch_cannot_bypass_holiday_ownership(string $method): void
    {
        Schema::create('business_hours', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('domain_uuid');
        });
        Schema::create('business_hour_holidays', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('business_hour_uuid');
        });
        DB::table('business_hours')->insert(['uuid' => self::B, 'domain_uuid' => self::B]);
        DB::table('business_hour_holidays')->insert(['uuid' => self::B, 'business_hour_uuid' => self::B]);
        $this->permissions('business_hours_holidays_update');

        $this->postJson('/api/holiday-hours/item-options', ['item_uuid' => self::B])->assertNotFound();
        $this->switchTo($method, self::B)->assertForbidden();
        $this->assertCurrentAccount(self::A);
        $this->postJson('/api/holiday-hours/item-options', ['item_uuid' => self::B])->assertNotFound();
    }

    private function switchTo(string $method, string $uuid): TestResponse
    {
        return $method === 'POST'
            ? $this->postJson('/domains/switch', ['domain_uuid' => $uuid, 'redirect_url' => '/dashboard'])
            : $this->from('/dashboard')->getJson('/domains/switch/'.$uuid);
    }

    private function permissions(string ...$permissions): void
    {
        $this->withSession(['permissions' => array_map(fn ($name) => (object) ['permission_name' => $name], $permissions)]);
    }

    private function grant(string $uuid): void
    {
        DB::table('user_domain_permission')->insert(['user_uuid' => self::USER, 'domain_uuid' => $uuid]);
    }

    private function grantGroup(string $uuid): void
    {
        DB::table('domain_groups')->insert(['domain_group_uuid' => self::GROUP, 'group_name' => 'Assigned accounts']);
        DB::table('domain_group_relations')->insert(['domain_group_uuid' => self::GROUP, 'domain_uuid' => $uuid]);
        DB::table('user_domain_group_permissions')->insert(['user_uuid' => self::USER, 'domain_group_uuid' => self::GROUP]);
    }

    private function makeSuperadmin(): void
    {
        DB::table('v_groups')->insert(['group_uuid' => self::GROUP, 'group_name' => 'superadmin']);
        DB::table('v_user_groups')->insert([
            'group_uuid' => self::GROUP, 'user_uuid' => self::USER, 'domain_uuid' => self::A, 'group_name' => 'superadmin',
        ]);
    }

    private function assertCurrentAccount(string $uuid): void
    {
        foreach (['domain_uuid' => $uuid, 'domain_name' => $uuid.'.invalid', 'domain_description' => 'Account '.$uuid, 'context' => $uuid.'.invalid'] as $key => $value) {
            $this->assertSame($value, session($key));
            $this->assertSame($value, $_SESSION[$key]);
        }
    }
}
