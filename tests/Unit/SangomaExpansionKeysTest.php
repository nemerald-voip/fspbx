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

class SangomaExpansionKeysTest extends TestCase
{
    private string $compiledPath;

    private Factory $views;

    protected function setUp(): void
    {
        parent::setUp();

        // Render without booting the application or touching its compiled-view cache.
        $this->compiledPath = sys_get_temp_dir().'/fspbx-sangoma-expansion-'.bin2hex(random_bytes(8));
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

    public function test_no_expansion_keys_restores_bridge_mode_and_clears_every_slot(): void
    {
        $xml = $this->render();

        $this->assertSame('1', (string) $xml->config->P231);
        $parameters = $xml->xpath('/hl_provision/config/*[starts-with(@para, "Exp")]');
        $this->assertCount(1200, $parameters);
        foreach ($parameters as $parameter) {
            $name = (string) $parameter['para'];
            $this->assertSame(
                str_ends_with($name, '_KeyType') || str_ends_with($name, '_Account') ? '0' : '',
                (string) $parameter,
                $parameter->getName()
            );
        }
    }

    /** @dataProvider unusableKeys */
    public function test_unusable_keys_do_not_enable_expansion_mode(array $keys): void
    {
        $xml = $this->render($keys);

        $this->assertSame('1', (string) $xml->config->P231);
        $this->assertCount(0, $xml->xpath('/hl_provision/config/*[contains(@para, "_KeyType") and text() != "0"]'));
    }

    public static function unusableKeys(): array
    {
        return [
            'blank placeholder' => [[['id' => 1, 'label' => 'Unused']]],
            'N/A key' => [[['id' => 1, 'type' => 'N/A', 'value' => '102']]],
            'unsupported type' => [[['id' => 1, 'type' => 'dtmf', 'value' => '102']]],
            'empty BLF' => [[['id' => 1, 'type' => 'blf', 'value' => '  ']]],
            'empty speed dial' => [[['id' => 1, 'type' => 'speed_dial']]],
            'empty park' => [[['id' => 1, 'type' => 'park']]],
            'unconfigured account' => [[['id' => 1, 'type' => 'line', 'line' => 3]]],
            'account outside rendered banks' => [[['id' => 1, 'type' => 'line', 'line' => 7]]],
            'negative account' => [[['id' => 1, 'type' => 'blf', 'value' => '102', 'line' => -1]]],
            'zero position' => [[['id' => 0, 'type' => 'blf', 'value' => '102']]],
            'negative position' => [[['id' => -1, 'type' => 'blf', 'value' => '102']]],
            'position past last module' => [[['id' => 241, 'type' => 'blf', 'value' => '102']]],
        ];
    }

    /** @dataProvider usableKeys */
    public function test_supported_keys_enable_expansion_with_zero_based_account_selection(array $key, string $type, string $value): void
    {
        $xml = $this->render([array_replace(['id' => 1, 'line' => 2, 'label' => 'Sales & <Support>', 'extension' => '**'], $key)]);

        $this->assertSame('2', (string) $xml->config->P231);
        $this->assertSame($type, (string) $xml->config->P60000);
        $this->assertSame($value, (string) $xml->config->P60001);
        $this->assertSame('Sales & <Support>', (string) $xml->config->P60002);
        $this->assertSame('1', (string) $xml->config->P60003);
        $this->assertSame('**', (string) $xml->config->P60004);
        $this->assertSame('Exp1_1_1_PickupCode', (string) $xml->config->P60004['para']);
    }

    public static function usableKeys(): array
    {
        return [
            'line without target' => [['type' => 'line'], '1', ''],
            'speed dial zero' => [['type' => 'speed_dial', 'value' => '0'], '2', '0'],
            'BLF normalization' => [['type' => ' BLF ', 'value' => ' 102 '], '3', '102'],
            'park' => [['type' => 'park', 'value' => 'park+*5901'], '8', 'park+*5901'],
        ];
    }

    public function test_sparse_positions_match_documented_page_and_module_parameter_banks(): void
    {
        // Independent boundary values from Sangoma's P-code reference (page 18973324).
        $positions = [
            1 => ['P60000', 'P60004', 'Exp1_1_1'],
            20 => ['P60095', 'P60099', 'Exp1_1_20'],
            21 => ['P60100', 'P60104', 'Exp1_2_1'],
            40 => ['P60195', 'P60199', 'Exp1_2_20'],
            41 => ['P61000', 'P61004', 'Exp2_1_1'],
            81 => ['P62000', 'P62004', 'Exp3_1_1'],
            121 => ['P63000', 'P63004', 'Exp4_1_1'],
            161 => ['P64000', 'P64004', 'Exp5_1_1'],
            201 => ['P65000', 'P65004', 'Exp6_1_1'],
            240 => ['P65195', 'P65199', 'Exp6_2_20'],
        ];
        $xml = $this->render(array_map(
            fn ($id) => ['id' => $id, 'type' => 'blf', 'value' => '102', 'extension' => '**'],
            array_keys($positions)
        ));

        foreach ($positions as [$type, $pickup, $prefix]) {
            $this->assertSame('3', (string) $xml->config->{$type});
            $this->assertSame($prefix.'_KeyType', (string) $xml->config->{$type}['para']);
            $this->assertSame('**', (string) $xml->config->{$pickup});
            $this->assertSame($prefix.'_PickupCode', (string) $xml->config->{$pickup}['para']);
        }
        $this->assertSame('0', (string) $xml->config->P60005);
        $names = array_map(fn ($node) => $node->getName(), $xml->xpath('/hl_provision/config/*'));
        $this->assertSameSize($names, array_unique($names));
    }

    public function test_only_the_final_key_assignment_controls_pc_port_mode(): void
    {
        $xml = $this->render([
            ['id' => 1, 'type' => 'blf', 'value' => '102'],
            ['id' => 1, 'type' => ''],
        ]);

        $this->assertSame('1', (string) $xml->config->P231);
        $this->assertSame('0', (string) $xml->config->P60000);
        $this->assertSame('', (string) $xml->config->P60001);
    }

    public function test_absent_expansion_input_keeps_bridge_mode(): void
    {
        $xml = $this->render([], ['expansion_keys' => null]);

        $this->assertSame('1', (string) $xml->config->P231);
    }

    public function test_keys_without_accounts_do_not_enable_expansion_mode(): void
    {
        $xml = $this->render([['id' => 1, 'type' => 'blf', 'value' => '102']], ['lines' => []]);

        $this->assertSame('1', (string) $xml->config->P231);
        $this->assertSame('0', (string) $xml->config->P60000);
    }

    private function render(array $keys = [], array $overrides = []): SimpleXMLElement
    {
        $xml = $this->views->file(
            dirname(__DIR__, 2).'/resources/provisioning/sangoma/S-Series/template.blade.php',
            array_replace([
                'flavor' => 'cfgmac.xml',
                'domain_name' => 'example.test',
                'settings' => [],
                'lines' => [
                    ['line_number' => 1, 'user_id' => '101'],
                    ['line_number' => 2, 'user_id' => '102'],
                    ['line_number' => 7, 'user_id' => '107'],
                ],
                'main_keys' => [],
                'expansion_keys' => $keys,
            ], $overrides)
        )->render();

        return new SimpleXMLElement(trim($xml));
    }
}
