<?php

namespace Tests\Unit;

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
            ->andReturn('true');
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
