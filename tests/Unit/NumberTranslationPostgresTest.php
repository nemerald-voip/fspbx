<?php

namespace Tests\Unit;

use App\Http\Controllers\NumberTranslationController;
use App\Http\Middleware\TrimStrings;
use App\Http\Requests\SaveNumberTranslationRequest;
use App\Models\NumberTranslation;
use App\Services\NumberTranslationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/** Opt-in tests against a disposable cluster; never connects to the app database. */
class NumberTranslationPostgresTest extends TestCase
{
    private bool $labReady = false;

    protected function setUp(): void
    {
        parent::setUp();
        $lab = getenv('FSPBX_NUMBER_TRANSLATION_LAB');
        if (! $lab || ! str_starts_with(realpath($lab) ?: '', '/tmp/fspbx-number-translations.')) {
            $this->markTestSkipped('Run tests/Unit/run-number-translation-lab.sh for disposable PostgreSQL tests.');
        }
        config(['database.default' => 'translation_lab', 'database.connections.translation_lab' => [
            'driver' => 'pgsql', 'host' => $lab, 'port' => 16543, 'database' => 'postgres',
            'username' => 'postgres', 'password' => '', 'charset' => 'utf8', 'prefix' => '',
            'schema' => 'public', 'sslmode' => 'disable',
        ]]);
        DB::purge('translation_lab');
        $this->assertSame($lab . '/db', DB::selectOne('show data_directory')->data_directory);
        $this->assertFalse(Schema::hasTable('v_default_settings'), 'Refusing an application database.');
        Schema::create('v_number_translations', function (Blueprint $table) {
            $table->uuid('number_translation_uuid')->primary();
            foreach (['name', 'description', 'enabled'] as $field) {
                $table->text('number_translation_' . $field)->nullable();
            }
        });
        Schema::create('v_number_translation_details', function (Blueprint $table) {
            $table->uuid('number_translation_detail_uuid')->primary();
            $table->uuid('number_translation_uuid')->references('number_translation_uuid')->on('v_number_translations');
            foreach (['regex', 'replace', 'order'] as $field) {
                $table->text('number_translation_detail_' . $field)->nullable();
            }
        });
        $this->labReady = true;
        session(['permissions' => array_map(fn ($name) => (object) ['permission_name' => 'number_translation_' . $name], ['view', 'add', 'edit', 'delete'])]);
    }

    protected function tearDown(): void
    {
        if ($this->labReady) {
            Schema::drop('v_number_translation_details');
            Schema::drop('v_number_translations');
            DB::purge('translation_lab');
        }
        parent::tearDown();
    }

    private function values(array $overrides = []): array
    {
        return array_replace([
            'name' => 'translation_test', 'description' => 'An & example', 'enabled' => true,
            'rules' => [
                ['regex' => '^\+(\d+)$', 'replace' => '$1'],
                ['regex' => '^(.*)$', 'replace' => '$1'],
            ],
        ], $overrides);
    }

    private function request(array $values, ?NumberTranslation $profile = null): SaveNumberTranslationRequest
    {
        $request = SaveNumberTranslationRequest::create('/api/system-settings/number-translations', $profile ? 'PUT' : 'POST', $values);
        $route = new Route($profile ? 'PUT' : 'POST', 'number-translations/{number_translation?}', []);
        $route->bind($request);
        if ($profile) $route->setParameter('number_translation', $profile);
        $request->setRouteResolver(fn () => $route);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        return $request;
    }

