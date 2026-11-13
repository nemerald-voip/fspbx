<?php

namespace Tests\Unit;

use App\Http\Controllers\SwitchNetworkController;
use App\Http\Requests\SaveSwitchNetworkRequest;
use App\Models\SwitchVariable;
use App\Services\FreeswitchEslService;
use App\Services\SwitchNetworkService;
use App\Services\SwitchVariableService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SwitchNetworkSetupTest extends TestCase
{
    private SwitchNetworkService $network;
    private SwitchVariableService $variables;
    private string $hostname = 'pbx-a';
    private array $addresses = ['10.0.0.19', '203.0.113.10'];
    private string $confDir;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'network_test',
            'database.connections.network_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array', 'session.driver' => 'array', 'app.key' => 'network-setup-test-key',
        ]);
        DB::purge('network_test');
        Schema::create('v_vars', function (Blueprint $table) {
            $table->uuid('var_uuid')->primary();
            foreach (['name', 'value', 'category', 'command', 'hostname', 'enabled', 'description'] as $field) {
                $table->text('var_'.$field)->nullable();
            }
            $table->integer('var_order')->nullable();
        });
        Schema::create('v_default_settings', function (Blueprint $table) {
            foreach (['category', 'subcategory', 'name', 'value', 'enabled'] as $field) {
                $table->text('default_setting_'.$field);
            }
        });
        $this->confDir = sys_get_temp_dir().'/fspbx-network-test-'.Str::uuid();
        File::makeDirectory($this->confDir);
        DB::table('v_default_settings')->insert([
            'default_setting_category' => 'switch', 'default_setting_subcategory' => 'conf',
            'default_setting_name' => 'dir', 'default_setting_value' => $this->confDir, 'default_setting_enabled' => 'true',
        ]);
        $this->variables = Mockery::mock(SwitchVariableService::class);
        $this->variables->shouldReceive('syncAndReloadXml')->with('pbx-a')->andReturnNull()->byDefault();
        $this->network = Mockery::mock(SwitchNetworkService::class, [$this->variables])->makePartial()->shouldAllowMockingProtectedMethods();
        $this->network->shouldReceive('runtime')->andReturnUsing(fn () => [
            'hostname' => $this->hostname,
            'values' => ['local_ip_v4' => '10.0.0.19', 'external_sip_ip' => '10.0.0.19', 'external_rtp_ip' => '10.0.0.19'],
        ]);
        $this->network->shouldReceive('interfaces')->andReturnUsing(fn () => array_map(fn ($ip) => [
            'address' => $ip, 'interface' => 'eth0', 'label' => $ip.' (eth0)',
        ], $this->addresses));
        app()->setLocale('en-us');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->confDir);
        DB::purge('network_test');
        parent::tearDown();
    }

    private function input(array $overrides = []): array
    {
        return array_replace([
            'hostname' => 'pbx-a', 'layout' => 'nat', 'local_ip' => '10.0.0.19',
            'public_ip' => '203.0.113.10', 'separate_media' => false,
        ], $overrides);
    }

    private function row(string $name, string $value, ?string $hostname = null, array $extra = []): SwitchVariable
    {
        return SwitchVariable::create(array_replace([
            'var_uuid' => (string) Str::uuid(), 'var_name' => $name, 'var_value' => $value,
            'var_category' => 'IP Address', 'var_command' => 'set', 'var_hostname' => $hostname,
            'var_enabled' => 'true', 'var_order' => 0,
        ], $extra));
    }

    private function apply(array $input = []): array
    {
        $input = $this->input($input);
        $review = $this->network->preview($input);
        return $this->network->apply($input + ['review_token' => $review['review_token']]);
    }

    private function assertRejected(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail('Expected validation to reject the operation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_review_does_not_write_and_apply_adopts_defaults_in_place(): void
    {
        $sip = $this->row('external_sip_ip', '$${local_ip_v4}');
        $rtp = $this->row('external_rtp_ip', '$${local_ip_v4}');
        $data = $this->input();
        $review = $this->network->preview($data);
        $this->assertSame(2, $review['shared_rows']);
        $this->assertCount(3, $review['changes']);
        $this->assertSame(2, SwitchVariable::count());
        $this->assertNull($sip->fresh()->var_hostname);

        $result = $this->network->apply($data + ['review_token' => $review['review_token']]);
        $this->assertTrue($result['synchronized']);
        $this->assertSame(3, SwitchVariable::count());
        $this->assertSame('pbx-a', $sip->fresh()->var_hostname);
        $this->assertSame('203.0.113.10', $rtp->fresh()->var_value);
        $this->assertSame(['local_ip_v4' => 0, 'external_sip_ip' => 10, 'external_rtp_ip' => 20],
            SwitchVariable::orderBy('var_order')->pluck('var_order', 'var_name')->all());
    }

    public function test_repeating_setup_updates_the_same_rows_and_preserves_other_servers_and_variables(): void
    {
        $peer = $this->row('external_sip_ip', '198.51.100.20', 'pbx-b');
        $other = $this->row('global_codec_prefs', 'PCMU,PCMA');
        $provision = $this->row('external_sip_ip', 'provision-value', null, ['var_category' => 'Provision']);
        $beforePeer = $peer->fresh()->toArray();
        $beforeOther = $other->fresh()->toArray();
        $beforeProvision = $provision->fresh()->toArray();
        $this->apply();
        $ids = SwitchVariable::where('var_hostname', 'pbx-a')->pluck('var_uuid')->sort()->values()->all();
        $this->apply(['public_ip' => '203.0.113.11']);
        $this->assertSame(6, SwitchVariable::count());
        $this->assertSame($ids, SwitchVariable::where('var_hostname', 'pbx-a')->pluck('var_uuid')->sort()->values()->all());
        $this->assertSame($beforePeer, $peer->fresh()->toArray());
        $this->assertSame($beforeOther, $other->fresh()->toArray());
        $this->assertSame($beforeProvision, $provision->fresh()->toArray());
    }

    public function test_review_lists_duplicate_shared_and_scoped_rows_and_apply_disables_them(): void
    {
        $this->row('external_sip_ip', '203.0.113.1', 'pbx-a');
        $this->row('external_sip_ip', '203.0.113.2', 'pbx-a', ['var_command' => 'exec-set']);
        $shared = $this->row('external_sip_ip', '$${local_ip_v4}');
        $this->row('external_sip_ip', '198.51.100.2', 'pbx-b');
        $review = $this->network->preview($this->input());
        $this->assertCount(2, array_filter($review['changes'], fn ($change) => $change['action'] === 'disable'));
        $this->apply();
        $this->assertSame('false', $shared->fresh()->var_enabled);
        $this->assertSame(1, SwitchVariable::where('var_name', 'external_sip_ip')->where('var_hostname', 'pbx-a')->where('var_enabled', 'true')->count());
        $this->assertSame(1, SwitchVariable::where('var_hostname', 'pbx-b')->where('var_enabled', 'true')->count());
    }

    public function test_direct_and_private_layouts_keep_native_variable_references(): void
    {
        foreach (['direct' => '203.0.113.10', 'private' => '10.0.0.19'] as $layout => $ip) {
            $this->apply(['layout' => $layout, 'local_ip' => $ip]);
            $this->assertSame('$${local_ip_v4}', SwitchVariable::where('var_name', 'external_sip_ip')->value('var_value'));
            $this->assertSame('$${local_ip_v4}', SwitchVariable::where('var_name', 'external_rtp_ip')->value('var_value'));
            $this->assertSame($ip, SwitchVariable::where('var_name', 'local_ip_v4')->value('var_value'));
        }
    }

    public function test_separate_public_media_address_is_used_only_when_selected(): void
    {
        $this->apply(['separate_media' => true, 'media_ip' => '198.51.100.15']);
        $this->assertSame('198.51.100.15', SwitchVariable::where('var_name', 'external_rtp_ip')->value('var_value'));
        $this->apply(['separate_media' => false, 'media_ip' => '198.51.100.15']);
        $this->assertSame('203.0.113.10', SwitchVariable::where('var_name', 'external_rtp_ip')->value('var_value'));
    }

    public function test_modified_values_cannot_reuse_a_review(): void
    {
        $data = $this->input();
        $review = $this->network->preview($data);
        $data['public_ip'] = '203.0.113.11';
        $this->assertRejected(fn () => $this->network->apply($data + ['review_token' => $review['review_token']]), 'review_token');
        $this->assertSame(0, SwitchVariable::count());
    }

    public function test_concurrent_manual_changes_require_a_new_review(): void
    {
        $row = $this->row('external_sip_ip', '$${local_ip_v4}');
        $data = $this->input();
        $review = $this->network->preview($data);
        $row->update(['var_value' => '203.0.113.99']);
        $this->assertRejected(fn () => $this->network->apply($data + ['review_token' => $review['review_token']]), 'review_token');
        $this->assertSame('203.0.113.99', $row->fresh()->var_value);
    }

    public function test_node_change_between_review_and_apply_is_rejected(): void
    {
        $data = $this->input();
        $review = $this->network->preview($data);
        $this->hostname = 'pbx-b';
        $this->assertRejected(fn () => $this->network->apply($data + ['review_token' => $review['review_token']]), 'hostname');
        $this->assertSame(0, SwitchVariable::count());
    }

    public function test_disappearing_interface_between_review_and_apply_is_rejected(): void
    {
        $data = $this->input();
        $review = $this->network->preview($data);
        $this->addresses = [];
        $this->assertRejected(fn () => $this->network->apply($data + ['review_token' => $review['review_token']]), 'local_ip');
        $this->assertSame(0, SwitchVariable::count());
    }

    public function test_missing_review_never_writes(): void
    {
        $this->assertRejected(fn () => $this->network->apply($this->input()), 'review_token');
        $this->assertSame(0, SwitchVariable::count());
    }

    public function test_sync_failure_keeps_saved_rows_and_reports_partial_success(): void
    {
        $this->variables->shouldReceive('syncAndReloadXml')->with('pbx-a')->once()->andThrow(
            ValidationException::withMessages(['runtime' => ['Test XML write failed.']])
        );
        $result = $this->apply();
        $this->assertTrue($result['saved']);
        $this->assertFalse($result['synchronized']);
        $this->assertSame(['Test XML write failed.'], $result['errors']['runtime']);
        $this->assertSame(3, SwitchVariable::count());
    }

    public function test_generated_xml_contains_only_the_selected_nodes_addresses_in_dependency_order(): void
    {
        $this->row('external_sip_ip', '$${local_ip_v4}');
        $this->row('external_rtp_ip', '$${local_ip_v4}');
        $this->apply();
        $this->row('local_ip_v4', '10.80.0.19', 'pbx-b', ['var_order' => 0]);
        $this->row('external_sip_ip', '198.51.100.20', 'pbx-b', ['var_order' => 10]);
        $this->row('external_rtp_ip', '198.51.100.20', 'pbx-b', ['var_order' => 20]);
        $writer = Mockery::mock(SwitchVariableService::class)->makePartial();
        $writer->shouldReceive('currentHostname')->andReturn('pbx-a', 'pbx-b');
        foreach (['pbx-a' => ['10.0.0.19', '203.0.113.10'], 'pbx-b' => ['10.80.0.19', '198.51.100.20']] as $host => [$local, $public]) {
            $this->assertTrue($writer->syncVarsXml(false));
            $xml = File::get($this->confDir.'/vars.xml');
            preg_match_all('/data="([^"]+)"/', $xml, $matches);
            $this->assertSame(['local_ip_v4='.$local, 'external_sip_ip='.$public, 'external_rtp_ip='.$public], $matches[1]);
        }
    }

    public function test_node_change_during_xml_sync_does_not_overwrite_the_file(): void
    {
        File::put($this->confDir.'/vars.xml', 'original XML');
        $writer = Mockery::mock(SwitchVariableService::class)->makePartial();
        $writer->shouldReceive('currentHostname')->andReturn('pbx-b');
        $this->assertRejected(fn () => $writer->syncAndReloadXml('pbx-a'), 'runtime');
        $this->assertSame('original XML', File::get($this->confDir.'/vars.xml'));
    }

    /** @dataProvider invalidPublicAddresses */
    public function test_unusable_public_addresses_are_rejected(string $ip): void
    {
        $this->assertRejected(fn () => $this->network->preview($this->input(['public_ip' => $ip])), 'public_ip');
    }

    public static function invalidPublicAddresses(): array
    {
        return [['127.0.0.1'], ['0.0.0.0'], ['10.0.0.19'], ['169.254.0.1'], ['224.0.0.1'], ['100.64.0.1']];
    }

    public function test_direct_mode_requires_a_public_interface_address(): void
    {
        $this->assertRejected(fn () => $this->network->preview($this->input(['layout' => 'direct'])), 'local_ip');
    }

    public function test_server_side_rules_validate_conditional_fields(): void
    {
        $rules = (new SaveSwitchNetworkRequest())->rules();
        $this->assertTrue(Validator::make($this->input(), $rules)->passes());
        $this->assertFalse(Validator::make($this->input(['separate_media' => true]), $rules)->passes());
        $this->assertTrue(Validator::make($this->input(['layout' => 'private', 'public_ip' => '', 'separate_media' => false]), $rules)->passes());
        $this->assertFalse(Validator::make($this->input(['local_ip' => '$${local_ip_v4}']), $rules)->passes());
        $this->assertFalse(Validator::make($this->input(['layout' => 'auto-nat']), $rules)->passes());
    }

    public function test_all_network_actions_require_view_add_and_edit_permissions(): void
    {
        $request = new SaveSwitchNetworkRequest();
        foreach (['var_view', 'var_add', 'var_edit'] as $missing) {
            session(['permissions' => array_map(fn ($name) => (object) ['permission_name' => $name], array_diff(['var_view', 'var_add', 'var_edit'], [$missing]))]);
            $this->assertFalse($request->authorize());
            try {
                (new SwitchNetworkController($this->network))->show();
                $this->fail('Expected permission failure.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
    }

    public function test_runtime_requires_a_real_switch_name_without_os_hostname_fallback(): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->andReturn(true);
        $esl->shouldReceive('executeCommand')->with('switchname', false)->andReturn('-ERR no reply');
        $esl->shouldReceive('disconnect')->once();
        app()->instance(FreeswitchEslService::class, $esl);
        $this->assertRejected(fn () => (new SwitchNetworkService($this->variables))->state(), 'runtime');
    }

    public function test_interface_discovery_omits_loopback_down_and_link_local_addresses(): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->andReturn(true);
        $esl->shouldReceive('executeCommand')->with('switchname', false)->andReturn('pbx-a');
        $esl->shouldReceive('executeCommand')->withArgs(fn ($cmd, $disconnect) => str_starts_with($cmd, 'global_getvar ') && $disconnect === false)->andReturn('10.0.0.19');
        $esl->shouldReceive('disconnect')->once();
        app()->instance(FreeswitchEslService::class, $esl);
        $iface = fn ($name, $ip, $flags = ['UP'], $state = 'UP') => [
            'ifname' => $name, 'flags' => $flags, 'operstate' => $state,
            'addr_info' => [['family' => 'inet', 'scope' => 'global', 'local' => $ip]],
        ];
        Process::preventStrayProcesses();
        Process::fake(fn () => Process::result(output: json_encode([
            $iface('eth0', '10.0.0.19'), $iface('eth1', '10.80.0.19', ['UP'], 'DOWN'),
            $iface('lo', '127.0.0.1', ['UP', 'LOOPBACK']), $iface('eth2', '169.254.1.1'),
        ])));
        $state = (new SwitchNetworkService($this->variables))->state();
        $this->assertSame(['10.0.0.19'], array_column($state['interfaces'], 'address'));
    }
}
