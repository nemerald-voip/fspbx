<?php

namespace Tests\Unit;

use App\Console\Commands\Updates\Update198;
use App\Models\Domain;
use App\Services\DialplanProvisioningService;
use App\Services\DialplanService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class Update198DialplanTest extends TestCase
{
    private string $databasePath;
    private array $temporaryDirectories = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = sys_get_temp_dir() . '/fspbx-update198-dialplan-' . bin2hex(random_bytes(8)) . '.sqlite';
        touch($this->databasePath);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->createSchema();

        $cache = Mockery::mock(DialplanService::class);
        $cache->shouldReceive('clearDialplanCache')
            ->zeroOrMoreTimes()
            ->with(Mockery::on(fn ($context) => in_array($context, ['public', 'global'], true)));
        $this->app->instance(DialplanService::class, $cache);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }

        foreach ($this->temporaryDirectories as $directory) {
            File::deleteDirectory($directory);
        }

        parent::tearDown();
    }

    public function test_it_repairs_the_public_return_dialplan_and_its_editable_rules(): void
    {
        $this->invokeEnsurePublicReturnDialplan();

        DB::table('v_dialplan_details')->insert([
            'dialplan_detail_uuid' => (string) Str::uuid(),
            'dialplan_uuid' => '95da3fe2-f561-4897-8eaf-98dbde0a1404',
            'dialplan_detail_tag' => 'action',
            'dialplan_detail_type' => 'hangup',
            'dialplan_detail_group' => 0,
            'dialplan_detail_order' => 99,
            'dialplan_detail_enabled' => 'true',
        ]);

        $this->invokeEnsurePublicReturnDialplan();

        $dialplan = DB::table('v_dialplans')
            ->where('dialplan_uuid', '95da3fe2-f561-4897-8eaf-98dbde0a1404')
            ->first();

        $this->assertNotNull($dialplan);
        $this->assertSame('Validates AI provider transfer targets and returns them to the owning account.', $dialplan->dialplan_description);
        $this->assertStringNotContainsString('FS PBX', $dialplan->dialplan_description);

        $details = DB::table('v_dialplan_details')
            ->where('dialplan_uuid', $dialplan->dialplan_uuid)
            ->orderBy('dialplan_detail_order')
            ->get();

        $this->assertCount(2, $details);
        $this->assertSame('condition', $details[0]->dialplan_detail_tag);
        $this->assertSame('${sip_req_user}', $details[0]->dialplan_detail_type);
        $this->assertSame('^xfer\.[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\.[0-9*#]+$', $details[0]->dialplan_detail_data);
        $this->assertSame('action', $details[1]->dialplan_detail_tag);
        $this->assertSame('lua', $details[1]->dialplan_detail_type);
        $this->assertSame('ai_agent_return.lua', $details[1]->dialplan_detail_data);
    }

    public function test_the_new_install_template_builds_the_same_global_dialplan_and_rules(): void
    {
        $domain = new Domain();
        $domain->domain_uuid = (string) Str::uuid();
        $domain->domain_name = 'account.example.test';

        $dialplans = [];
        $details = [];
        $method = new ReflectionMethod(DialplanProvisioningService::class, 'buildFromTemplate');
        $method->setAccessible(true);
        $arguments = [
            base_path('public/app/dialplans/resources/switch/conf/dialplan/100_ai_agent_return.xml'),
            $domain,
            [],
            &$dialplans,
            &$details,
        ];
        $method->invokeArgs(new DialplanProvisioningService(), $arguments);

        $this->assertCount(1, $dialplans);
        $this->assertNull($dialplans[0]['domain_uuid']);
        $this->assertSame('7bc57f0c-00a2-4f72-9f41-7ebebc1c318c', $dialplans[0]['app_uuid']);
        $this->assertSame('public', $dialplans[0]['dialplan_context']);
        $this->assertSame('sip_req_user', $dialplans[0]['dialplan_destination']);
        $this->assertSame('xfer.<agent-uuid>.<extension>', $dialplans[0]['dialplan_number']);
        $this->assertSame('Validates AI provider transfer targets and returns them to the owning account.', $dialplans[0]['dialplan_description']);

        $this->assertCount(2, $details);
        $this->assertSame('condition', $details[0]['dialplan_detail_tag']);
        $this->assertSame('${sip_req_user}', $details[0]['dialplan_detail_type']);
        $this->assertSame('^xfer\.[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\.[0-9*#]+$', $details[0]['dialplan_detail_data']);
        $this->assertSame('action', $details[1]['dialplan_detail_tag']);
        $this->assertSame('lua', $details[1]['dialplan_detail_type']);
        $this->assertSame('ai_agent_return.lua', $details[1]['dialplan_detail_data']);
    }

    public function test_it_updates_agent_presence_dialplans_in_place_and_is_idempotent(): void
    {
        $statusUuid = (string) Str::uuid();
        $breakUuid = (string) Str::uuid();
        $statusDetailUuids = $this->seedAgentDialplan(
            $statusUuid,
            '2eb032c5-c79d-4096-ac90-8a47fe40f411',
            '*22',
            '^(?:agent\+|\*22)(.+)$',
            false
        );
        $breakDetailUuids = $this->seedAgentDialplan(
            $breakUuid,
            '17a937f4-82f1-4a0f-b3a8-213db15127cf',
            '*24',
            '^(?:agent\+|\*24)(.+)$',
            true
        );

        $star23Uuid = (string) Str::uuid();
        DB::table('v_dialplans')->insert([
            'dialplan_uuid' => $star23Uuid,
            'app_uuid' => 'feb0ee6e-0ea5-41fc-a9c1-189daf2d4161',
            'dialplan_context' => 'global',
            'dialplan_xml' => '<condition field="destination_number" expression="^\*23$"/>',
        ]);
        $star23DetailUuid = (string) Str::uuid();
        DB::table('v_dialplan_details')->insert([
            'dialplan_detail_uuid' => $star23DetailUuid,
            'dialplan_uuid' => $star23Uuid,
            'dialplan_detail_tag' => 'condition',
            'dialplan_detail_type' => 'destination_number',
            'dialplan_detail_data' => '^\*23$',
            'dialplan_detail_group' => 0,
            'dialplan_detail_order' => 5,
            'dialplan_detail_enabled' => 'true',
        ]);

        $this->invokeUpdateAgentPresenceDialplans();
        $firstPass = DB::table('v_dialplans')
            ->whereIn('dialplan_uuid', [$statusUuid, $breakUuid, $star23Uuid])
            ->get()
            ->keyBy('dialplan_uuid');
        $firstDetails = DB::table('v_dialplan_details')
            ->whereIn('dialplan_uuid', [$statusUuid, $breakUuid])
            ->orderBy('dialplan_detail_group')
            ->orderBy('dialplan_detail_order')
            ->get();

        $this->invokeUpdateAgentPresenceDialplans();
        $secondPass = DB::table('v_dialplans')
            ->whereIn('dialplan_uuid', [$statusUuid, $breakUuid, $star23Uuid])
            ->get()
            ->keyBy('dialplan_uuid');
        $secondDetails = DB::table('v_dialplan_details')
            ->whereIn('dialplan_uuid', [$statusUuid, $breakUuid])
            ->orderBy('dialplan_detail_group')
            ->orderBy('dialplan_detail_order')
            ->get();

        $this->assertSame($firstPass[$statusUuid]->dialplan_xml, $secondPass[$statusUuid]->dialplan_xml);
        $this->assertSame($firstPass[$breakUuid]->dialplan_xml, $secondPass[$breakUuid]->dialplan_xml);
        $this->assertEquals($firstDetails, $secondDetails);
        $this->assertSame('<condition field="destination_number" expression="^\*23$"/>', $secondPass[$star23Uuid]->dialplan_xml);
        $this->assertSame(
            '^\*23$',
            DB::table('v_dialplan_details')
                ->where('dialplan_detail_uuid', $star23DetailUuid)
                ->value('dialplan_detail_data')
        );

        $this->assertStringContainsString('expression="^\*22$"', $secondPass[$statusUuid]->dialplan_xml);
        $this->assertStringContainsString('expression="^agent(\d+)$"', $secondPass[$statusUuid]->dialplan_xml);
        $this->assertStringContainsString('lua/agent_toggle.lua login $1', $secondPass[$statusUuid]->dialplan_xml);
        $this->assertStringNotContainsString('app.lua agent_status', $this->compactCondition($secondPass[$statusUuid]->dialplan_xml, '^agent(\d+)$'));
        $this->assertStringContainsString('expression="^\*24$"', $secondPass[$breakUuid]->dialplan_xml);
        $this->assertStringContainsString('expression="^break(\d+)$"', $secondPass[$breakUuid]->dialplan_xml);
        $this->assertStringContainsString('lua/agent_toggle.lua break $1', $secondPass[$breakUuid]->dialplan_xml);
        $this->assertStringNotContainsString('agent\+', $secondPass[$statusUuid]->dialplan_xml);
        $this->assertStringNotContainsString('agent\+', $secondPass[$breakUuid]->dialplan_xml);

        $this->assertEqualsCanonicalizing(
            array_merge(
                array_slice($statusDetailUuids, 0, 3),
                array_slice($breakDetailUuids, 0, 3)
            ),
            $secondDetails->pluck('dialplan_detail_uuid')->all()
        );

        $statusCompact = $secondDetails
            ->where('dialplan_uuid', $statusUuid)
            ->where('dialplan_detail_group', 1);
        $this->assertSame(
            '^\*22$',
            $secondDetails->where('dialplan_uuid', $statusUuid)
                ->where('dialplan_detail_group', 0)
                ->firstWhere('dialplan_detail_tag', 'condition')
                ->dialplan_detail_data
        );
        $this->assertSame('^agent(\d+)$', $statusCompact->firstWhere('dialplan_detail_tag', 'condition')->dialplan_detail_data);
        $statusToggle = $statusCompact->firstWhere('dialplan_detail_data', 'lua/agent_toggle.lua login $1');
        $this->assertNotNull($statusToggle);
        $this->assertSame('lua', $statusToggle->dialplan_detail_type);
        $this->assertSame('true', $statusToggle->dialplan_detail_enabled);
        $this->assertCount(2, $statusCompact);

        $breakCompact = $secondDetails
            ->where('dialplan_uuid', $breakUuid)
            ->where('dialplan_detail_group', 1);
        $this->assertSame(
            '^\*24$',
            $secondDetails->where('dialplan_uuid', $breakUuid)
                ->where('dialplan_detail_group', 0)
                ->firstWhere('dialplan_detail_tag', 'condition')
                ->dialplan_detail_data
        );
        $this->assertSame('^break(\d+)$', $breakCompact->firstWhere('dialplan_detail_tag', 'condition')->dialplan_detail_data);
        $breakToggle = $breakCompact->firstWhere('dialplan_detail_data', 'lua/agent_toggle.lua break $1');
        $this->assertNotNull($breakToggle);
        $this->assertSame('lua', $breakToggle->dialplan_detail_type);
        $this->assertSame('true', $breakToggle->dialplan_detail_enabled);
        $this->assertCount(2, $breakCompact);
    }

    public function test_new_install_agent_templates_use_compact_keys_and_preserve_star_codes(): void
    {
        $domain = new Domain();
        $domain->domain_uuid = (string) Str::uuid();
        $domain->domain_name = 'account.example.test';

        [$statusDialplans, $statusDetails] = $this->buildTemplate('200_agent_status.xml', $domain);
        [$breakDialplans, $breakDetails] = $this->buildTemplate('215_agent_status_break.xml', $domain);
        [$idDialplans, $idDetails] = $this->buildTemplate('210_agent_status_id.xml', $domain);

        $this->assertCount(1, $statusDialplans);
        $this->assertCount(1, $breakDialplans);
        $this->assertCount(1, $idDialplans);
        $this->assertContains('^\*22$', $statusDetails->pluck('dialplan_detail_data')->all());
        $this->assertContains('^agent(\d+)$', $statusDetails->pluck('dialplan_detail_data')->all());
        $this->assertContains('lua/agent_toggle.lua login $1', $statusDetails->pluck('dialplan_detail_data')->all());
        $this->assertContains('^\*24$', $breakDetails->pluck('dialplan_detail_data')->all());
        $this->assertContains('^break(\d+)$', $breakDetails->pluck('dialplan_detail_data')->all());
        $this->assertContains('lua/agent_toggle.lua break $1', $breakDetails->pluck('dialplan_detail_data')->all());
        $this->assertContains('^\*23$', $idDetails->pluck('dialplan_detail_data')->all());
        $this->assertStringNotContainsString('agent\+', (string) json_encode([$statusDetails, $breakDetails]));
    }

    public function test_it_patches_lua_startup_configuration_exactly_once(): void
    {
        $directory = sys_get_temp_dir() . '/fspbx-update198-lua-' . bin2hex(random_bytes(8));
        $this->temporaryDirectories[] = $directory;
        File::ensureDirectoryExists($directory);

        $canonical = File::get(resource_path('autoload_configs/lua.conf.xml'));
        $withoutAgent = preg_replace(
            '/^[ \t]*<!-- FS PBX: Call Center Agent BLF daemon -->\R^[ \t]*<param name="startup-script" value="lua\/agent_blf\.lua"\/>\R/m',
            '',
            $canonical
        );
        File::put($directory . '/lua.conf.xml', $withoutAgent);

        $this->invokePatchAgentBlfStartupConfiguration($directory);
        $withDuplicate = str_replace(
            '<param name="startup-script" value="lua/agent_blf.lua"/>',
            "<param name=\"startup-script\" value=\"lua/agent_blf.lua\"/>\n"
                . '    <param value="lua/agent_blf.lua" name="startup-script"/>',
            File::get($directory . '/lua.conf.xml')
        );
        File::put($directory . '/lua.conf.xml', $withDuplicate);
        $this->invokePatchAgentBlfStartupConfiguration($directory);
        $this->invokePatchAgentBlfStartupConfiguration($directory);

        $updated = File::get($directory . '/lua.conf.xml');
        $this->assertSame(1, substr_count($updated, '<param name="startup-script" value="lua/agent_blf.lua"/>'));
        $this->assertStringNotContainsString('luarun', $updated);
    }

    private function invokeEnsurePublicReturnDialplan(): void
    {
        $method = new ReflectionMethod(Update198::class, 'ensurePublicReturnDialplan');
        $method->setAccessible(true);
        $method->invoke(new Update198());
    }

    private function compactCondition(string $xml, string $expression): string
    {
        preg_match(
            '/<condition\b[^>]*expression="' . preg_quote($expression, '/') . '"[^>]*>.*?<\/condition>/s',
            $xml,
            $matches
        );

        return $matches[0] ?? '';
    }

    private function invokeUpdateAgentPresenceDialplans(): void
    {
        $method = new ReflectionMethod(Update198::class, 'updateAgentPresenceDialplans');
        $method->setAccessible(true);
        $method->invoke(new Update198(), false);
    }

    private function invokePatchAgentBlfStartupConfiguration(string $directory): void
    {
        $method = new ReflectionMethod(Update198::class, 'patchAgentBlfStartupConfiguration');
        $method->setAccessible(true);
        $method->invoke(new Update198(), $directory);
    }

    private function seedAgentDialplan(
        string $dialplanUuid,
        string $appUuid,
        string $starCode,
        string $compactExpression,
        bool $breakAction
    ): array {
        $compactActions = [
            ['agent_id=$1', 'true'],
            ['agent_name=$1', 'false'],
            ['agent_authorized=true', 'false'],
        ];
        if ($breakAction) {
            $compactActions[] = ['agent_action=break', 'true'];
        }

        $compactXml = '';
        foreach ($compactActions as [$data, $enabled]) {
            $compactXml .= "\n\t\t<action application=\"set\" data=\"{$data}\" enabled=\"{$enabled}\"/>";
        }

        DB::table('v_dialplans')->insert([
            'dialplan_uuid' => $dialplanUuid,
            'app_uuid' => $appUuid,
            'dialplan_context' => 'global',
            'dialplan_xml' => <<<XML
<extension name="agent-test">
	<condition field="destination_number" expression="^\\{$starCode}$">
		<action application="lua" data="app.lua agent_status" enabled="true"/>
	</condition>
	<condition field="destination_number" expression="{$compactExpression}">{$compactXml}
		<action application="lua" data="app.lua agent_status" enabled="true"/>
	</condition>
</extension>
XML,
        ]);

        $rows = [];
        $uuids = [];
        $order = 5;
        $starConditionUuid = (string) Str::uuid();
        $uuids[] = $starConditionUuid;
        $rows[] = [
            'dialplan_detail_uuid' => $starConditionUuid,
            'dialplan_uuid' => $dialplanUuid,
            'dialplan_detail_tag' => 'condition',
            'dialplan_detail_type' => 'destination_number',
            'dialplan_detail_data' => '^\\' . $starCode . '$',
            'dialplan_detail_group' => 0,
            'dialplan_detail_order' => $order,
            'dialplan_detail_enabled' => 'true',
        ];

        $conditionUuid = (string) Str::uuid();
        $uuids[] = $conditionUuid;
        $rows[] = [
            'dialplan_detail_uuid' => $conditionUuid,
            'dialplan_uuid' => $dialplanUuid,
            'dialplan_detail_tag' => 'condition',
            'dialplan_detail_type' => 'destination_number',
            'dialplan_detail_data' => $compactExpression,
            'dialplan_detail_group' => 1,
            'dialplan_detail_order' => $order,
            'dialplan_detail_enabled' => 'true',
        ];

        foreach ($compactActions as [$data, $enabled]) {
            $order += 5;
            $uuid = (string) Str::uuid();
            $uuids[] = $uuid;
            $rows[] = [
                'dialplan_detail_uuid' => $uuid,
                'dialplan_uuid' => $dialplanUuid,
                'dialplan_detail_tag' => 'action',
                'dialplan_detail_type' => 'set',
                'dialplan_detail_data' => $data,
                'dialplan_detail_group' => 1,
                'dialplan_detail_order' => $order,
                'dialplan_detail_enabled' => $enabled,
            ];
        }

        DB::table('v_dialplan_details')->insert($rows);

        return $uuids;
    }

    private function buildTemplate(string $filename, Domain $domain): array
    {
        $dialplans = [];
        $details = [];
        $method = new ReflectionMethod(DialplanProvisioningService::class, 'buildFromTemplate');
        $method->setAccessible(true);
        $arguments = [
            base_path('public/app/dialplans/resources/switch/conf/dialplan/' . $filename),
            $domain,
            [],
            &$dialplans,
            &$details,
        ];
        $method->invokeArgs(new DialplanProvisioningService(), $arguments);

        return [collect($dialplans), collect($details)];
    }

    private function createSchema(): void
    {
        Schema::create('v_dialplans', function (Blueprint $table) {
            $table->string('dialplan_uuid')->primary();
            $table->string('domain_uuid')->nullable();
            $table->string('app_uuid')->nullable();
            $table->string('hostname')->nullable();
            $table->string('dialplan_name')->nullable();
            $table->string('dialplan_destination')->nullable();
            $table->string('dialplan_number')->nullable();
            $table->string('dialplan_context')->nullable();
            $table->string('dialplan_continue')->nullable();
            $table->text('dialplan_xml')->nullable();
            $table->integer('dialplan_order')->nullable();
            $table->string('dialplan_enabled')->nullable();
            $table->text('dialplan_description')->nullable();
            $table->timestamp('insert_date')->nullable();
            $table->string('insert_user')->nullable();
            $table->timestamp('update_date')->nullable();
            $table->string('update_user')->nullable();
        });

        Schema::create('v_dialplan_details', function (Blueprint $table) {
            $table->string('dialplan_detail_uuid')->primary();
            $table->string('domain_uuid')->nullable();
            $table->string('dialplan_uuid');
            $table->string('dialplan_detail_tag')->nullable();
            $table->string('dialplan_detail_type')->nullable();
            $table->text('dialplan_detail_data')->nullable();
            $table->string('dialplan_detail_break')->nullable();
            $table->string('dialplan_detail_inline')->nullable();
            $table->integer('dialplan_detail_group')->nullable();
            $table->integer('dialplan_detail_order')->nullable();
            $table->string('dialplan_detail_enabled')->nullable();
            $table->timestamp('insert_date')->nullable();
            $table->string('insert_user')->nullable();
            $table->timestamp('update_date')->nullable();
            $table->string('update_user')->nullable();
        });
    }
}
