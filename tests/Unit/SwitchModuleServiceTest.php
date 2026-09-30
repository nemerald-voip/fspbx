<?php

namespace Tests\Unit;

use App\Models\SwitchModule;
use App\Services\FreeswitchEslService;
use App\Services\SwitchModuleService;
use Mockery;
use Tests\TestCase;

class SwitchModuleServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /** @dataProvider runtimeActions */
    public function test_runtime_actions_include_modules_with_autoload_disabled(string $action, string $command): void
    {
        $modules = collect([
            new SwitchModule(['module_name' => 'mod_curl', 'module_enabled' => 'false']),
            new SwitchModule(['module_name' => 'mod_shout', 'module_enabled' => 'true']),
        ]);
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->once()->andReturnTrue();
        $esl->shouldReceive('executeCommand')->once()->with("{$command} mod_curl", false)->andReturn('+OK');
        $esl->shouldReceive('executeCommand')->once()->with("{$command} mod_shout", false)->andReturn('+OK');
        $esl->shouldReceive('disconnect')->once();
        $this->app->instance(FreeswitchEslService::class, $esl);

        $service = Mockery::mock(SwitchModuleService::class)->makePartial();
        $service->shouldReceive('activeModuleNames')->once()
            ->with(Mockery::on(fn ($names) => $names->all() === ['mod_curl', 'mod_shout']))
            ->andReturn($action === 'start' ? collect(['mod_curl', 'mod_shout']) : collect());

        $result = $service->control($modules, $action);

        $this->assertTrue($result['success']);
        $this->assertSame(['Runtime status refreshed.'], $result['messages']['success_3']);
        $this->assertSame('false', $modules[0]->module_enabled);
        $this->assertSame('true', $modules[1]->module_enabled);
    }

    public static function runtimeActions(): array
    {
        return [['start', 'load'], ['stop', 'unload']];
    }

    public function test_runtime_failure_is_reported_for_a_module_with_autoload_disabled(): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->once()->andReturnTrue();
        $esl->shouldReceive('executeCommand')->once()->with('unload mod_curl', false)
            ->andReturn('-ERR Module is not unloadable.');
        $esl->shouldReceive('disconnect')->once();
        $this->app->instance(FreeswitchEslService::class, $esl);

        $result = (new SwitchModuleService())->control(collect([
            new SwitchModule(['module_name' => 'mod_curl', 'module_enabled' => 'false']),
        ]), 'stop');

        $this->assertFalse($result['success']);
        $this->assertSame(['mod_curl: Module is not unloadable.'], $result['messages']['error_1']);
    }

    public function test_active_module_names_falls_back_to_module_exists_for_missing_candidates(): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('executeCommand')
            ->once()
            ->with('show modules as json')
            ->andReturn([
                'rows' => [
                    [
                        'ikey' => 'mod_sofia',
                        'filename' => '/usr/lib/freeswitch/mod/mod_sofia.so',
                    ],
                    [
                        'ikey' => 'mod_native_file',
                        'filename' => '/usr/lib/freeswitch/mod/mod_native_file.so',
                    ],
                ],
            ]);
        $esl->shouldReceive('isConnected')
            ->once()
            ->andReturnTrue();
        $esl->shouldReceive('executeCommand')
            ->once()
            ->with('module_exists mod_bcg729', false)
            ->andReturnTrue();
        $esl->shouldReceive('disconnect')
            ->once();

        $this->app->instance(FreeswitchEslService::class, $esl);

        $activeNames = app(SwitchModuleService::class)->activeModuleNames(collect([
            'mod_sofia',
            'mod_native_file',
            'mod_bcg729',
            'bad module name',
        ]));

        $this->assertTrue($activeNames->contains('mod_sofia'));
        $this->assertTrue($activeNames->contains('mod_native_file'));
        $this->assertTrue($activeNames->contains('mod_bcg729'));
        $this->assertFalse($activeNames->contains('bad module name'));
    }
}