    public function test_profiles_and_ordered_rules_survive_create_edit_and_delete(): void
    {
        $service = new NumberTranslationService();
        $profile = $service->save($this->values());
        $this->assertTrue(Str::isUuid($profile->getKey()));
        $this->assertSame(['005', '010'], $profile->rules->pluck('number_translation_detail_order')->all());
        $this->assertSame(['^\+(\d+)$', '^(.*)$'], $profile->rules->pluck('number_translation_detail_regex')->all());
        [$first, $second] = $profile->rules->all();
        $first->update(['number_translation_detail_order' => '5']);
        $this->assertSame($first->getKey(), $profile->fresh()->rules->first()->getKey(), 'Unpadded legacy order is numeric.');
        $reordered = $service->save($this->values(['rules' => [
            ['uuid' => $second->getKey(), 'regex' => '^(.*)$', 'replace' => '$1'],
            ['uuid' => $first->getKey(), 'regex' => '^\+(\d+)$', 'replace' => '$1'],
        ]]), $profile);
        $this->assertSame([$second->getKey(), $first->getKey()], $reordered->rules->modelKeys(), 'List position sets the order.');
        $this->assertSame(['005', '010'], $reordered->rules->pluck('number_translation_detail_order')->all());
        $updated = $service->save($this->values([
            'name' => 'renamed', 'enabled' => false,
            'rules' => [['uuid' => $first->getKey(), 'regex' => '^(44|49)$', 'replace' => '']],
        ]), $profile);
        $this->assertSame('false', $updated->number_translation_enabled);
        $this->assertSame($first->getKey(), $updated->rules->sole()->getKey());
        $this->assertSame('', $updated->rules->sole()->number_translation_detail_replace);
        $this->assertSame('005', $updated->rules->sole()->number_translation_detail_order);
        $this->assertSame(1, DB::table('v_number_translation_details')->count());
        $service->delete($updated);
        $this->assertSame(0, NumberTranslation::count());
        $this->assertSame(0, DB::table('v_number_translation_details')->count());
    }

    public function test_lua_query_preserves_numeric_order_profile_boundaries_and_empty_profiles(): void
    {
        $uuid = fn (int $id) => sprintf('00000000-0000-0000-0000-%012d', $id);
        foreach ([
            [1, 'alpha', 'true'], [2, 'alpha', 'true'], [3, 'empty', 'true'],
            [4, 'disabled', 'false'], [5, 'unset', null],
        ] as [$id, $name, $enabled]) {
            DB::table('v_number_translations')->insert([
                'number_translation_uuid' => $uuid($id),
                'number_translation_name' => $name,
                'number_translation_enabled' => $enabled,
            ]);
        }
        foreach ([
            [1, 1, '10'], [2, 1, '5'], [3, 1, '005'], [4, 1, null],
            [5, 1, ''], [6, 1, 'legacy'], [7, 1, '999999999999999999999'],
            [8, 2, '0'], [9, 4, '0'], [10, 5, '0'],
        ] as [$id, $profile, $order]) {
            DB::table('v_number_translation_details')->insert([
                'number_translation_detail_uuid' => $uuid($id),
                'number_translation_uuid' => $uuid($profile),
                'number_translation_detail_regex' => '^rule' . $id . '$',
                'number_translation_detail_replace' => '${country}$1&"',
                'number_translation_detail_order' => $order,
            ]);
        }

        // Execute the handler's actual query on PostgreSQL, with independently
        // expected results for legacy text orders, duplicate names and nulls.
        $source = file_get_contents(resource_path('freeswitch_scripts/app/xml_handler/resources/scripts/configuration/translate.conf.lua'));
        $this->assertSame(1, preg_match('/local sql = \[\[(.*?)\]\]/s', $source, $matches));
        $rows = collect(DB::select($matches[1]));
        $this->assertSame([
            [$uuid(1), '^rule2$'], [$uuid(1), '^rule3$'], [$uuid(1), '^rule1$'],
            [$uuid(1), '^rule7$'], [$uuid(1), '^rule4$'], [$uuid(1), '^rule5$'],
            [$uuid(1), '^rule6$'], [$uuid(2), '^rule8$'], [$uuid(3), null],
        ], $rows->map(fn ($row) => [$row->number_translation_uuid, $row->number_translation_detail_regex])->all());
        $this->assertSame('${country}$1&"', $rows->first()->number_translation_detail_replace);
    }

