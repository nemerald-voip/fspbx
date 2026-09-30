<?php

namespace Tests\Unit;

use App\Models\CallCenterAgents;
use App\Models\Domain;
use App\Models\FusionCache;
use App\Console\Commands\Updates\Update200;
use App\Services\AgentDirectoryCacheService;
use App\Services\CallCenterAgentRuntimeStatus;
use App\Services\DialplanProvisioningService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class AgentCallTrackingTest extends TestCase
{
    private string $cacheDirectory;
    private array $previousCache = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->cacheDirectory = sys_get_temp_dir().'/fspbx-agent-tracking-'.bin2hex(random_bytes(5));
        mkdir($this->cacheDirectory);
        foreach (['cacheType' => 'file', 'cacheLocation' => $this->cacheDirectory] as $property => $value) {
            $reflection = new ReflectionProperty(FusionCache::class, $property);
            $this->previousCache[$property] = $reflection->getValue();
            $reflection->setValue(null, $value);
        }
        $this->table('v_domains', 'domain_uuid', ['domain_name']);
        $this->table('v_extensions', 'extension_uuid', ['domain_uuid', 'extension', 'number_alias', 'user_context']);
        $this->table('v_call_center_agents', 'call_center_agent_uuid', ['domain_uuid', 'agent_id', 'agent_contact', 'agent_type', 'agent_status']);
        $this->table('v_dialplans', 'dialplan_uuid', ['domain_uuid', 'app_uuid', 'dialplan_name', 'dialplan_context', 'dialplan_continue', 'dialplan_order', 'dialplan_enabled', 'dialplan_description', 'dialplan_xml', 'insert_date', 'insert_user', 'update_date', 'update_user']);
        $this->table('v_dialplan_details', 'dialplan_detail_uuid', ['domain_uuid', 'dialplan_uuid', 'dialplan_detail_tag', 'dialplan_detail_type', 'dialplan_detail_data', 'dialplan_detail_order', 'dialplan_detail_group', 'dialplan_detail_enabled', 'dialplan_detail_inline', 'dialplan_detail_break', 'insert_date', 'insert_user', 'update_date', 'update_user']);
        DB::table('v_domains')->insert([
            ['domain_uuid' => 'account-a', 'domain_name' => 'a.test'],
            ['domain_uuid' => 'account-b', 'domain_name' => 'b.test'],
        ]);
        DB::table('v_extensions')->insert([
            ['extension_uuid' => 'ext-a', 'domain_uuid' => 'account-a', 'extension' => '201', 'number_alias' => '2001', 'user_context' => 'a.test'],
            ['extension_uuid' => 'ext-a2', 'domain_uuid' => 'account-a', 'extension' => '202', 'number_alias' => null, 'user_context' => 'a.test'],
            ['extension_uuid' => 'ext-b', 'domain_uuid' => 'account-b', 'extension' => '201', 'number_alias' => null, 'user_context' => 'b.test'],
        ]);
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel()) {
            DB::rollBack();
        }
        DB::purge('sqlite');
        foreach ($this->previousCache as $property => $value) {
            (new ReflectionProperty(FusionCache::class, $property))->setValue(null, $value);
        }
        File::deleteDirectory($this->cacheDirectory);
        parent::tearDown();
    }

    private function table(string $name, string $primary, array $columns): void
    {
        Schema::create($name, function (Blueprint $table) use ($primary, $columns) {
            $table->string($primary)->primary();
            foreach ($columns as $column) {
                $table->text($column)->nullable();
            }
        });
    }

    private function cache(string $key): string
    {
        $path = $this->cacheDirectory.'/'.str_replace(':', '.', $key);
        file_put_contents($path, 'cached');
        return $path;
    }

    private function agent(): CallCenterAgents
    {
        return CallCenterAgents::create([
            'domain_uuid' => 'account-a', 'agent_id' => '999',
            'agent_contact' => 'user/201@a.test', 'agent_type' => 'callback', 'agent_status' => 'Available',
        ]);
    }

    public function test_agent_creation_invalidates_contact_and_alias_only_after_commit(): void
    {
        $own = $this->cache('directory:201@a.test');
        $alias = $this->cache('directory:2001@a.test');
        $foreign = $this->cache('directory:201@b.test');
        DB::beginTransaction();
        $this->agent();
        $this->assertFileExists($own);
        DB::commit();
        $this->assertFileDoesNotExist($own);
        $this->assertFileDoesNotExist($alias);
        $this->assertFileExists($foreign);
    }

    public function test_rollback_keeps_cache_and_status_changes_do_not_clear_membership(): void
    {
        $agent = $this->agent();
        $own = $this->cache('directory:201@a.test');
        DB::beginTransaction();
        $agent->update(['agent_contact' => 'user/202@a.test']);
        DB::rollBack();
        $this->assertFileExists($own);
        $agent->refresh()->update(['agent_status' => 'On Break']);
        $this->assertFileExists($own);
    }

    public function test_reassignment_and_delete_clear_both_old_and_new_entries(): void
    {
        $agent = $this->agent();
        $old = $this->cache('directory:201@a.test');
        $alias = $this->cache('directory:2001@a.test');
        $new = $this->cache('directory:202@a.test');
        $agent->update(['agent_contact' => 'user/202@a.test']);
        foreach ([$old, $alias, $new] as $file) {
            $this->assertFileDoesNotExist($file);
        }
        $new = $this->cache('directory:202@a.test');
        $agent->delete();
        $this->assertFileDoesNotExist($new);
    }

    public function test_bulk_delete_snapshots_clear_after_commit_without_model_events(): void
    {
        $agent = $this->agent();
        $file = $this->cache('directory:201@a.test');
        DB::transaction(function () use ($agent, $file) {
            app(AgentDirectoryCacheService::class)->agentsChanged([$agent->getAttributes()]);
            CallCenterAgents::whereKey($agent->getKey())->delete();
            $this->assertFileExists($file);
        });
        $this->assertFileDoesNotExist($file);
    }

    public function test_extension_renumber_alias_and_context_changes_clear_old_and_new_keys(): void
    {
        $old = ['extension' => '201', 'number_alias' => '2001', 'user_context' => 'a.test', 'domain_uuid' => 'account-a'];
        $new = ['extension' => '202', 'number_alias' => '2002', 'user_context' => 'custom.test', 'domain_uuid' => 'account-a'];
        $files = array_map(fn ($key) => $this->cache($key), [
            'directory:201@a.test', 'directory:2001@a.test', 'directory:202@a.test',
            'directory:2002@a.test', 'directory:202@custom.test', 'directory:2002@custom.test',
        ]);
        app(AgentDirectoryCacheService::class)->extensionsChanged([$old, $new]);
        foreach ($files as $file) {
            $this->assertFileDoesNotExist($file);
        }
    }

    public function test_install_is_idempotent_and_preserves_other_dialplans(): void
    {
        DB::table('v_dialplans')->insert(['dialplan_uuid' => 'unrelated', 'dialplan_xml' => '<extension name="custom"/>']);
        $cache = $this->cache('dialplan:a.test');
        $installer = new Update200();
        $install = new ReflectionMethod(Update200::class, 'installDialplan');
        $install->invoke($installer);
        $install->invoke($installer);
        $this->assertSame(2, DB::table('v_dialplans')->count());
        $this->assertSame(2, DB::table('v_dialplan_details')->count());
        $row = DB::table('v_dialplans')->where('app_uuid', $installer::APP_UUID)->first();
        $this->assertSame('9', $row->dialplan_order);
        $this->assertStringContainsString('agent_call_track.lua caller', $row->dialplan_xml);
        $this->assertStringContainsString('${fspbx_cc_agent_uuid}', $row->dialplan_xml);
        $this->assertSame('<extension name="custom"/>', DB::table('v_dialplans')->where('dialplan_uuid', 'unrelated')->value('dialplan_xml'));
        $this->assertFileDoesNotExist($cache);
    }

    public function test_fresh_install_template_has_the_same_conditions_and_action(): void
    {
        if (! File::exists(public_path(\App\Console\Commands\Updates\Update200::TEMPLATE_PATH))) {
            $this->markTestSkipped('Fresh-install templates require the separate public repository.');
        }
        $domain = new Domain();
        $domain->domain_uuid = 'account-a';
        $domain->domain_name = 'a.test';
        $plans = $details = [];
        (new ReflectionMethod(DialplanProvisioningService::class, 'buildFromTemplate'))->invokeArgs(
            app(DialplanProvisioningService::class),
            [public_path(\App\Console\Commands\Updates\Update200::TEMPLATE_PATH), $domain, [], &$plans, &$details]
        );
        $this->assertCount(1, $plans);
        $this->assertNull($plans[0]['domain_uuid']);
        $this->assertSame(Update200::APP_UUID, $plans[0]['app_uuid']);
        $this->assertEquals(9, $plans[0]['dialplan_order']);
        $this->assertCount(2, $details);
        $this->assertSame('agent_call_track.lua caller', $details[1]['dialplan_detail_data']);
    }

    public function test_runtime_activity_preserves_login_queue_precedence_and_statistics(): void
    {
        foreach (['Available', 'On Break', 'Logged Out'] as $status) {
            $row = CallCenterAgentRuntimeStatus::normalize(['status' => $status, 'state' => 'Waiting', 'external_calls_count' => '2', 'talk_time' => '120']);
            $this->assertSame($status, $row['login_status']);
            $this->assertSame($status, $row['status']);
            $this->assertSame(2, $row['external_calls_count']);
            $this->assertSame('non_queue_call', $row['activity']);
            $this->assertSame('120', $row['talk_time']);
        }
        $row = CallCenterAgentRuntimeStatus::normalize(['status' => 'Available', 'state' => 'Receiving', 'external_calls_count' => '1']);
        $this->assertSame('Receiving', $row['status']);
        $this->assertSame('Available', $row['login_status']);
        $this->assertNull($row['activity']);
        $this->assertNull(CallCenterAgentRuntimeStatus::normalize([])['activity']);
    }

    public function test_update_downloads_missing_public_template_and_installs_idempotently(): void
    {
        $previous = public_path();
        $this->app->usePublicPath($this->cacheDirectory.'/public');
        $template = '<extension app_uuid="'.Update200::APP_UUID.'"/>';
        Http::fake(['https://raw.githubusercontent.com/*' => Http::response($template)]);
        $this->mock(\App\Services\FreeswitchEslService::class, function ($mock) {
            $mock->shouldReceive('executeCommand')->with('reloadxml')->twice()->andReturn('+OK');
        });
        $directoryCache = $this->cache('directory:201@a.test');
        ob_start();
        try {
            $this->assertTrue((new Update200())->apply());
            $this->assertTrue((new Update200())->apply());
            $this->assertSame($template, File::get(public_path(Update200::TEMPLATE_PATH)));
            Http::assertSentCount(1);
            Http::assertSent(fn ($request) => $request->url() === 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/'.Update200::TEMPLATE_PATH);
            $this->assertSame(1, DB::table('v_dialplans')->count());
            $this->assertSame(2, DB::table('v_dialplan_details')->count());
            $this->assertFileDoesNotExist($directoryCache);
        } finally {
            ob_end_clean();
            $this->app->usePublicPath($previous);
        }
    }

    public function test_failed_or_invalid_template_download_does_not_activate_tracking(): void
    {
        $previous = public_path();
        $this->app->usePublicPath($this->cacheDirectory.'/public');
        Http::fakeSequence()->push('not found', 404)->push('<invalid>')->push('<extension app_uuid="wrong"/>');
        $this->mock(\App\Services\FreeswitchEslService::class)->shouldNotReceive('executeCommand');
        $directoryCache = $this->cache('directory:201@a.test');
        ob_start();
        try {
            for ($i = 0; $i < 3; $i++) {
                $this->assertFalse((new Update200())->apply());
                $this->assertFileDoesNotExist(public_path(Update200::TEMPLATE_PATH));
                $this->assertSame(0, DB::table('v_dialplans')->count());
                $this->assertFileExists($directoryCache);
            }
        } finally {
            ob_end_clean();
            $this->app->usePublicPath($previous);
        }
    }
}
