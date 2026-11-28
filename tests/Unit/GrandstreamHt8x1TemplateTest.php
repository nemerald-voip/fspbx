<?php

namespace Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

class GrandstreamHt8x1TemplateTest extends TestCase
{
    private string $compiledPath;

    private Factory $views;

    protected function setUp(): void
    {
        parent::setUp();

        // No application/database bootstrap and no writes to the live compiled-view cache.
        $this->compiledPath = sys_get_temp_dir().'/fspbx-ht8x1-'.bin2hex(random_bytes(8));
        mkdir($this->compiledPath);
        $files = new Filesystem;
        $compiler = new BladeCompiler($files, $this->compiledPath);
        $engines = new EngineResolver;
        $engines->register('blade', fn () => new CompilerEngine($compiler, $files));
        $this->views = new Factory($engines, new FileViewFinder($files, []), new Dispatcher(new Container));
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->compiledPath);

        parent::tearDown();
    }

    public function test_ht841_uses_separate_fxs_and_fxo_credentials_with_xml_escaping(): void
    {
        $xml = $this->render([
            $this->line(1, ['user_id' => 'phone101', 'auth_id' => 'auth101', 'password' => 'a&<b>"c', 'display_name' => 'Sales & Support']),
            $this->line(2), $this->line(5), $this->line(6),
        ]);

        // Expected values come from the HT8x1 reference, not the template's maps.
        $this->assertSame('phone101', (string) $xml->config->P4060);
        $this->assertSame('auth101', (string) $xml->config->P4090);
        $this->assertSame('a&<b>"c', (string) $xml->config->P4120);
        $this->assertSame('Sales & Support', (string) $xml->config->P4180);
        $this->assertSame('102', (string) $xml->config->P4061);
        $this->assertSame('105', (string) $xml->config->P4064);
        $this->assertSame('secret5', (string) $xml->config->P4124);
        $this->assertSame('0', (string) $xml->config->P4150);
        $this->assertSame('1', (string) $xml->config->P4154);
        $this->assertSame('1', (string) $xml->config->P4595);
        $this->assertSame('1', (string) $xml->config->P4599);
        foreach (['P35', 'P34', 'P735', 'P734', 'P4065', 'P4125', 'P4600', 'P92988'] as $code) {
            $this->assertCount(0, $xml->xpath('/gs_provision/config/'.$code), $code);
        }
    }

    public function test_ht881_keeps_sparse_port_numbers_and_uses_noncontiguous_forwarding_codes(): void
    {
        $xml = $this->render([$this->line(6), $this->line(9)], [
            'device_model' => 'HT881',
            'settings' => ['grandstream_ht8x1_fxo_5_destination' => '16105550105', 'grandstream_ht8x1_fxo_8_destination' => '16105550108'],
        ]);

        $this->assertSame('', (string) $xml->config->P4061);
        $this->assertSame('106', (string) $xml->config->P4065);
        $this->assertSame('109', (string) $xml->config->P4068);
        $this->assertSame('secret9', (string) $xml->config->P4128);
        $this->assertSame('1', (string) $xml->config->P4603);
        $this->assertSame('16105550105', (string) $xml->config->P92988);
        $this->assertSame('pbx.example.test', (string) $xml->config->P92982);
        $this->assertSame('16105550108', (string) $xml->config->P92991);
        $this->assertCount(0, $xml->xpath('/gs_provision/config/P92992'));
        $this->assertSame('pbx.example.test', (string) $xml->config->P92995);
        $this->assertSame('5060', (string) $xml->config->P92999);
    }

    public function test_removed_accounts_clear_all_credentials_and_disable_ports_and_profiles(): void
    {
        $xml = $this->render([], [
            'device_model' => 'HT881',
            'settings' => ['grandstream_ht8x1_fxo_1_destination' => '7000'],
        ]);

        foreach (['P271', 'P401', 'P501', 'P4595', 'P4596', 'P4603'] as $code) {
            $this->assertSame('0', (string) $xml->config->{$code}, $code);
        }
        foreach (['P4060', 'P4090', 'P4120', 'P4180', 'P4061', 'P4121', 'P4068', 'P4128', 'P47', 'P747', 'P547', 'P3305', 'P3306'] as $code) {
            $this->assertCount(1, $xml->xpath('/gs_provision/config/'.$code), $code);
            $this->assertSame('', (string) $xml->config->{$code}, $code);
        }
    }

    public function test_same_fxo_server_settings_share_a_profile_despite_different_credentials(): void
    {
        $xml = $this->render([$this->line(2), $this->line(3), $this->line(5)]);

        $this->assertSame('0', (string) $xml->config->P271);
        $this->assertSame('1', (string) $xml->config->P401);
        $this->assertSame('0', (string) $xml->config->P501);
        $this->assertSame('1', (string) $xml->config->P4151);
        $this->assertSame('1', (string) $xml->config->P4152);
        $this->assertSame('1', (string) $xml->config->P4154);
        $this->assertSame('pbx.example.test:5060', (string) $xml->config->P747);
        $this->assertSame('1', (string) $xml->config->P830);
    }

    public function test_second_fxo_profile_has_its_own_transport_server_and_registration_settings(): void
    {
        $xml = $this->render([
            $this->line(2),
            $this->line(3, ['server_address' => 'second.example.test', 'sip_port' => '', 'sip_transport' => 'Tls Or Tcp', 'register_expires' => 120]),
        ]);

        $this->assertSame('1', (string) $xml->config->P501);
        $this->assertSame('2', (string) $xml->config->P4152);
        $this->assertSame('second.example.test:5061', (string) $xml->config->P547);
        $this->assertSame('2', (string) $xml->config->P530);
        $this->assertSame('2', (string) $xml->config->P532);
        $this->assertSame('1', (string) $xml->config->P543);
        $this->assertSame('7060', (string) $xml->config->P640);
        $this->assertSame('0', (string) $xml->config->P20503);
    }

    public function test_more_than_two_fxo_profiles_is_rejected_instead_of_using_the_wrong_server(): void
    {
        $this->expectExceptionMessage('HT8x1 supports at most two different FXO SIP profiles.');
        $this->render([
            $this->line(2),
            $this->line(3, ['server_address' => 'second.example.test']),
            $this->line(4, ['server_address' => 'third.example.test']),
        ]);
    }

    /** @dataProvider modelSources */
    public function test_model_detection_matches_live_requests_and_previews(array $vars, bool $hasEightPorts): void
    {
        $xml = $this->render([$this->line(9)], $vars);

        $this->assertCount($hasEightPorts ? 1 : 0, $xml->xpath('/gs_provision/config/P4068'));
    }

    public static function modelSources(): array
    {
        return [
            'unknown uses four ports' => [['device_model' => ''], false],
            'live request' => [['user_agent' => 'Grandstream HT881 1.0.11.8', 'device_model' => 'HT841'], true],
            'saved model' => [['device_model' => 'HT881'], true],
            'last contact' => [['device_model' => '', 'device_provisioned_agent' => 'Grandstream HT881 1.0.11.8'], true],
            'explicit model override' => [['settings' => ['grandstream_ht8x1_model' => 'HT881']], true],
            'HT841 live overrides old model' => [['user_agent' => 'Grandstream HT841 1.0.11.8', 'device_model' => 'HT881'], false],
        ];
    }

    public function test_provisioning_credentials_and_optional_fxo_settings_are_applied(): void
    {
        $xml = $this->render([$this->line(2)], ['settings' => [
            'provision_base_url' => 'https://pbx.example.test/prov/',
            'http_auth_username' => 'provision-user', 'http_auth_password' => 'p&<secret>',
            'admin_password' => 'admin&password',
            'grandstream_ht8x1_fxo_1_destination' => '7000',
            'grandstream_ht8x1_fxo_stage_method' => '2',
            'grandstream_ht8x1_fxo_number_of_rings' => '5',
            'grandstream_ht8x1_fxo_wait_for_dial_tone' => '1',
            'grandstream_ht8x1_fxo_from_domain' => '',
            'grandstream_ht8x1_fxo_country' => '17',
            'grandstream_ht8x1_fxo_ac_termination' => '2',
            'grandstream_ht8x1_fxo_current_disconnect_threshold' => '150',
            'grandstream_ht8x1_fxo_disconnect_tone_detection' => '1',
            'grandstream_ht8x1_fxo_disconnect_tone' => 'fxoch1-4:f1=480@-32,f2=620@-32,c=500/500;',
        ]]);

        $this->assertSame('2', (string) $xml->config->P212);
        $this->assertSame('pbx.example.test/prov', (string) $xml->config->P237);
        $this->assertSame('provision-user', (string) $xml->config->P1360);
        $this->assertSame('p&<secret>', (string) $xml->config->P1361);
        $this->assertSame('admin&password', (string) $xml->config->P2);
        $this->assertSame('15', (string) $xml->config->P28792);
        $this->assertSame('7000', (string) $xml->config->P3305);
        $this->assertSame('pbx.example.test', (string) $xml->config->P3306);
        foreach (['P3304' => '2', 'P92921' => '2', 'P798' => '5', 'P92925' => '5',
            'P3303' => '1', 'P92917' => '1', 'P8618' => '', 'P864' => '17', 'P92845' => '17',
            'P899' => '2', 'P92841' => '2'] as $code => $expected) {
            $this->assertSame($expected, (string) $xml->config->{$code}, $code);
        }
        $this->assertSame('150', (string) $xml->config->P857);
        $this->assertSame('150', (string) $xml->config->P657);
        $this->assertSame('1', (string) $xml->config->P712);
        $this->assertSame('1', (string) $xml->config->P92861);
        $this->assertSame('fxoch1-4:f1=480@-32,f2=620@-32,c=500/500;', (string) $xml->config->P841);
    }

    public function test_defaults_do_not_forward_incoming_pstn_calls_back_to_the_gateway_account(): void
    {
        $xml = $this->render([$this->line(2)]);

        $this->assertSame('', (string) $xml->config->P3305);
        $this->assertSame('', (string) $xml->config->P3306);
        $this->assertSame('', (string) $xml->config->P769);
        foreach (['P3304' => '1', 'P92921' => '1', 'P798' => '2', 'P92925' => '2',
            'P3303' => '0', 'P92917' => '0', 'P763' => '0', 'P563' => '0',
            'P4301' => '0', 'P8618' => 'pbx.example.test'] as $code => $expected) {
            $this->assertSame($expected, (string) $xml->config->{$code}, $code);
        }
        foreach (['P796', 'P864', 'P92845', 'P899', 'P92841'] as $code) {
            $this->assertCount(0, $xml->xpath('/gs_provision/config/'.$code), $code);
        }
        $this->assertCount(1, $xml->xpath('/gs_provision/config/P769'));
        $this->assertSame('0', (string) $xml->config->P842);
        $this->assertSame('1', (string) $xml->config->P893);
        $this->assertCount(0, $xml->xpath('/gs_provision/config/P2'));
        $this->assertCount(0, $xml->xpath('/gs_provision/config/P841'));
    }

    public function test_missing_auth_id_and_proxy_use_safe_fallbacks_without_losing_user_id(): void
    {
        $xml = $this->render([$this->line(1, [
            'auth_id' => '', 'user_id' => 'phone101', 'sip_transport' => 'UDP',
            'server_address_primary' => '2001:db8::1',
            'outbound_proxy_secondary' => 'backup.example.test:5080',
        ])]);

        $this->assertSame('phone101', (string) $xml->config->P4090);
        $this->assertSame('[2001:db8::1]:5060', (string) $xml->config->P47);
        $this->assertSame('', (string) $xml->config->P48);
        $this->assertSame('backup.example.test:5080', (string) $xml->config->P2333);
        $this->assertSame('0', (string) $xml->config->P130);
    }

    private function line(int $number, array $overrides = []): array
    {
        return array_replace([
            'line_number' => $number, 'user_id' => (string) (100 + $number), 'auth_id' => (string) (100 + $number),
            'password' => 'secret'.$number, 'server_address' => 'pbx.example.test',
            'sip_port' => '5060', 'sip_transport' => 'TCP', 'register_expires' => 3600,
        ], $overrides);
    }

    private function render(array $lines, array $overrides = []): SimpleXMLElement
    {
        $body = $this->views->file(
            dirname(__DIR__, 2).'/resources/provisioning/grandstream/HT8x1/template.blade.php',
            array_replace(['flavor' => 'cfgmac.xml', 'device_model' => 'HT841', 'settings' => [], 'lines' => $lines], $overrides)
        )->render();

        $xml = new SimpleXMLElement(trim($body));
        $codes = array_map(fn ($node) => $node->getName(), $xml->xpath('/gs_provision/config/*'));
        $this->assertSameSize(array_unique($codes), $codes, 'P-values must not be emitted more than once.');

        return $xml;
    }
}