    public function test_foreign_rule_uuid_is_rejected_and_cannot_be_moved_between_profiles(): void
    {
        $service = new NumberTranslationService();
        $other = $service->save($this->values(['name' => 'other']));
        $profile = $service->save($this->values());
        $values = $this->values(['name' => 'should_rollback', 'rules' => [
            ['uuid' => $other->rules->first()->getKey(), 'regex' => 'x', 'replace' => 'y'],
        ]]);
        $request = $this->request($values, $profile);
        $validator = Validator::make($values, $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('rules.0.uuid', $validator->errors()->toArray());
        try {
            $service->save($values, $profile);
            $this->fail('A foreign detail was accepted.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->assertSame('translation_test', $profile->fresh()->number_translation_name);
            $this->assertSame(2, $other->fresh()->rules->count());
        }
    }

    public function test_validation_accepts_captures_lookbehind_alternatives_variables_and_empty_replacements(): void
    {
        $values = $this->values(['rules' => [
            ['regex' => '(?<=\+)(44|49)\d+$', 'replace' => '${country}$1&"'],
            ['regex' => '^prefix~(\d+)$', 'replace' => null],
        ]]);
        $request = $this->request($values);
        $validator = Validator::make($values, $request->rules());
        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    public function test_validation_reports_nested_errors_and_duplicate_names(): void
    {
        $service = new NumberTranslationService();
        $profile = $service->save($this->values());
        $values = $this->values(['rules' => [
            ['regex' => '([', 'replace' => "bad\x01"],
            ['regex' => 'x', 'replace' => 'y', 'order' => 5],
        ]]);
        $validator = Validator::make($values, $this->request($values)->rules());
        $this->assertFalse($validator->passes());
        foreach (['name', 'rules.0.regex', 'rules.0.replace', 'rules.1'] as $key) {
            $this->assertArrayHasKey($key, $validator->errors()->toArray());
        }
        $values = $this->values();
        $this->assertTrue(Validator::make($values, $this->request($values, $profile)->rules())->passes());
    }

    public function test_http_trimming_preserves_literal_spaces_in_regex_and_replacement(): void
    {
        $values = $this->values(['name' => ' trimmed ', 'rules' => [
            ['regex' => ' ^(.*)$ ', 'replace' => ' $1 '],
        ]]);
        $request = Request::create('/api/system-settings/number-translations', 'POST', $values);
        $this->app->instance('request', $request);
        (new TrimStrings())->handle($request, fn ($request) => response()->json([]));
        $this->assertSame('trimmed', $request->input('name'));
        $this->assertSame(' ^(.*)$ ', $request->input('rules.0.regex'));
        $this->assertSame(' $1 ', $request->input('rules.0.replace'));
    }

    public function test_configuration_check_rejects_stale_wrongly_ordered_or_disabled_profiles(): void
    {
        $service = new class extends NumberTranslationService {
            public function matches(string $xml): bool { return $this->configurationMatches(simplexml_load_string($xml)); }
        };
        $profile = $service->save($this->values());
        $correct = '<configuration name="translate.conf"><profiles><profile name="translation_test" description="An &amp; example">'
            . '<rule regex="^\+(\d+)$" replace="$1"/><rule regex="^(.*)$" replace="$1"/></profile></profiles></configuration>';
        $this->assertTrue($service->matches($correct));
        $this->assertFalse($service->matches(str_replace('replace="$1"', 'replace="wrong"', $correct)));
        $this->assertFalse($service->matches(str_replace('name="translate.conf"', 'name="sofia.conf"', $correct)));
        $wrongOrder = '<configuration name="translate.conf"><profiles><profile name="translation_test" description="An &amp; example">'
            . '<rule regex="^(.*)$" replace="$1"/><rule regex="^\+(\d+)$" replace="$1"/></profile></profiles></configuration>';
        $this->assertFalse($service->matches($wrongOrder));
        $service->save($this->values(['enabled' => false]), $profile);
        $this->assertFalse($service->matches($correct));
        $this->assertTrue($service->matches('<configuration name="translate.conf"><profiles/></configuration>'));
    }

    public function test_runtime_failure_returns_saved_item_instead_of_encouraging_duplicate_creation(): void
    {
        $service = Mockery::mock(NumberTranslationService::class)->makePartial();
        $service->shouldReceive('synchronize')->once()->andReturnUsing(function () {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame(1, NumberTranslation::count());
            return ['synchronized' => false, 'error' => 'Test socket unavailable'];
        });
        $request = $this->request($this->values());
        $request->validateResolved();
        $response = (new NumberTranslationController($service))->store($request);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['runtime_synchronized']);
        $this->assertNotEmpty($response->getData(true)['item']['uuid']);
        $this->assertNotEmpty($response->getData(true)['messages']['error']);
    }

    public function test_permissions_distinguish_view_create_edit_and_delete(): void
    {
        session(['permissions' => [(object) ['permission_name' => 'number_translation_view']]]);
        $this->assertFalse($this->request($this->values())->authorize());
        $profile = (new NumberTranslationService())->save($this->values());
        $this->assertFalse($this->request($this->values(), $profile)->authorize());
        $this->assertSame('translation_test', (new NumberTranslationController(new NumberTranslationService()))->show($profile)->getData(true)['name']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new NumberTranslationController(new NumberTranslationService()))->destroy($profile);
    }

    public function test_create_can_save_multiple_new_rules_without_uuids(): void
    {
        $values = $this->values();
        foreach ($values['rules'] as &$rule) $rule['uuid'] = null;
        unset($rule);
        $request = $this->request($values);
        $this->assertTrue(Validator::make($values, $request->rules())->passes());
    }

    public function test_list_search_sort_and_pagination_use_postgresql(): void
    {
        $service = new NumberTranslationService();
        $service->save($this->values(['name' => 'GB_one']));
        $service->save($this->values(['name' => 'GB_two', 'enabled' => false]));
        $service->save($this->values(['name' => 'unrelated']));
        $request = Request::create('/api/system-settings/number-translations', 'GET', [
            'filter' => ['search' => 'gb_'], 'sort' => '-number_translation_name', 'per_page' => 1,
        ]);
        $this->app->instance('request', $request);
        $data = (new NumberTranslationController($service))->index($request)->getData(true);
        $this->assertSame(2, $data['total']);
        $this->assertSame('GB_two', $data['data'][0]['name']);
        $this->assertSame(2, $data['data'][0]['rules_count']);
        $this->assertFalse($data['data'][0]['enabled']);
    }

    public function test_edit_and_delete_synchronize_after_their_transactions_commit(): void
    {
        $service = Mockery::mock(NumberTranslationService::class)->makePartial();
        $profile = $service->save($this->values());
        $service->shouldReceive('synchronize')->twice()->andReturnUsing(function () {
            $this->assertSame(0, DB::transactionLevel());
            return ['synchronized' => true, 'error' => null];
        });
        $controller = new NumberTranslationController($service);
        $request = $this->request($this->values(['rules' => []]), $profile);
        $request->validateResolved();
        $response = $controller->update($request, $profile)->getData(true);
        $this->assertSame([], $response['item']['rules']);
        $this->assertTrue($response['runtime_synchronized']);
        $this->assertTrue($controller->destroy($profile)->getData(true)['runtime_synchronized']);
        $this->assertSame(0, NumberTranslation::count());
    }

    public function test_search_preserves_commas_in_descriptions(): void
    {
        $service = new NumberTranslationService();
        $service->save($this->values(['description' => 'Local, international']));
        $request = Request::create('/api/system-settings/number-translations', 'GET', ['filter' => ['search' => 'Local, international']]);
        $this->app->instance('request', $request);
        $data = (new NumberTranslationController($service))->index($request)->getData(true);
        $this->assertSame(1, $data['total']);
    }
}
