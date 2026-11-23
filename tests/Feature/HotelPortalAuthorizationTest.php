<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HotelRoomService;
use App\Services\TigerTmsApiClient;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Http, Schema};
use Mockery;
use Tests\TestCase;

class HotelPortalAuthorizationTest extends TestCase
{
    private const A = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const B = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const ROOM = '11111111-1111-4111-8111-111111111111';
    private const OTHER = '22222222-2222-4222-8222-222222222222';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'hotel_test',
            'database.connections.hotel_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'session.driver' => 'array', 'cache.default' => 'array', 'logging.default' => 'null',
        ]);
        Http::preventStrayRequests();
        foreach ([
            'hotel_rooms' => ['uuid', 'domain_uuid', 'extension_uuid', 'room_name', 'created_at', 'updated_at'],
            'hotel_room_status' => ['uuid', 'domain_uuid', 'hotel_room_uuid', 'occupancy_status', 'housekeeping_status', 'guest_first_name', 'guest_last_name', 'arrival_date', 'departure_date', 'created_at', 'updated_at'],
            'hotel_housekeeping_definitions' => ['uuid', 'domain_uuid', 'code', 'label', 'enabled', 'created_at', 'updated_at'],
            'v_extensions' => ['extension_uuid', 'domain_uuid', 'extension', 'directory_first_name', 'directory_last_name', 'effective_caller_id_name', 'do_not_disturb'],
            'extension_advanced_settings' => ['extension_advanced_setting_uuid', 'extension_uuid', 'suspended'],
            'v_voicemails' => ['voicemail_uuid', 'domain_uuid', 'voicemail_id', 'greeting_id', 'voicemail_enabled'],
            'wakeup_calls' => ['uuid', 'domain_uuid', 'extension_uuid'],
            'v_domain_settings' => ['domain_setting_uuid', 'domain_uuid', 'domain_setting_category', 'domain_setting_name', 'domain_setting_subcategory', 'domain_setting_value', 'domain_setting_enabled', 'domain_setting_description'],
            'v_default_settings' => ['default_setting_category', 'default_setting_subcategory', 'default_setting_value', 'default_setting_enabled'],
        ] as $table => $columns) {
            Schema::create($table, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
            });
        }
        foreach ([self::ROOM => self::A, self::OTHER => self::B] as $id => $domain) {
            DB::table('hotel_rooms')->insert(['uuid' => $id, 'domain_uuid' => $domain, 'room_name' => '101', 'extension_uuid' => $id]);
            DB::table('v_extensions')->insert(['extension_uuid' => $id, 'domain_uuid' => $domain, 'extension' => '101']);
            DB::table('hotel_housekeeping_definitions')->insert(['uuid' => $id, 'domain_uuid' => $domain, 'code' => 1, 'label' => 'Clean', 'enabled' => true]);
        }
    }

    private function signIn(string ...$permissions): void
    {
        $this->actingAs((new User)->forceFill(['user_uuid' => self::ROOM, 'domain_uuid' => self::A, 'user_enabled' => 'true']));
        $this->withSession(['domain_uuid' => self::A, 'permissions' => array_map(fn ($name) => (object) ['permission_name' => $name], $permissions)]);
    }

    public static function actions(): array
    {
        return [
            ['GET', 'hotel-rooms', 'hotel_rooms_list_view', []],
            ['POST', 'hotel-rooms', 'hotel_rooms_create', []],
            ['POST', 'hotel-rooms/bulk-store', 'hotel_rooms_create', []],
            ['PUT', 'hotel-rooms/'.self::ROOM, 'hotel_rooms_update', []],
            ['POST', 'hotel-rooms/item-options', 'hotel_rooms_create', []],
            ['POST', 'hotel-rooms/item-options', 'hotel_rooms_update', ['item_uuid' => self::ROOM]],
            ['POST', 'hotel-rooms/bulk-delete', 'hotel_rooms_delete', []],
            ['GET', 'hotel-room-status', 'hotel_room_status_list_view', []],
            ['POST', 'hotel-room-status', 'hotel_room_status_update', []],
            ['POST', 'hotel-room-status/item-options', 'hotel_room_status_update', []],
            ['POST', 'hotel-room-status/item-options', 'hotel_room_status_list_view', ['item_uuid' => self::ROOM]],
            ['POST', 'hotel-room-status/bulk-delete', 'hotel_room_status_update', []],
            ['POST', 'housekeeping', 'hotel_housekeeping_manage', []],
            ['POST', 'housekeeping/item-options', 'hotel_housekeeping_manage', []],
            ['POST', 'housekeeping/default-codes', 'hotel_housekeeping_manage', []],
            ['PUT', 'account-settings/pms-provider', 'hotel_pms_provider_manage', []],
        ];
    }

    /** @dataProvider actions */
    public function test_actions_require_the_matching_permission_and_a_valid_session_account(string $method, string $path, string $permission, array $payload): void
    {
        $all = array_unique(array_column(self::actions(), 2));
        $this->signIn('account_settings_list_view', ...array_diff($all, [$permission]));
        $this->json($method, '/api/'.$path, $payload)->assertForbidden();
        $this->signIn('account_settings_list_view', ...$all);
        $this->withSession(['domain_uuid' => null]);
        $this->json($method, '/api/'.$path, $payload)->assertForbidden();
    }

    public function test_rooms_and_guest_records_are_scoped_even_with_an_old_account_filter(): void
    {
        $this->signIn('hotel_rooms_list_view', 'hotel_room_status_list_view', 'hotel_rooms_update', 'hotel_room_status_update');
        DB::table('hotel_room_status')->insert(['uuid' => self::OTHER, 'hotel_room_uuid' => self::OTHER, 'domain_uuid' => self::B, 'guest_first_name' => 'ForeignGuest']);
        foreach (['hotel-rooms', 'hotel-room-status'] as $resource) {
            foreach (['', '?filter[domain_uuid]='.self::B] as $query) {
                $this->getJson('/api/'.$resource.$query)->assertOk()->assertJsonCount(1, 'data')
                    ->assertJsonPath('data.0.uuid', self::ROOM)->assertDontSee('ForeignGuest');
            }
            $this->postJson('/api/'.$resource.'/item-options', ['item_uuid' => self::OTHER])->assertNotFound();
            $this->postJson('/api/'.$resource.'/item-options', ['item_uuid' => self::ROOM])->assertOk()->assertJsonPath('item.uuid', self::ROOM);
        }
    }

    public function test_room_writes_assign_the_session_account_and_validate_extension_ownership(): void
    {
        $this->signIn('hotel_rooms_create', 'hotel_rooms_update', 'hotel_rooms_delete');
        $this->postJson('/api/hotel-rooms', ['room_name' => 'New', 'domain_uuid' => self::B])->assertCreated();
        $this->assertDatabaseHas('hotel_rooms', ['room_name' => 'New', 'domain_uuid' => self::A]);
        $this->postJson('/api/hotel-rooms', ['room_name' => 'Invalid', 'extension_uuid' => self::OTHER])->assertJsonValidationErrors('extension_uuid');
        $this->postJson('/api/hotel-rooms/bulk-store', ['extensions' => [self::ROOM, self::OTHER]])->assertJsonValidationErrors('extensions.1');
        $this->postJson('/api/hotel-rooms/bulk-store', ['extensions' => [self::ROOM], 'domain_uuid' => self::B])->assertCreated();
        $this->assertSame(2, DB::table('hotel_rooms')->where('domain_uuid', self::A)->where('extension_uuid', self::ROOM)->count());
        $this->putJson('/api/hotel-rooms/'.self::OTHER, ['room_name' => 'Changed'])->assertNotFound();
        $this->putJson('/api/hotel-rooms/'.self::ROOM, ['room_name' => 'Changed', 'domain_uuid' => self::B])->assertOk();
        $this->assertDatabaseHas('hotel_rooms', ['uuid' => self::ROOM, 'domain_uuid' => self::A, 'room_name' => 'Changed']);
        $this->postJson('/api/hotel-rooms/bulk-delete', ['items' => [self::ROOM, self::OTHER]])->assertNotFound();
        $this->assertDatabaseHas('hotel_rooms', ['uuid' => self::ROOM]);
        $this->postJson('/api/hotel-rooms/bulk-delete', ['items' => [self::ROOM]])->assertOk();
        $this->assertDatabaseMissing('hotel_rooms', ['uuid' => self::ROOM]);
        $this->assertDatabaseHas('hotel_rooms', ['uuid' => self::OTHER]);
    }

    public function test_existing_mismatched_status_is_neither_displayed_nor_changed_by_guest_operations(): void
    {
        DB::table('hotel_room_status')->insert(['uuid' => self::OTHER, 'hotel_room_uuid' => self::ROOM, 'domain_uuid' => self::B, 'guest_first_name' => 'ForeignGuest']);
        $this->signIn('hotel_room_status_list_view', 'hotel_room_status_update');
        $this->getJson('/api/hotel-room-status')->assertOk()->assertJsonPath('data.0.status', null);
        $this->postJson('/api/hotel-room-status/item-options', ['item_uuid' => self::ROOM])->assertOk()->assertJsonPath('item.status', null);
        $this->postJson('/api/hotel-room-status', ['uuid' => self::ROOM, 'guest_first_name' => 'LocalGuest'])->assertCreated();
        $this->getJson('/api/hotel-room-status')->assertOk()->assertJsonPath('data.0.status.guest_first_name', 'LocalGuest');
        $this->postJson('/api/hotel-room-status/bulk-delete', ['items' => [self::ROOM]])->assertOk();
        $this->assertDatabaseHas('hotel_room_status', ['uuid' => self::OTHER, 'domain_uuid' => self::B, 'guest_first_name' => 'ForeignGuest']);
    }

    public function test_unauthorized_guest_changes_stop_before_the_service(): void
    {
        $this->signIn('hotel_room_status_update');
        $service = $this->mock(HotelRoomService::class);
        $service->shouldNotReceive('checkIn', 'checkOut');
        $this->postJson('/api/hotel-room-status', ['uuid' => self::OTHER])->assertNotFound();
        $this->postJson('/api/hotel-room-status', ['uuid' => self::ROOM, 'housekeeping_status' => self::OTHER])->assertJsonValidationErrors('housekeeping_status');
        $this->postJson('/api/hotel-room-status/bulk-delete', ['items' => [self::ROOM, self::OTHER]])->assertNotFound();
        $this->postJson('/api/hotel-room-status/bulk-delete', ['items' => [self::ROOM, self::ROOM]])->assertUnprocessable();
    }

    public function test_housekeeping_and_provider_changes_apply_only_to_the_current_account(): void
    {
        $this->signIn('hotel_housekeeping_manage', 'hotel_pms_provider_manage', 'account_settings_list_view');
        $this->postJson('/api/housekeeping', ['domain_uuid' => self::B, 'housekeeping_options' => [['code' => 2, 'label' => 'Ready']]])->assertCreated();
        $this->assertDatabaseHas('hotel_housekeeping_definitions', ['domain_uuid' => self::A, 'label' => 'Ready']);
        $this->assertDatabaseHas('hotel_housekeeping_definitions', ['uuid' => self::OTHER]);
        $this->putJson('/api/account-settings/pms-provider', ['domain_uuid' => self::B, 'pms_provider' => 'tigertms'])->assertOk();
        $this->assertDatabaseHas('v_domain_settings', ['domain_uuid' => self::A, 'domain_setting_value' => 'tigertms']);
        $this->assertDatabaseMissing('v_domain_settings', ['domain_uuid' => self::B]);
    }

    public function test_default_hotel_permissions_only_go_to_superadmin(): void
    {
        foreach ([
            'v_groups' => ['group_uuid', 'group_name'],
            'v_permissions' => ['permission_uuid', 'application_name', 'permission_name', 'insert_date'],
            'v_group_permissions' => ['group_permission_uuid', 'group_uuid', 'group_name', 'permission_name', 'permission_assigned', 'permission_protected', 'insert_date'],
        ] as $table => $columns) {
            Schema::create($table, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
            });
        }
        foreach (['superadmin', 'admin', 'user'] as $group) {
            DB::table('v_groups')->insert(['group_uuid' => $group, 'group_name' => $group]);
        }
        $seeder = new DatabaseSeeder();
        foreach (['createPermissions', 'createGroupPermissions'] as $method) {
            (new \ReflectionMethod($seeder, $method))->invoke($seeder);
        }
        foreach (array_unique(array_column(self::actions(), 2)) as $permission) {
            $this->assertDatabaseHas('v_permissions', ['permission_name' => $permission]);
            $this->assertSame(['superadmin' => 'true'], DB::table('v_group_permissions')->where('permission_name', $permission)
                ->pluck('permission_assigned', 'group_name')->all());
        }
    }

    public function test_portal_check_in_and_checkout_still_notify_tigertms_for_the_room_account(): void
    {
        $this->signIn('hotel_room_status_update');
        DB::table('v_domain_settings')->insert(['domain_uuid' => self::A, 'domain_setting_subcategory' => 'pms_provider', 'domain_setting_value' => 'tigertms', 'domain_setting_enabled' => 'true']);
        $client = $this->mock(TigerTmsApiClient::class);
        $client->shouldReceive('checkIn')->once()->with(self::A, '101', Mockery::on(fn ($data) => $data['guests'][0]['firstname'] === 'Visitor'));
        $client->shouldReceive('checkOut')->once()->with(self::A, '101');
        $this->postJson('/api/hotel-room-status', ['uuid' => self::ROOM, 'guest_first_name' => 'Visitor', 'housekeeping_status' => self::ROOM])->assertCreated();
        $this->assertDatabaseHas('hotel_room_status', ['domain_uuid' => self::A, 'hotel_room_uuid' => self::ROOM, 'guest_first_name' => 'Visitor']);
        $this->postJson('/api/hotel-room-status/bulk-delete', ['items' => [self::ROOM]])->assertOk();
        $this->assertDatabaseMissing('hotel_room_status', ['hotel_room_uuid' => self::ROOM]);
    }

    public static function providers(): array
    {
        return [['charpms'], ['tigertms']];
    }

    /** @dataProvider providers */
    public function test_pms_webhooks_check_in_and_out_in_the_resolved_account_without_a_portal_session(string $provider): void
    {
        $this->assertNull(session('domain_uuid'));
        $this->assertGuest();
        config(['queue.default' => 'sync']);
        Schema::create('webhook_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('url');
            $table->text('headers');
            $table->text('payload');
            $table->text('exception')->nullable();
            $table->timestamps();
        });
        $this->mock(TigerTmsApiClient::class)->shouldNotReceive('checkIn', 'checkOut');
        DB::table('v_domain_settings')->insert(['domain_uuid' => self::B, 'domain_setting_subcategory' => 'pms_provider', 'domain_setting_value' => $provider, 'domain_setting_enabled' => 'true']);
        DB::table('hotel_room_status')->insert(['uuid' => self::ROOM, 'domain_uuid' => self::A, 'hotel_room_uuid' => self::ROOM, 'guest_first_name' => 'Existing']);

        if ($provider === 'charpms') {
            Schema::create('v_users', function (Blueprint $table) {
                $table->uuid('user_uuid')->primary();
                $table->uuid('domain_uuid');
            });
            foreach (['users_adv_fields', 'v_user_settings'] as $table) {
                Schema::create($table, fn (Blueprint $schema) => $schema->uuid('user_uuid'));
            }
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuidMorphs('tokenable');
                $table->string('name');
                $table->string('token');
                $table->text('abilities');
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
            DB::table('v_users')->insert(['user_uuid' => self::OTHER, 'domain_uuid' => self::B]);
            $this->withToken(User::findOrFail(self::OTHER)->createToken('hotel test', ['char:webhook'])->plainTextToken);
        } else {
            $secret = random_bytes(32);
            config(['tigertms.webhook_secret' => bin2hex($secret)]);
        }

        foreach (['checkin', 'checkout'] as $action) {
            if ($provider === 'charpms') {
                $this->postJson('/api/pms/char', [
                    'action' => $action === 'checkin' ? 'CHKI' : 'CHKO', 'extension_id' => '101', 'name' => 'Visitor',
                ])->assertOk()->assertJsonPath('code', 0);
            } else {
                $payload = ['site' => self::B, 'eventType' => $action === 'checkin' ? 'GuestCheckedIn' : 'GuestCheckedOut', 'roomNumber' => '101', 'guests' => [['firstname' => 'Visitor']]];
                $timestamp = (string) now()->timestamp;
                $this->postJson('/api/pms/tigertms', $payload, [
                    'X-iLINK-Timestamp' => $timestamp,
                    'X-iLINK-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.json_encode($payload), $secret),
                ])->assertOk()->assertJsonPath('result', 'success');
            }
            if ($action === 'checkin') {
                $this->assertDatabaseHas('hotel_room_status', ['hotel_room_uuid' => self::OTHER, 'domain_uuid' => self::B, 'guest_first_name' => 'Visitor']);
            } else {
                $this->assertDatabaseMissing('hotel_room_status', ['hotel_room_uuid' => self::OTHER]);
            }
            $this->assertDatabaseHas('hotel_room_status', ['hotel_room_uuid' => self::ROOM, 'guest_first_name' => 'Existing']);
        }
        Http::assertNothingSent();
    }
}
