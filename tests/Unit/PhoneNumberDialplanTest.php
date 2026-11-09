<?php

namespace Tests\Unit;

use App\Http\Controllers\PhoneNumbersController;
use App\Http\Requests\UpdatePhoneNumberRequest;
use App\Jobs\BuildDialplanForPhoneNumber;
use App\Models\Destinations;
use App\Models\Dialplans;
use App\Services\DialplanBuilderService;
use App\Services\DialplanService;
use App\Services\PhoneNumberService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Compilers\Compiler;
use Mockery;
use ReflectionProperty;
use Tests\TestCase;

class PhoneNumberDialplanTest extends TestCase
{
    private string $compiledPath;
    private mixed $originalCompiledPath;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('v_domains', function (Blueprint $table) {
            $table->uuid('domain_uuid')->primary();
            $table->string('domain_name');
        });
        DB::table('v_domains')->insert([
            'domain_uuid' => '7d58342b-2b29-4dcf-92d6-e9a9e002a4e5',
            'domain_name' => 'account.test',
        ]);
        Schema::create('v_dialplans', function (Blueprint $table) {
            $table->uuid('dialplan_uuid')->primary();
            foreach (['domain_uuid', 'app_uuid', 'dialplan_name', 'dialplan_number', 'dialplan_context',
                'dialplan_continue', 'dialplan_enabled', 'dialplan_description', 'insert_date', 'insert_user',
                'update_date', 'update_user'] as $column) {
                $table->string($column)->nullable();
            }
            $table->text('dialplan_xml')->nullable();
            $table->integer('dialplan_order');
        });
        Schema::create('v_dialplan_details', function (Blueprint $table) {
            $table->uuid('dialplan_detail_uuid')->primary();
            foreach (['domain_uuid', 'dialplan_uuid', 'dialplan_detail_tag', 'dialplan_detail_type',
                'dialplan_detail_data', 'dialplan_detail_break', 'dialplan_detail_inline', 'insert_date',
                'insert_user'] as $column) {
                $table->string($column)->nullable();
            }
            $table->string('dialplan_detail_enabled')->nullable();
            $table->integer('dialplan_detail_group');
            $table->integer('dialplan_detail_order');
        });
        Schema::create('v_fax', fn (Blueprint $table) => $table->uuid('fax_uuid')->primary());

