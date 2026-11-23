<?php

namespace Tests\Feature;

use App\Models\{Location, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Bus, DB, Event, Http, Process, Schema};
use Tests\TestCase;

class LocationAuthorizationTest extends TestCase
{
    private const A = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const B = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const LOCAL = '11111111-1111-4111-8111-111111111111';
    private const FOREIGN = '22222222-2222-4222-8222-222222222222';
    private const UNOWNED = '33333333-3333-4333-8333-333333333333';
    private const MISSING = '44444444-4444-4444-8444-444444444444';
    private const URL = '/api/locations';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'location_authorization',
            'database.connections.location_authorization' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'session.driver' => 'array', 'logging.default' => 'null',
        ]);
        DB::purge('location_authorization');
        Http::preventStrayRequests();
        Bus::fake();
        Process::fake();
        Schema::create('locations', function (Blueprint $table) {
            $table->uuid('location_uuid')->primary();
            $table->uuid('domain_uuid')->nullable();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });
        Schema::create('locationables', function (Blueprint $table) {
            $table->uuid('location_uuid');
            $table->uuid('locationable_id');
            $table->string('locationable_type');
        });
        foreach ([self::LOCAL => self::A, self::FOREIGN => self::B, self::UNOWNED => null] as $uuid => $domain) {
            DB::table('locations')->insert(['location_uuid' => $uuid, 'domain_uuid' => $domain, 'name' => 'original']);
            DB::table('locationables')->insert(['location_uuid' => $uuid, 'locationable_id' => $uuid, 'locationable_type' => User::class]);
        }
        $this->actingAs((new User)->forceFill(['user_uuid' => self::LOCAL, 'domain_uuid' => self::A, 'user_enabled' => 'true']));
        $this->withSession(['domain_uuid' => self::A, 'domain_name' => 'a.invalid', 'domain.language.code' => 'en-us', 'permissions' => []]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('location_authorization');
        parent::tearDown();
    }

    public static function endpoints(): array
    {
        return [
            ['GET', '', 'view', []],
            ['POST', '', 'create', ['name' => 'New office']],
            ['PUT', '/'.self::LOCAL, 'update', ['name' => 'New office']],
            ['POST', '/bulk-delete', 'delete', ['items' => [self::LOCAL]]],
        ];
    }

    /** @dataProvider endpoints */
    public function test_each_endpoint_requires_its_action_permission(string $method, string $path, string $action, array $data): void
    {
        $this->permissions(...array_diff(['view', 'create', 'update', 'delete'], [$action]));
        $this->json($method, self::URL.$path, $data)->assertForbidden();
        $this->unchanged();
    }

    /** @dataProvider endpoints */
    public function test_missing_or_invalid_account_context_is_rejected(string $method, string $path, string $action, array $data): void
    {
        $this->permissions($action);
        foreach ([null, '', 'invalid'] as $domain) {
            $this->withSession(['domain_uuid' => $domain]);
            $this->json($method, self::URL.$path, $data)->assertForbidden();
        }
        $this->unchanged();
    }

    public function test_listing_uses_current_account_and_rejects_foreign_account_parameter(): void
    {
        $this->permissions('view');
        foreach (['', '?domain_uuid='.self::A, '?filter[domain_uuid]='.self::B.'&showGlobal=true'] as $query) {
            $this->getJson(self::URL.$query)->assertOk()->assertJsonCount(1)->assertJsonPath('0.location_uuid', self::LOCAL);
        }
        $this->getJson(self::URL.'?domain_uuid='.self::B)->assertForbidden();
        $this->getJson(self::URL.'?domain_uuid=')->assertForbidden();
    }

    public function test_account_switch_uses_session_account_rather_than_login_account(): void
    {
        $this->permissions('view', 'create', 'update', 'delete');
        $this->withSession(['domain_uuid' => self::B]);
        $this->getJson(self::URL)->assertOk()->assertJsonCount(1)->assertJsonPath('0.location_uuid', self::FOREIGN);
        $this->postJson(self::URL, ['name' => 'Account B office'])->assertCreated();
        $this->assertDatabaseHas('locations', ['name' => 'Account B office', 'domain_uuid' => self::B]);
        $this->putJson(self::URL.'/'.self::LOCAL, ['name' => 'forbidden'])->assertNotFound();
        $this->postJson(self::URL.'/bulk-delete', ['items' => [self::LOCAL]])->assertNotFound();
        $this->assertDatabaseHas('locations', ['location_uuid' => self::LOCAL, 'name' => 'original']);
        $this->assertDatabaseHas('locationables', ['location_uuid' => self::LOCAL]);
    }

    public function test_creation_assigns_current_account_and_ignores_supplied_record_uuid(): void
    {
        $this->permissions('create');
        $this->postJson(self::URL, ['name' => 'New office', 'location_uuid' => self::FOREIGN])->assertCreated();
        $created = DB::table('locations')->where('name', 'New office')->first();
        $this->assertSame(self::A, $created->domain_uuid);
        $this->assertNotSame(self::FOREIGN, $created->location_uuid);
        $this->postJson(self::URL, ['name' => 'Second office', 'domain_uuid' => self::A])->assertCreated();
        $this->assertDatabaseHas('locations', ['name' => 'Second office', 'domain_uuid' => self::A]);
        $this->assertDatabaseHas('locations', ['location_uuid' => self::FOREIGN, 'name' => 'original']);
    }

    public function test_client_cannot_create_or_transfer_locations_to_another_account(): void
    {
        $this->permissions('create', 'update');
        foreach ([self::B, null, '', 'invalid'] as $domain) {
            $payload = ['name' => 'changed', 'domain_uuid' => $domain];
            $this->postJson(self::URL, $payload)->assertUnprocessable()->assertJsonValidationErrors('domain_uuid');
            $this->putJson(self::URL.'/'.self::LOCAL, $payload)->assertUnprocessable()->assertJsonValidationErrors('domain_uuid');
            $this->unchanged();
        }
    }

    public function test_own_location_updates_preserve_ownership_and_assignment_links(): void
    {
        $this->permissions('update');
        $this->putJson(self::URL.'/'.self::LOCAL, ['name' => 'Updated office', 'description' => 'First floor', 'location_uuid' => self::FOREIGN])->assertOk();
        $this->assertDatabaseHas('locations', ['location_uuid' => self::LOCAL, 'domain_uuid' => self::A, 'name' => 'Updated office', 'description' => 'First floor']);
        $this->assertDatabaseHas('locations', ['location_uuid' => self::FOREIGN, 'domain_uuid' => self::B, 'name' => 'original']);
        $this->assertSame(3, DB::table('locationables')->count());
        $this->putJson(self::URL.'/'.self::LOCAL, ['name' => 'Updated again', 'domain_uuid' => self::A])->assertOk();
        $this->assertDatabaseHas('locations', ['location_uuid' => self::LOCAL, 'domain_uuid' => self::A, 'name' => 'Updated again']);
    }

    public function test_foreign_unowned_and_missing_locations_cannot_be_updated_or_deleted(): void
    {
        $this->permissions('update', 'delete');
        $this->withSession(['user.groups' => [(object) ['group_name' => 'superadmin', 'group_level' => 80]]]);
        foreach ([self::FOREIGN, self::UNOWNED, self::MISSING] as $uuid) {
            $this->putJson(self::URL.'/'.$uuid, ['name' => 'changed'])->assertNotFound();
            $this->postJson(self::URL.'/bulk-delete', ['items' => [$uuid]])->assertNotFound();
            $this->unchanged();
            $this->assertSame(0, DB::transactionLevel());
        }
        $this->putJson(self::URL.'/invalid', ['name' => 'changed'])->assertNotFound();
    }

    public function test_bulk_delete_preflights_every_location_before_running_delete_hooks(): void
    {
        $this->permissions('delete');
        $deletions = 0;
        Event::listen('eloquent.deleting: '.Location::class, function () use (&$deletions) {
            $deletions++;
        });
        foreach ([self::FOREIGN, self::UNOWNED, self::MISSING] as $uuid) {
            $this->postJson(self::URL.'/bulk-delete', ['items' => [self::LOCAL, $uuid]])->assertNotFound();
            $this->assertSame(0, $deletions);
            $this->unchanged();
        }
    }

    public function test_bulk_delete_validates_uuid_array_before_changing_rows(): void
    {
        $this->permissions('delete');
        foreach ([null, [], 'invalid', ['invalid'], [self::LOCAL, self::LOCAL]] as $items) {
            $this->postJson(self::URL.'/bulk-delete', ['items' => $items])->assertUnprocessable();
            $this->unchanged();
        }
    }

    public function test_authorized_deletion_removes_only_selected_location_and_its_assignment_links(): void
    {
        $this->permissions('delete');
        $this->postJson(self::URL.'/bulk-delete', ['items' => [self::LOCAL]])->assertOk();
        $this->assertDatabaseMissing('locations', ['location_uuid' => self::LOCAL]);
        $this->assertDatabaseMissing('locationables', ['location_uuid' => self::LOCAL]);
        foreach ([self::FOREIGN, self::UNOWNED] as $uuid) {
            $this->assertDatabaseHas('locations', ['location_uuid' => $uuid, 'name' => 'original']);
            $this->assertDatabaseHas('locationables', ['location_uuid' => $uuid]);
        }
    }

    public function test_failed_deletion_rolls_back_assignment_cleanup(): void
    {
        $this->permissions('delete');
        DB::statement("CREATE TRIGGER reject_location_delete BEFORE DELETE ON locations BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
        $this->postJson(self::URL.'/bulk-delete', ['items' => [self::LOCAL]])->assertStatus(500);
        $this->unchanged();
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_native_api_still_requires_cookie_authentication(): void
    {
        $this->permissions('delete');
        $this->withHeader('Authorization', 'Bearer synthetic-token')
            ->postJson(self::URL.'/bulk-delete', ['items' => [self::LOCAL]])->assertUnauthorized();
        $this->unchanged();
    }

    private function permissions(string ...$actions): void
    {
        $this->withSession(['permissions' => array_map(fn ($action) => (object) ['permission_name' => 'location_'.$action], $actions)]);
    }

    private function unchanged(): void
    {
        $this->assertSame(3, DB::table('locations')->count());
        $this->assertSame(3, DB::table('locationables')->count());
        $this->assertSame(0, DB::table('locations')->where('name', '!=', 'original')->count());
        $this->assertDatabaseHas('locations', ['location_uuid' => self::LOCAL, 'domain_uuid' => self::A]);
        $this->assertDatabaseHas('locations', ['location_uuid' => self::FOREIGN, 'domain_uuid' => self::B]);
    }
}
