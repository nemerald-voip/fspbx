<?php

namespace Tests\Unit;

use App\Console\Commands\PrepareFreeswitchRestart;
use App\Models\FusionCache;
use App\Services\SwitchVariableService;
use Illuminate\Support\Facades\File;
use Mockery;
use ReflectionProperty;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class PrepareFreeswitchRestartTest extends TestCase
{
    private string $cacheDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDirectory = sys_get_temp_dir().'/fspbx-restart-test-'.bin2hex(random_bytes(6));
        mkdir($this->cacheDirectory);
        $this->setCacheSetting('cacheType', 'file');
        $this->setCacheSetting('cacheLocation', $this->cacheDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->cacheDirectory);
        $this->setCacheSetting('cacheType', null);
        $this->setCacheSetting('cacheLocation', null);
        parent::tearDown();
    }

    private function setCacheSetting(string $name, ?string $value): void
    {
        $property = new ReflectionProperty(FusionCache::class, $name);
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }

    private function runCommand(bool $preserve, SwitchVariableService $variables): int
    {
        $command = new PrepareFreeswitchRestart;
        $command->setLaravel($this->app);
        $this->app->instance(SwitchVariableService::class, $variables);

        return $command->run(new ArrayInput($preserve ? ['--preserve-vars' => true] : []), new BufferedOutput);
    }

    public function test_upgrade_preserves_variables_and_flushes_file_cache_without_esl(): void
    {
        $variables = Mockery::mock(SwitchVariableService::class);
        $variables->shouldNotReceive('syncVarsXml');
        file_put_contents($this->cacheDirectory.'/dialplan.example', 'stale XML');
        file_put_contents($this->cacheDirectory.'/.stignore', 'keep');

        $this->assertSame(0, $this->runCommand(true, $variables));
        $this->assertFileDoesNotExist($this->cacheDirectory.'/dialplan.example');
        $this->assertFileExists($this->cacheDirectory.'/.stignore');
    }

    public function test_fresh_preparation_writes_variables_without_reloading_xml(): void
    {
        $variables = Mockery::mock(SwitchVariableService::class);
        $variables->shouldReceive('syncVarsXml')->once()->with(false)->andReturnTrue();
        $this->assertSame(0, $this->runCommand(false, $variables));
    }

    public function test_failed_variable_write_does_not_clear_cache(): void
    {
        $variables = Mockery::mock(SwitchVariableService::class);
        $variables->shouldReceive('syncVarsXml')->once()->with(false)->andReturnFalse();
        file_put_contents($this->cacheDirectory.'/dialplan.example', 'keep until XML ready');
        $this->assertSame(1, $this->runCommand(false, $variables));
        $this->assertFileExists($this->cacheDirectory.'/dialplan.example');
    }

    public function test_invalid_cache_locations_fail(): void
    {
        foreach (['/', '/tmp/..', 'relative/path'] as $path) {
            $this->setCacheSetting('cacheLocation', $path);
            $this->assertFalse(FusionCache::flushForRestart());
        }
    }

    public function test_missing_cache_directory_is_valid_before_first_start(): void
    {
        rmdir($this->cacheDirectory);
        $this->assertTrue(FusionCache::flushForRestart());
    }
}