        $this->compiledPath = sys_get_temp_dir() . '/fspbx-phone-number-tests-' . bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->compiledPath);
        $cachePath = new ReflectionProperty(Compiler::class, 'cachePath');
        $this->originalCompiledPath = $cachePath->getValue(app('blade.compiler'));
        $cachePath->setValue(app('blade.compiler'), $this->compiledPath);

        app()->instance(PhoneNumberService::class, new class extends PhoneNumberService
        {
            public function countryCodeForDomain(?string $domainUuid = null): string
            {
                return 'US';
            }
        });
    }

    protected function tearDown(): void
    {
        if (isset($this->compiledPath)) {
            (new ReflectionProperty(Compiler::class, 'cachePath'))
                ->setValue(app('blade.compiler'), $this->originalCompiledPath);
            File::deleteDirectory($this->compiledPath);
        }
        DB::purge('sqlite');

        parent::tearDown();
    }

    /** @dataProvider phoneNumbers */
    public function test_creation_and_rebuild_keep_xml_and_editable_actions_consistent(string $number, ?string $did, bool $conditional): void
    {
        $domainUuid = '7d58342b-2b29-4dcf-92d6-e9a9e002a4e5';
        $phoneNumber = new Destinations([
            'domain_uuid' => $domainUuid,
            'dialplan_uuid' => '28c7b5c6-3bf7-4899-9d07-f9298d065410',
            'destination_number' => $number,
            'destination_prefix' => '1',
            'destination_number_regex' => '^\\+?1?(' . $number . ')$',
            'destination_context' => 'public',
            'destination_enabled' => 'true',
            'destination_actions' => json_encode([[
                'destination_app' => 'transfer',
                'destination_data' => '*991001 XML account.test',
            ]]),
            'destination_conditions' => $conditional ? json_encode([[
                'condition_field' => 'caller_id_number',
                'condition_expression' => '5304792220',
                'condition_app' => 'transfer',
                'condition_data' => '9150 XML account.test',
            ]]) : null,
        ]);
        $builder = new class extends DialplanBuilderService
        {
            public function clearCacheForPhoneNumber(Destinations $phoneNumber): void {}
        };
        $context = [
            ['export', 'call_direction=inbound', 'true'],
            ['set', 'domain_uuid=' . $domainUuid, 'true'],
            ['set', 'domain_name=account.test', 'true'],
        ];
        if ($did !== null) {
            $context[] = ['export', 'original_did=' . $did, 'false'];
        }
        $expected = [];
        if ($conditional) {
            $expected[] = [...$context, ['transfer', '9150 XML account.test', 'false']];
        }
        $expected[] = [...$context, ['transfer', '*991001 XML account.test', 'false']];

        // Exercise both initial creation and replacement of existing details.
        for ($build = 0; $build < 2; $build++) {
            $builder->buildDialplanForPhoneNumber($phoneNumber, 'account.test');
            $dialplan = Dialplans::findOrFail($phoneNumber->dialplan_uuid);
            $details = $dialplan->dialplan_details()->orderBy('dialplan_detail_group')
                ->orderBy('dialplan_detail_order')->get();

            $this->assertSame($expected, $this->actionsByCondition($dialplan->dialplan_xml));
            $this->assertSame($expected, $details->where('dialplan_detail_tag', 'action')
                ->groupBy('dialplan_detail_group')->map(fn ($rows) => $rows->map(fn ($row) => [
                    $row->dialplan_detail_type,
                    $row->dialplan_detail_data,
                    $row->dialplan_detail_inline ?: 'false',
                ])->values()->all())->values()->all());

            // The Rules editor regenerates XML from these rows on save.
            // Match the editor's raw enabled flag handling: legacy NULL means enabled.
            $rebuiltXml = app(DialplanService::class)->buildXml($dialplan,
                $details->map(fn ($row) => $row->getAttributes())->all());
            $this->assertSame($expected, $this->actionsByCondition($rebuiltXml));
            if (!$conditional) {
                // An omitted inline attribute and inline="false" are equivalent.
                $this->assertXmlStringEqualsXmlString($dialplan->dialplan_xml, str_replace(' inline="false"', '', $rebuiltXml));
            }

            if ($build === 0) {
                // Reproduce an existing affected record before saving it again.
                $dialplan->dialplan_details()->where('dialplan_detail_tag', 'action')
                    ->whereIn('dialplan_detail_type', ['export', 'set'])->delete();
                $dialplan->dialplan_xml = preg_replace('/\s*<action application="export" data="original_did=[^"]*"\/>/', '', $dialplan->dialplan_xml);
                $dialplan->save();
            }
        }
    }

    public function test_international_caller_condition_matches_the_same_numbers_in_xml_and_rules(): void
    {
        $phoneNumber = new Destinations([
            'domain_uuid' => '7d58342b-2b29-4dcf-92d6-e9a9e002a4e5',
            'dialplan_uuid' => '28c7b5c6-3bf7-4899-9d07-f9298d065410',
            'destination_number' => '7282396453', 'destination_prefix' => '1',
            'destination_number_regex' => '^\\+?1?(7282396453)$',
            'destination_context' => 'public', 'destination_enabled' => 'true',
            'destination_conditions' => json_encode([[
                'condition_field' => 'caller_id_number', 'condition_expression' => '+442079460958',
                'condition_app' => 'transfer', 'condition_data' => '9150 XML account.test',
            ]]),
        ]);
        $builder = new class extends DialplanBuilderService {
            public function clearCacheForPhoneNumber(Destinations $phoneNumber): void {}
        };
        $builder->buildDialplanForPhoneNumber($phoneNumber, 'account.test');
        $dialplan = Dialplans::findOrFail($phoneNumber->dialplan_uuid);
        $xml = simplexml_load_string($dialplan->dialplan_xml);
        $expression = (string) $xml->xpath('//regex[@field="caller_id_number"]')[0]['expression'];
        $stored = $dialplan->dialplan_details()->where('dialplan_detail_type', 'caller_id_number')->firstOrFail();
        $this->assertSame($expression, $stored->dialplan_detail_data);
        foreach (['+442079460958', '442079460958', '02079460958', '2079460958'] as $number) {
            $this->assertSame(1, preg_match('/'.$expression.'/', $number));
        }
        foreach (['+12079460958', '+4420794609589', '12079460958'] as $number) {
            $this->assertSame(0, preg_match('/'.$expression.'/', $number));
        }
    }

    private function actionsByCondition(string $xml): array
    {
        $document = new \DOMDocument();
        $this->assertTrue($document->loadXML($xml));
        $xpath = new \DOMXPath($document);
        $groups = [];
        foreach ($xpath->query('/extension/condition') as $condition) {
            $actions = [];
            foreach ($xpath->query('action', $condition) as $action) {
                $actions[] = [$action->getAttribute('application'), $action->getAttribute('data'),
                    $action->getAttribute('inline') ?: 'false'];
            }
            $groups[] = $actions;
        }

        return $groups;
    }

    public function test_rules_editor_still_supplies_context_for_legacy_details(): void
    {
        $dialplan = new Dialplans([
            'domain_uuid' => '7d58342b-2b29-4dcf-92d6-e9a9e002a4e5',
            'dialplan_name' => '7282396453',
            'dialplan_context' => 'public',
        ]);
        $xml = app(DialplanService::class)->buildXml($dialplan, [
            [
                'dialplan_detail_tag' => 'condition',
                'dialplan_detail_type' => '${fspbx_inbound_did}',
                'dialplan_detail_data' => '^\\+?1?(7282396453)$',
                'dialplan_detail_break' => null,
                'dialplan_detail_group' => 0,
            ],
            [
                'dialplan_detail_tag' => 'action',
                'dialplan_detail_type' => 'transfer',
                'dialplan_detail_data' => '*991001 XML account.test',
                'dialplan_detail_inline' => null,
                'dialplan_detail_group' => 0,
            ],
        ]);

        $this->assertSame([[
            ['export', 'call_direction=inbound', 'true'],
            ['set', 'domain_uuid=7d58342b-2b29-4dcf-92d6-e9a9e002a4e5', 'true'],
            ['set', 'domain_name=account.test', 'true'],
            ['transfer', '*991001 XML account.test', 'false'],
        ]], $this->actionsByCondition($xml));
    }

    public function test_saving_an_unchanged_phone_number_dispatches_a_rebuild(): void
    {
        Schema::create('v_destinations', function (Blueprint $table) {
            $table->uuid('destination_uuid')->primary();
            foreach (['domain_uuid', 'dialplan_uuid', 'destination_number', 'destination_prefix',
                'destination_number_regex', 'destination_actions', 'update_date', 'update_user'] as $column) {
                $table->string($column)->nullable();
            }
        });
        session(['domain_name' => 'account.test']);
        $actions = json_encode([['destination_app' => 'transfer', 'destination_data' => '*991001 XML account.test']]);
        $phoneNumber = new Destinations([
            'destination_uuid' => '8c75b703-9419-4ad7-92d8-408d73c34b3e',
            'domain_uuid' => '7d58342b-2b29-4dcf-92d6-e9a9e002a4e5',
            'dialplan_uuid' => '28c7b5c6-3bf7-4899-9d07-f9298d065410',
            'destination_number' => '7282396453',
            'destination_prefix' => '1',
            'destination_actions' => $actions,
        ]);
        $phoneNumber->save();
        $phoneNumber->setAppends([]);
        $request = Mockery::mock(UpdatePhoneNumberRequest::class);
        $request->shouldReceive('validated')->once()->andReturn([
            'destination_number' => '7282396453',
            'destination_prefix' => '1',
            'routing_options' => [['type' => 'voicemails', 'extension' => '1001']],
        ]);
        Bus::fake();

        $response = (new PhoneNumbersController())->update($request, $phoneNumber);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($actions, $phoneNumber->fresh()->destination_actions);
        Bus::assertDispatchedTimes(BuildDialplanForPhoneNumber::class, 1);
    }

    public static function phoneNumbers(): array
    {
        $cases = [];
        foreach (['5304792220' => '+15304792220', '7282396453' => '+17282396453',
            '72823964XX' => null, '1001' => null] as $number => $did) {
            $cases[$number . ' main route'] = [(string) $number, $did, false];
            $cases[$number . ' conditional route'] = [(string) $number, $did, true];
        }

        return $cases;
    }
}
