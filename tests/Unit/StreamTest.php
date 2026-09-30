<?php

namespace Tests\Unit;

use App\Http\Controllers\StreamController;
use App\Http\Requests\SaveStreamRequest;
use App\Models\MusicStreams;
use App\Rules\StreamLocation;
use App\Services\StreamService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StreamTest extends TestCase
{
    private const ACCOUNT = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';
    private const OTHER = 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        session(['domain_uuid' => self::ACCOUNT]);
        $this->permissions(['stream_view', 'stream_add', 'stream_edit', 'stream_delete']);
        Schema::create('v_streams', function (Blueprint $table) {
            $table->uuid('stream_uuid')->primary();
            $table->uuid('domain_uuid')->nullable();
            $table->string('stream_name');
            $table->string('stream_location');
            $table->string('stream_enabled');
            $table->text('stream_description')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        parent::tearDown();
    }

    private function permissions(array $names): void
    {
        session(['permissions' => array_map(fn ($name) => (object) ['permission_name' => $name], $names)]);
    }

    private function stream(?string $domain = self::ACCOUNT, string $name = 'Radio'): MusicStreams
    {
        $item = new MusicStreams();
        $item->forceFill(['domain_uuid' => $domain, 'stream_name' => $name,
            'stream_location' => 'shouts://radio.example.test/live', 'stream_enabled' => 'true'])->save();
        return $item;
    }

    public function test_location_validation_preserves_exact_playback_address(): void
    {
        foreach (['shout://radio.test:8000/live.mp3', 'shouts://radio.test/live?token=a%20b', 'shouts://[::1]:8443/live', 'shout://radio.test/'] as $value) {
            $this->assertTrue(Validator::make(['location' => $value], ['location' => [new StreamLocation()]])->passes(), $value);
        }
        foreach (['https://radio.test/live', 'radio.test/live', 'shout://https://radio.test/live', 'shout://', 'shout://radio.test/live.m3u8', 'shouts://radio.test/list.pls', 'shout://radio.test/live.aac', 'shout://radio.test/a b', "shout://radio.test/a\nb", 'shout://radio.test/live#fragment'] as $value) {
            $this->assertTrue(Validator::make(['location' => $value], ['location' => [new StreamLocation()]])->fails(), $value);
        }
    }

    public function test_create_generates_uuid_and_preserves_location_without_fetching_it(): void
    {
        $location = 'shouts://radio.test:8443/live?token=abc%20def';
        $item = (new StreamService())->save(['stream_name' => 'Radio', 'stream_location' => $location, 'stream_enabled' => 'true']);
        $this->assertTrue(\Illuminate\Support\Str::isUuid($item->stream_uuid));
        $this->assertSame(self::ACCOUNT, $item->domain_uuid);
        $this->assertSame($location, $item->fresh()->stream_location);
    }

    public function test_visibility_is_current_account_plus_global_even_for_global_administrators(): void
    {
        $own = $this->stream();
        $global = $this->stream(null);
        $this->stream(self::OTHER);
        $this->permissions(['stream_view', 'stream_all']);
        $this->assertEqualsCanonicalizing([$own->stream_uuid, $global->stream_uuid], (new StreamService())->visible()->pluck('stream_uuid')->all());
    }

    public function test_global_mutation_is_denied_and_bulk_changes_are_atomic(): void
    {
        $own = $this->stream();
        $global = $this->stream(null);
        try {
            (new StreamService())->bulk([$own->stream_uuid, $global->stream_uuid], 'delete');
            $this->fail('Expected global authorization failure.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame(2, MusicStreams::count());
    }

    public function test_foreign_uuid_rejects_whole_bulk_request(): void
    {
        $own = $this->stream();
        $foreign = $this->stream(self::OTHER);
        try {
            (new StreamService())->bulk([$own->stream_uuid, $foreign->stream_uuid], 'disable');
            $this->fail('Expected scope failure.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        $this->assertSame('true', $own->fresh()->stream_enabled);
    }

    public function test_global_create_and_update_require_global_permission(): void
    {
        $this->expectException(HttpException::class);
        (new StreamService())->save(['domain_uuid' => null, 'stream_name' => 'Global', 'stream_location' => 'shout://radio.test/live', 'stream_enabled' => 'true']);
    }

    public function test_other_account_update_is_denied_even_with_global_permission(): void
    {
        $foreign = $this->stream(self::OTHER);
        $this->permissions(['stream_edit', 'stream_all']);
        $this->expectException(HttpException::class);
        (new StreamService())->save(['stream_name' => 'Changed'], $foreign);
    }

    public function test_global_copy_belongs_to_current_account_and_enable_disable_are_explicit(): void
    {
        $global = $this->stream(null);
        $service = new StreamService();
        $service->bulk([$global->stream_uuid], 'copy');
        $copy = MusicStreams::where('domain_uuid', self::ACCOUNT)->firstOrFail();
        $this->assertNotSame($copy->stream_uuid, $global->stream_uuid);
        $this->assertSame($global->stream_location, $copy->stream_location);
        $service->bulk([$copy->stream_uuid], 'disable');
        $service->bulk([$copy->stream_uuid], 'disable');
        $this->assertSame('false', $copy->fresh()->stream_enabled);
        $this->assertSame('true', $global->fresh()->stream_enabled);
        $service->bulk([$copy->stream_uuid], 'enable');
        $this->assertSame('true', $copy->fresh()->stream_enabled);
    }

    public function test_search_and_select_all_have_same_scope(): void
    {
        $own = $this->stream(self::ACCOUNT, 'Jazz');
        $global = $this->stream(null, 'Jazz global');
        $this->stream(self::OTHER, 'Jazz foreign');
        $this->stream(self::ACCOUNT, 'Rock');
        $controller = new StreamController(new StreamService());
        $request = Request::create('/api/streams/data', 'GET', ['filter' => ['search' => 'jAzZ'], 'sort' => '-stream_name']);
        $page = $controller->getData($request);
        $this->assertSame(2, $page->total());
        $this->assertSame($global->stream_uuid, $page->items()[0]['stream_uuid']);
        $selected = $controller->selectAll($request)->getData(true)['items'];
        $this->assertEqualsCanonicalizing([$own->stream_uuid, $global->stream_uuid], $selected);
    }

    public function test_bulk_endpoint_checks_action_permission(): void
    {
        $own = $this->stream();
        $this->permissions(['stream_view']);
        $this->expectException(HttpException::class);
        (new StreamController(new StreamService()))->bulkAction(Request::create('/api/streams/bulk-action', 'POST', ['action' => 'delete', 'items' => [$own->stream_uuid]]));
    }

    public function test_form_options_do_not_persist_drafts(): void
    {
        (new StreamController(new StreamService()))->getItemOptions(Request::create('/api/streams/item-options', 'POST'));
        $this->assertSame(0, MusicStreams::count());
    }

    public function test_module_status_distinguishes_loaded_stopped_and_unknown_responses(): void
    {
        foreach ([[true, 'running'], ['true', 'running'], [false, 'stopped'], ['false', 'stopped'],
            [null, 'unknown'], ['-ERR unavailable', 'unknown'], [[], 'unknown']] as [$reply, $expected]) {
            $esl = \Mockery::mock(\App\Services\FreeswitchEslService::class);
            $esl->shouldReceive('isConnected')->once()->andReturn(true);
            $esl->shouldReceive('executeCommand')->once()->with('module_exists mod_shout')->andReturn($reply);
            $this->app->instance(\App\Services\FreeswitchEslService::class, $esl);
            $response = (new StreamController(new StreamService()))->moduleStatus();
            $this->assertSame($expected, $response->getData(true)['status']);
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_disconnected_module_status_is_unknown_without_sending_a_command(): void
    {
        $esl = \Mockery::mock(\App\Services\FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->once()->andReturn(false);
        $esl->shouldNotReceive('executeCommand');
        $this->app->instance(\App\Services\FreeswitchEslService::class, $esl);
        $this->assertSame('unknown', (new StreamController(new StreamService()))->moduleStatus()->getData(true)['status']);
    }

    public function test_module_status_requires_stream_view_permission(): void
    {
        $this->permissions([]);
        $this->expectException(HttpException::class);
        (new StreamController(new StreamService()))->moduleStatus();
    }

    public function test_all_sound_selectors_exclude_disabled_global_and_foreign_streams(): void
    {
        foreach ([
            'v_music_on_hold' => ['domain_uuid', 'music_on_hold_name'],
            'v_recordings' => ['domain_uuid', 'recording_filename', 'recording_name'],
            'v_default_settings' => ['default_setting_category', 'default_setting_subcategory', 'default_setting_enabled', 'default_setting_value'],
            'v_vars' => ['var_uuid', 'var_category', 'var_enabled', 'var_name', 'var_value'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) $table->string($column)->nullable();
            });
        }
        $this->stream(self::ACCOUNT, 'Own');
        $this->stream(null, 'Global');
        $disabled = $this->stream(null, 'Disabled global');
        $disabled->stream_enabled = 'false';
        $disabled->save();
        $this->stream(self::OTHER, 'Foreign');
        foreach (['getMusicOnHoldCollection', 'getRingBackTonesCollection', 'getRingBackTonesCollectionGrouped'] as $helper) {
            $result = json_encode($helper(self::ACCOUNT));
            $this->assertStringContainsString('Own', $result, $helper);
            $this->assertStringContainsString('Global', $result, $helper);
            $this->assertStringNotContainsString('Disabled global', $result, $helper);
            $this->assertStringNotContainsString('Foreign', $result, $helper);
        }
    }

    public function test_legacy_location_can_remain_unchanged_but_new_location_is_validated(): void
    {
        $item = $this->stream();
        $item->stream_location = 'local_stream://custom';
        $item->save();
        $request = SaveStreamRequest::create('/api/streams/'.$item->stream_uuid, 'PUT', ['stream_location' => $item->stream_location]);
        $route = new \Illuminate\Routing\Route('PUT', '/api/streams/{stream}', fn () => null);
        $route->bind($request);
        $route->setParameter('stream', $item);
        $request->setRouteResolver(fn () => $route);
        $this->assertTrue(Validator::make(['stream_location' => $item->stream_location], ['stream_location' => $request->rules()['stream_location']])->passes());
        $request->merge(['stream_location' => 'https://radio.test/live']);
        $this->assertTrue(Validator::make($request->all(), ['stream_location' => $request->rules()['stream_location']])->fails());
    }
}
