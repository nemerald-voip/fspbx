<?php

namespace Tests\Unit;

use App\Http\Controllers\UsersController;
use App\Http\Requests\StoreLdapDirectoryRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class LdapManagedUserRulesTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        config()->set('database.connections.ldap_managed_user_rules_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'ldap_managed_user_rules_test');
        DB::purge('ldap_managed_user_rules_test');

        $migration = require base_path('database/migrations/2026_08_24_000001_create_ldap_directory_tables.php');
        $migration->up();

        Schema::create('v_users', function (Blueprint $table) {
            $table->uuid('user_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->string('user_email')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('ldap_managed_user_rules_test');
        config()->set('database.default', $this->originalConnection);

        parent::tearDown();
    }

    public function test_email_is_editable_when_active_directory_did_not_provide_one(): void
    {
        $user = $this->user('11111111-1111-4111-8111-111111111111');

        DB::table('ldap_directory_users')->insert([
            'directory_user_uuid' => '22222222-2222-4222-8222-222222222222',
            'directory_uuid' => '33333333-3333-4333-8333-333333333333',
            'domain_uuid' => $user->domain_uuid,
            'user_uuid' => $user->user_uuid,
            'external_id' => 'remote-user-1',
            'extension' => '1001',
            'remote_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rules = $this->requestFor($user)->rules();

        $this->assertSame(['prohibited'], $rules['first_name']);
        $this->assertSame(['prohibited'], $rules['last_name']);
        $this->assertSame(
            ['nullable', 'email', "unique:v_users,user_email,{$user->user_uuid},user_uuid"],
            $rules['user_email']
        );
        $this->assertSame(['prohibited'], $rules['user_enabled']);
        $this->assertSame(['prohibited'], $rules['extension_uuid']);
        $this->assertSame(['sometimes', 'array'], $rules['groups']);
    }

    public function test_email_is_read_only_when_active_directory_provided_it(): void
    {
        $user = $this->user('22222222-2222-4222-8222-222222222222');

        DB::table('ldap_directory_users')->insert([
            'directory_user_uuid' => '33333333-3333-4333-8333-333333333333',
            'directory_uuid' => '44444444-4444-4444-8444-444444444444',
            'domain_uuid' => $user->domain_uuid,
            'user_uuid' => $user->user_uuid,
            'external_id' => 'remote-user-with-email',
            'email' => 'directory@example.com',
            'remote_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(['prohibited'], $this->requestFor($user)->rules()['user_email']);
    }

    public function test_local_user_identity_remains_editable(): void
    {
        $rules = $this->requestFor($this->user('44444444-4444-4444-8444-444444444444'))->rules();

        $this->assertSame(['required', 'string', 'max:255'], $rules['first_name']);
        $this->assertSame(['nullable', 'uuid'], $rules['extension_uuid']);
        $this->assertSame(['sometimes', 'required', 'array'], $rules['groups']);
    }

    public function test_directory_email_attribute_mapping_can_be_left_blank(): void
    {
        $request = StoreLdapDirectoryRequest::create('/ldap-directories', 'POST', [
            'user_email_attribute' => null,
        ]);

        (new ReflectionMethod($request, 'prepareForValidation'))->invoke($request);
        $rule = $request->rules()['user_email_attribute'];

        $this->assertSame('', $request->input('user_email_attribute'));
        $this->assertTrue(Validator::make($request->only('user_email_attribute'), [
            'user_email_attribute' => $rule,
        ])->passes());
    }

    public function test_directory_type_accepts_active_directory_and_generic_ldap(): void
    {
        $request = StoreLdapDirectoryRequest::create('/ldap-directories', 'POST');
        $rule = $request->rules()['type'];

        $this->assertTrue(Validator::make(['type' => 'active_directory'], ['type' => $rule])->passes());
        $this->assertTrue(Validator::make(['type' => 'ldap'], ['type' => $rule])->passes());
        $this->assertFalse(Validator::make(['type' => 'unknown'], ['type' => $rule])->passes());
    }

    public function test_local_source_scope_excludes_directory_managed_users(): void
    {
        $domainUuid = '55555555-5555-4555-8555-555555555555';
        $directoryUserUuid = '66666666-6666-4666-8666-666666666666';
        $localUserUuid = '77777777-7777-4777-8777-777777777777';

        DB::table('v_users')->insert([
            ['user_uuid' => $directoryUserUuid, 'domain_uuid' => $domainUuid, 'user_email' => 'directory@example.com'],
            ['user_uuid' => $localUserUuid, 'domain_uuid' => $domainUuid, 'user_email' => 'local@example.com'],
        ]);
        DB::table('ldap_directory_users')->insert([
            'directory_user_uuid' => '88888888-8888-4888-8888-888888888888',
            'directory_uuid' => '99999999-9999-4999-8999-999999999999',
            'domain_uuid' => $domainUuid,
            'user_uuid' => $directoryUserUuid,
            'external_id' => 'remote-user-2',
            'remote_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $controller = new class extends UsersController
        {
            public function applyLocalSource($query, string $domainUuid): void
            {
                $this->applySourceFilter($query, 'local', $domainUuid, true);
            }
        };
        $query = User::query()->where('domain_uuid', $domainUuid);
        $controller->applyLocalSource($query, $domainUuid);

        $this->assertSame([$localUserUuid], $query->pluck('user_uuid')->all());
    }

    private function user(string $uuid): User
    {
        $user = new User();
        $user->setRawAttributes([
            'user_uuid' => $uuid,
            'domain_uuid' => '55555555-5555-4555-8555-555555555555',
            'user_email' => $uuid . '@example.com',
        ]);
        $user->exists = true;

        return $user;
    }

    private function requestFor(User $user): UpdateUserRequest
    {
        $request = UpdateUserRequest::create('/users/' . $user->user_uuid, 'PUT');
        $request->setRouteResolver(fn () => new class($user)
        {
            public function __construct(private readonly User $user)
            {
            }

            public function parameter(string $name, mixed $default = null): mixed
            {
                return $name === 'user' ? $this->user : $default;
            }
        });

        return $request;
    }
}
