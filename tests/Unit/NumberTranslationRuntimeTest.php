<?php

namespace Tests\Unit;

use App\Services\FreeswitchEslService;
use App\Services\NumberTranslationService;
use Mockery;
use Tests\TestCase;

class NumberTranslationRuntimeTest extends TestCase
{
    public function test_cache_is_cleared_and_xml_verified_before_reload(): void
    {
        $xml = simplexml_load_string('<configuration name="translate.conf"><profiles/></configuration>');
        $service = Mockery::mock(NumberTranslationService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('clearCache')->once()->globally()->ordered()->andReturn(true);
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->once()->globally()->ordered()->andReturn(true);
        $esl->shouldReceive('executeCommand')->with('xml_locate configuration configuration name translate.conf', false)
            ->once()->globally()->ordered()->andReturn($xml);
        $service->shouldReceive('configurationMatches')->with($xml)->once()->globally()->ordered()->andReturn(true);
        $esl->shouldReceive('executeCommand')->with('reloadxml', false)->once()->globally()->ordered()->andReturn('+OK [Success]');
        $esl->shouldReceive('disconnect')->once();
        $this->app->instance(FreeswitchEslService::class, $esl);
        $this->assertTrue($service->synchronize()['synchronized']);
    }

    public function test_cache_failure_does_not_reload(): void
    {
        $service = Mockery::mock(NumberTranslationService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('clearCache')->once()->andReturn(false);
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldNotReceive('executeCommand');
        $this->app->instance(FreeswitchEslService::class, $esl);
        $this->assertFalse($service->synchronize()['synchronized']);
    }

    public function test_disconnected_socket_keeps_the_saved_changes_and_reports_failure(): void
    {
        $service = Mockery::mock(NumberTranslationService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('clearCache')->once()->andReturn(true);
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->andReturn(false);
        $esl->shouldReceive('disconnect')->once();
        $esl->shouldNotReceive('executeCommand');
        $this->app->instance(FreeswitchEslService::class, $esl);
        $this->assertFalse($service->synchronize()['synchronized']);
    }

    public function test_stale_or_invalid_xml_is_not_applied(): void
    {
        $service = Mockery::mock(NumberTranslationService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('clearCache')->once()->andReturn(true);
        $service->shouldReceive('configurationMatches')->once()->andReturn(false);
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->andReturn(true);
        $esl->shouldReceive('executeCommand')->with('xml_locate configuration configuration name translate.conf', false)->andReturn('-ERR not found');
        $esl->shouldNotReceive('executeCommand')->with('reloadxml', false);
        $esl->shouldReceive('disconnect')->once();
        $this->app->instance(FreeswitchEslService::class, $esl);
        $this->assertFalse($service->synchronize()['synchronized']);
    }

    /** @dataProvider failedReloadResponses */
    public function test_failed_reload_is_not_reported_as_success(mixed $response): void
    {
        $service = Mockery::mock(NumberTranslationService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('clearCache')->andReturn(true);
        $service->shouldReceive('configurationMatches')->andReturn(true);
        $esl = Mockery::mock(FreeswitchEslService::class);
        $esl->shouldReceive('isConnected')->andReturn(true);
        $esl->shouldReceive('executeCommand')->with('xml_locate configuration configuration name translate.conf', false)->andReturn(null);
        $esl->shouldReceive('executeCommand')->with('reloadxml', false)->andReturn($response);
        $esl->shouldReceive('disconnect');
        $this->app->instance(FreeswitchEslService::class, $esl);
        $this->assertFalse($service->synchronize()['synchronized']);
    }

    public static function failedReloadResponses(): array
    {
        return [[null], [''], ['-ERR failed'], ['unconfirmed']];
    }

    public function test_esl_parses_xml_containing_regex_alternatives_and_error_literals(): void
    {
        $esl = (new \ReflectionClass(FreeswitchEslService::class))->newInstanceWithoutConstructor();
        $event = new class {
            public function getBody(): string
            {
                return '<configuration name="translate.conf"><profiles><profile name="test"><rule regex="^(44|49)$" replace="-ERR$1"/></profile></profiles></configuration>';
            }
        };
        $parsed = $esl->convertEslResponse($event);
        $this->assertInstanceOf(\SimpleXMLElement::class, $parsed);
        $this->assertSame('^(44|49)$', (string) $parsed->profiles->profile->rule['regex']);
        $check = new \ReflectionMethod($esl, 'handleResponseErrors');
        $check->setAccessible(true);
        $check->invoke($esl, $event);
    }

    public function test_esl_still_parses_pipe_delimited_tables(): void
    {
        $esl = (new \ReflectionClass(FreeswitchEslService::class))->newInstanceWithoutConstructor();
        $event = new class {
            public function getBody(): string { return "name|status\nmod_translate|running"; }
        };
        $this->assertSame([['name' => 'mod_translate', 'status' => 'running']], $esl->convertEslResponse($event));
    }
}
