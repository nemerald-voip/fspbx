<?php

namespace Tests\Unit;

use App\Services\FreeswitchEslService;
use App\Services\FreeswitchGlobalVariableResolver;
use Mockery;
use Tests\TestCase;

class FreeswitchGlobalVariableResolverTest extends TestCase
{
    public function test_literal_values_do_not_require_freeswitch(): void
    {
        $this->assertSame('5080', app(FreeswitchGlobalVariableResolver::class)->resolve(' 5080 '));
    }

    public function test_exact_global_variable_references_are_resolved_through_freeswitch(): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->once()->andReturnTrue();
        $esl->shouldReceive('executeCommand')
            ->once()
            ->with('global_getvar external_sip_port')
            ->andReturn('5080');
        $this->app->instance(FreeswitchEslService::class, $esl);

        $this->assertSame(
            '5080',
            app(FreeswitchGlobalVariableResolver::class)->resolve('$${external_sip_port}')
        );
    }

    public function test_unavailable_global_variables_fail_closed(): void
    {
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->once()->andReturnTrue();
        $esl->shouldReceive('executeCommand')
            ->once()
            ->with('global_getvar external_sip_port')
            ->andReturn('_undef_');
        $this->app->instance(FreeswitchEslService::class, $esl);

        $this->assertNull(
            app(FreeswitchGlobalVariableResolver::class)->resolve('$${external_sip_port}')
        );
    }

    public function test_only_exact_safe_variable_references_are_expanded(): void
    {
        $this->assertSame(
            '$${external_sip_port}:tcp',
            app(FreeswitchGlobalVariableResolver::class)->resolve('$${external_sip_port}:tcp')
        );
    }
}
