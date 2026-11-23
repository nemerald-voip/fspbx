<?php

namespace Tests\Feature;

use App\Http\Controllers\CdrsController;
use App\Http\Middleware\CheckFusionPBXLogin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NoStoreByDefault;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\{Cache, DB, File, Http, Schema};
use Tests\TestCase;

class ConferenceSessionTest extends TestCase
{
    private const ACCOUNT = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const OTHER_ACCOUNT = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const ROOM = '11111111-1111-4111-8111-111111111111';
    private const OTHER_ROOM = '22222222-2222-4222-8222-222222222222';
    private const FOREIGN_ROOM = '33333333-3333-4333-8333-333333333333';
    private const SESSION = '44444444-4444-4444-8444-444444444444';
    private const OTHER_SESSION = '55555555-5555-4555-8555-555555555555';
    private const FOREIGN_SESSION = '66666666-6666-4666-8666-666666666666';
    private const DETAIL = '77777777-7777-4777-8777-777777777777';
    private const FOREIGN_DETAIL = '88888888-8888-4888-8888-888888888888';
    private const CALL = '99999999-9999-4999-8999-999999999999';
    private string $recordings;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'conference_test',
            'database.connections.conference_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'session.driver' => 'array', 'logging.default' => 'null',
        ]);
        DB::purge('conference_test');
        Http::preventStrayRequests();
        $this->withoutMiddleware([CheckFusionPBXLogin::class, NoStoreByDefault::class, HandleInertiaRequests::class, ThrottleRequests::class]);
        $this->actingAs((new User)->forceFill(['user_uuid' => self::DETAIL, 'domain_uuid' => self::ACCOUNT, 'user_enabled' => 'true', 'username' => 'tester']));
        $this->withSession(['domain_uuid' => self::ACCOUNT, 'domain_name' => 'a.invalid', 'domain.language.code' => 'en-us', 'permissions' => []]);
        Cache::put(self::ACCOUNT . '_timeZone', 'America/Los_Angeles');

        Schema::create('v_conference_rooms', function (Blueprint $table) {
            $table->uuid('conference_room_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->text('conference_room_name');
            $table->text('description')->nullable();
        });
        Schema::create('v_conference_sessions', function (Blueprint $table) {
            $table->uuid('conference_session_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->uuid('meeting_uuid');
            $table->text('profile');
            $table->text('recording')->nullable();
            $table->bigInteger('start_epoch');
            $table->bigInteger('end_epoch')->nullable();
        });
        Schema::create('v_conference_session_details', function (Blueprint $table) {
            $table->uuid('conference_session_detail_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->uuid('conference_session_uuid');
            $table->uuid('uuid')->nullable();
            $table->text('caller_id_name');
            $table->text('caller_id_number');
            $table->text('moderator');
            $table->text('network_addr');
            $table->bigInteger('start_epoch');
            $table->bigInteger('end_epoch')->nullable();
        });
        Schema::create('v_xml_cdr', function (Blueprint $table) {
            $table->uuid('xml_cdr_uuid')->primary();
            $table->uuid('domain_uuid');
            $table->uuid('extension_uuid')->nullable();
        });
        foreach ([self::ROOM => self::ACCOUNT, self::OTHER_ROOM => self::ACCOUNT, self::FOREIGN_ROOM => self::OTHER_ACCOUNT] as $room => $domain) {
            DB::table('v_conference_rooms')->insert(['conference_room_uuid' => $room, 'domain_uuid' => $domain, 'conference_room_name' => 'Meeting room']);
        }
        foreach ([self::SESSION => self::ROOM, self::OTHER_SESSION => self::OTHER_ROOM, self::FOREIGN_SESSION => self::FOREIGN_ROOM] as $session => $room) {
            DB::table('v_conference_sessions')->insert([
                'conference_session_uuid' => $session, 'domain_uuid' => $room === self::FOREIGN_ROOM ? self::OTHER_ACCOUNT : self::ACCOUNT,
                'meeting_uuid' => $room, 'profile' => 'default', 'start_epoch' => 1700000000, 'end_epoch' => 1700000060,
            ]);
        }
        foreach ([self::DETAIL => self::ACCOUNT, self::FOREIGN_DETAIL => self::OTHER_ACCOUNT] as $detail => $domain) {
            // Even an inconsistent child with the same session UUID must remain tenant-scoped.
            DB::table('v_conference_session_details')->insert([
                'conference_session_detail_uuid' => $detail, 'domain_uuid' => $domain, 'conference_session_uuid' => self::SESSION,
                'uuid' => self::CALL, 'caller_id_name' => 'Alice', 'caller_id_number' => '1001', 'moderator' => 'true',
                'network_addr' => '192.0.2.1', 'start_epoch' => 1700000000, 'end_epoch' => 1700000060,
            ]);
        }
        $this->recordings = sys_get_temp_dir() . '/fspbx-conference-' . bin2hex(random_bytes(8));
        File::makeDirectory($this->recordings . '/a.invalid', 0700, true);
        File::makeDirectory($this->recordings . '/b.invalid', 0700, true);
        File::put($this->recordings . '/a.invalid/session.wav', '0123456789');
        File::put($this->recordings . '/b.invalid/secret.wav', 'other account');
        config(['filesystems.disks.recordings.root' => $this->recordings]);
        DB::table('v_conference_sessions')->where('conference_session_uuid', self::SESSION)->update(['recording' => $this->recordings . '/a.invalid/session.wav']);
    }

    protected function tearDown(): void
    {
        DB::disconnect('conference_test');
        if (isset($this->recordings)) File::deleteDirectory($this->recordings);
        parent::tearDown();
    }

    public function test_history_and_participants_are_scoped_to_the_selected_room_and_account(): void
    {
        $this->permissions('conference_session_view');
        $this->getJson($this->url('/data'))->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.conference_session_uuid', self::SESSION)
            ->assertJsonPath('data.0.stream_url', null)->assertJsonPath('data.0.download_url', null)
            ->assertJsonMissingPath('data.0.recording');
        $this->getJson($this->url('/' . self::SESSION . '/participants') . '?filter[search]=ALICE')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.moderator', true)->assertJsonPath('data.0.call_details_url', null);
        $this->getJson($this->url('/data') . '?filter[profile]=missing')->assertOk()->assertJsonPath('total', 0);
        $this->getJson($this->url('/data') . '?sort=recording')->assertStatus(400);
        $this->getJson($this->url('/data') . '?filter[domain_uuid]=' . self::OTHER_ACCOUNT)->assertStatus(400);
    }

    public function test_native_pages_render_with_room_context_and_preserve_back_links(): void
    {
        $this->permissions('conference_session_view', 'conference_room_view');
        $page = '/conference-rooms/' . self::ROOM . '/sessions';
        $this->get($page, ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('component', 'ConferenceSessions')
            ->assertJsonPath('props.room.conference_room_uuid', self::ROOM)->assertJsonPath('props.conference_session', null)
            ->assertJsonPath('props.timezone', 'America/Los_Angeles');
        $this->get($page . '/' . self::SESSION, ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('component', 'ConferenceSessions')
            ->assertJsonPath('props.conference_session.conference_session_uuid', self::SESSION)->assertJsonPath('props.routes.sessions', url($page));
    }

    public function test_all_history_actions_require_view_permission_and_valid_account_context(): void
    {
        $this->permissions('conference_session_delete', 'conference_session_play', 'call_recording_download');
        foreach (['/data', '/' . self::SESSION . '/participants', '/' . self::SESSION . '/recording/stream', '/' . self::SESSION . '/recording/download'] as $path) {
            $this->getJson($this->url($path))->assertForbidden();
        }
        $this->postJson($this->url('/bulk-delete'), ['items' => [self::SESSION]])->assertForbidden();
        $this->permissions('conference_session_view');
        foreach ([null, '', 'invalid'] as $domain) {
            $this->withSession(['domain_uuid' => $domain]);
            $this->getJson($this->url('/data'))->assertForbidden();
        }
    }

    public function test_foreign_rooms_and_sessions_cannot_be_read_streamed_or_deleted(): void
    {
        $this->permissions('conference_session_view', 'conference_session_delete', 'conference_session_play');
        $this->getJson('/api/conference-rooms/' . self::FOREIGN_ROOM . '/sessions/data')->assertNotFound();
        foreach ([self::OTHER_SESSION, self::FOREIGN_SESSION] as $session) {
            foreach (['participants', 'recording/stream', 'recording/download'] as $action) {
                $this->getJson($this->url('/' . $session . '/' . $action))->assertNotFound();
            }
            $this->postJson($this->url('/bulk-delete'), ['items' => [self::SESSION, $session]])->assertNotFound();
        }
        $this->assertDatabaseCount('v_conference_sessions', 3);
        $this->assertDatabaseCount('v_conference_session_details', 2);
    }

    public function test_deletion_validates_input_and_removes_only_own_history_without_removing_audio(): void
    {
        $this->permissions('conference_session_view');
        $this->postJson($this->url('/bulk-delete'), ['items' => [self::SESSION]])->assertForbidden();
        $this->permissions('conference_session_view', 'conference_session_delete');
        foreach ([[], ['invalid'], [self::SESSION, self::SESSION]] as $items) {
            $this->postJson($this->url('/bulk-delete'), ['items' => $items])->assertUnprocessable();
        }
        $this->postJson($this->url('/bulk-delete'), ['items' => [self::SESSION]])->assertOk();
        $this->assertDatabaseMissing('v_conference_sessions', ['conference_session_uuid' => self::SESSION]);
        $this->assertDatabaseMissing('v_conference_session_details', ['conference_session_detail_uuid' => self::DETAIL]);
        $this->assertDatabaseHas('v_conference_session_details', ['conference_session_detail_uuid' => self::FOREIGN_DETAIL]);
        $this->assertDatabaseCount('v_conference_sessions', 2);
        $this->assertFileExists($this->recordings . '/a.invalid/session.wav');
    }

    public function test_recording_permissions_and_http_range_requests(): void
    {
        $stream = $this->url('/' . self::SESSION . '/recording/stream');
        $download = $this->url('/' . self::SESSION . '/recording/download');
        $this->permissions('conference_session_view');
        $this->getJson($stream)->assertForbidden();
        $this->getJson($download)->assertForbidden();
        $this->permissions('conference_session_view', 'call_recording_download');
        $this->getJson($stream)->assertForbidden();
        $this->get($download)->assertOk()->assertDownload('session.wav');
        $this->permissions('conference_session_view', 'conference_session_play');
        $this->get($stream, ['Range' => 'bytes=2-5'])->assertStatus(206)->assertHeader('Content-Range', 'bytes 2-5/10')
            ->assertHeader('Content-Type', 'audio/wav')->assertHeader('Content-Disposition', 'inline; filename=session.wav');
        $this->getJson($this->url('/data'))->assertOk()->assertJsonPath('data.0.stream_url', url($stream));
    }

    public function test_recording_resolution_supports_old_extensionless_rows_and_rejects_path_escape(): void
    {
        $this->permissions('conference_session_view', 'conference_session_play');
        $url = $this->url('/' . self::SESSION . '/recording/download');
        $this->setRecording('session');
        $this->get($url)->assertOk()->assertDownload('session.wav');
        symlink($this->recordings . '/b.invalid/secret.wav', $this->recordings . '/a.invalid/link.wav');
        File::put($this->recordings . '/a.invalid/private.txt', 'not audio');
        foreach (['../b.invalid/secret.wav', 'link.wav', 'private.txt', 'missing.wav', $this->recordings . '/b.invalid/secret.wav'] as $path) {
            $this->setRecording($path);
            $this->getJson($url)->assertNotFound();
            $this->getJson($this->url('/data'))->assertOk()->assertJsonPath('data.0.download_url', null);
        }
    }

    public function test_call_details_require_permissions_and_a_matching_account_and_participant(): void
    {
        $url = $this->url('/' . self::SESSION . '/participants/' . self::DETAIL . '/call');
        $this->permissions('conference_session_view');
        $this->getJson($url)->assertForbidden();
        $this->permissions('conference_session_view', 'conference_session_details', 'xml_cdr_details');
        $this->getJson($this->url('/' . self::SESSION . '/participants/' . self::FOREIGN_DETAIL . '/call'))->assertNotFound();
        DB::table('v_xml_cdr')->insert(['xml_cdr_uuid' => self::CALL, 'domain_uuid' => self::OTHER_ACCOUNT]);
        $this->getJson($url)->assertNotFound();
        DB::table('v_xml_cdr')->where('xml_cdr_uuid', self::CALL)->update(['domain_uuid' => self::ACCOUNT]);
        $controller = $this->mock(CdrsController::class);
        $controller->shouldReceive('getItemOptions')->once()->andReturnUsing(function () {
            $this->assertSame(self::CALL, request('item_uuid'));
            return ['item' => ['xml_cdr_uuid' => self::CALL]];
        });
        $this->getJson($url)->assertOk()->assertJsonPath('item.xml_cdr_uuid', self::CALL);
        $this->permissions('conference_session_view', 'conference_session_details', 'xml_cdr_details', 'xml_cdr_view_self_records');
        $this->getJson($url)->assertForbidden();
    }

    private function url(string $suffix): string
    {
        return '/api/conference-rooms/' . self::ROOM . '/sessions' . $suffix;
    }

    private function setRecording(string $path): void
    {
        DB::table('v_conference_sessions')->where('conference_session_uuid', self::SESSION)->update(['recording' => $path]);
    }

    private function permissions(string ...$names): void
    {
        $this->withSession(['permissions' => collect($names)->map(fn ($name) => (object) ['permission_name' => $name])->all()]);
    }
}
