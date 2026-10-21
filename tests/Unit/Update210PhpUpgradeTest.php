<?php

namespace Tests\Unit;

use App\Console\Commands\Updates\Update210;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class Update210PhpUpgradeTest extends TestCase
{
    private string $directory;
    private $previousContainer;
    private $previousFacadeApplication;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->directory = sys_get_temp_dir().'/fspbx-update210-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        $app = new Application($this->directory);
        $app->instance('files', new Filesystem());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        Process::fake();
        Process::preventStrayProcesses();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->directory);
        Container::setInstance($this->previousContainer);
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Facade::clearResolvedInstances();
        parent::tearDown();
    }

    private function applyUpdate(Update210 $update): array
    {
        ob_start();
        try {
            $result = $update->apply();
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        return [$result, $output];
    }

    public function test_it_uses_upgrade_only_mode_and_requests_standard_worker_restarts(): void
    {
        $update = new Update210();
        [$result, $output] = $this->applyUpdate($update);

        $this->assertTrue($result);
        $this->assertStringNotContainsString('PHP may still be vulnerable', $output);
        Process::assertRan(fn ($process) => $process->command === [
            'bash', $this->directory.'/install/install_php.sh', '--upgrade-only',
        ] && $process->timeout === 1800);
        $this->assertSame(['fs-cdr-service', 'fs-esl-listener-emergency', 'reverb'], $update->getSupervisorProgramsToRestart());
    }

    public function test_package_failure_warns_and_does_not_block_the_application_update(): void
    {
        Process::fake(fn () => Process::result(exitCode: 100, errorOutput: 'Repository unavailable'));
        $update = new Update210();
        [$result, $output] = $this->applyUpdate($update);

        $this->assertTrue($result);
        $this->assertStringContainsString('Repository unavailable', $output);
        $this->assertStringContainsString('PHP may still be vulnerable', $output);
        $this->assertStringContainsString('install/install_php.sh --upgrade-only', $output);
        $this->assertSame([], $update->getSupervisorProgramsToRestart());
    }

    public function test_process_startup_or_timeout_exceptions_do_not_block_the_application_update(): void
    {
        Process::fake(function () {
            throw new RuntimeException('PHP upgrade process timed out');
        });
        [$result, $output] = $this->applyUpdate(new Update210());

        $this->assertTrue($result);
        $this->assertStringContainsString('PHP upgrade process timed out', $output);
        $this->assertStringContainsString('Continuing the application update', $output);
    }

    public function test_a_failed_retry_does_not_keep_successful_worker_restart_requests(): void
    {
        $update = new Update210();
        $this->applyUpdate($update);
        $this->assertNotEmpty($update->getSupervisorProgramsToRestart());

        Process::fake(fn () => Process::result(exitCode: 1));
        [$result, $output] = $this->applyUpdate($update);
        $this->assertTrue($result);
        $this->assertStringContainsString('PHP may still be vulnerable', $output);
        $this->assertSame([], $update->getSupervisorProgramsToRestart());
    }
}
