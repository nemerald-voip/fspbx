<?php

namespace Tests\Unit;

use App\Console\Commands\UpdateApp;
use App\Services\GitHubApiService;
use App\Services\ProFeaturesService;
use Illuminate\Config\Repository;
use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class UpdateAppSupervisorRestartTest extends TestCase
{
    private string $directory;
    private Application $app;
    private BufferedOutput $output;
    private $previousContainer;
    private $previousFacadeApplication;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $this->directory = sys_get_temp_dir().'/fspbx-supervisor-update-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/config', 0777, true);
        file_put_contents($this->directory.'/config/version.php', '<?php return ["release" => "2.1.0"];');
        $this->app = new Application($this->directory);
        $this->app->instance('config', new Repository(['app' => ['version' => '2.1.0']]));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        Process::fake();
        Process::preventStrayProcesses();
        $this->output = new BufferedOutput();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->directory);
        Container::setInstance($this->previousContainer);
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Facade::clearResolvedInstances();
        parent::tearDown();
    }

    private function command(): UpdateApp
    {
        $command = new class($this->createMock(GitHubApiService::class)) extends UpdateApp {
            public function restartForTest(array $programs): void
            {
                $this->restartSupervisorPrograms($programs);
            }

            // Isolate the full handle() test from deployments, DB writes, and
            // live host commands. Supervisor uses the separately faked facade.
            protected function executeCommand($command, $timeout = 60, $failOnError = true)
            {
                if (str_starts_with($command, 'supervisorctl')) {
                    throw new RuntimeException('Supervisor must not use the fatal command runner.');
                }
            }

            protected function runArtisanCommand($command, array $options = []) {}

            public function call($command, array $arguments = [])
            {
                return 0;
            }
        };
        $command->setOutput(new OutputStyle(new ArrayInput([]), $this->output));

        return $command;
    }

    public function test_missing_and_stopped_programs_are_skipped_while_running_programs_restart(): void
    {
        $commands = [];
        Process::fake(function ($process) use (&$commands) {
            $commands[] = $process->command;
            if ($process->command === ['supervisorctl', 'status']) {
                return Process::result(output: "fs-cdr-service RUNNING pid 123, uptime 1:00:00\nreverb STOPPED Not started\nunrelated RUNNING pid 456, uptime 1:00:00\n", exitCode: 3);
            }
            return Process::result();
        });

        $this->command()->restartForTest(['fs-cdr-service', 'fs-esl-listener-emergency', 'reverb', 'fs-cdr-service', '']);

        $this->assertSame([
            ['supervisorctl', 'status'],
            ['supervisorctl', 'restart', 'fs-cdr-service'],
        ], $commands);
        $output = $this->output->fetch();
        $this->assertStringContainsString('fs-esl-listener-emergency: not configured', $output);
        $this->assertStringContainsString('reverb: STOPPED', $output);
    }

    public function test_only_running_group_members_restart_and_are_deduplicated(): void
    {
        $commands = [];
        Process::fake(function ($process) use (&$commands) {
            $commands[] = $process->command;
            return $process->command === ['supervisorctl', 'status']
                ? Process::result(output: "workers:worker_00 RUNNING pid 123\nworkers:worker_01 STOPPED Not started\nworkers-other:worker_00 RUNNING pid 456\n", exitCode: 3)
                : Process::result();
        });

        $this->command()->restartForTest(['workers', 'workers:*', 'workers:worker_00']);

        $this->assertSame([
            ['supervisorctl', 'status'],
            ['supervisorctl', 'restart', 'workers:worker_00'],
        ], $commands);
    }

    public function test_restart_failures_and_timeouts_do_not_prevent_later_restarts(): void
    {
        $commands = [];
        Process::fake(function ($process) use (&$commands) {
            $commands[] = $process->command;
            if ($process->command === ['supervisorctl', 'status']) {
                return Process::result(output: "fs-cdr-service RUNNING pid 123\nreverb RUNNING pid 456\nfspbx-contact-center-events RUNNING pid 789\n");
            }
            if ($process->command[2] === 'fs-cdr-service') {
                return Process::result(exitCode: 7, output: 'ERROR (spawn error)');
            }
            if ($process->command[2] === 'reverb') {
                throw new RuntimeException('Restart timed out');
            }
            return Process::result();
        });

        $this->command()->restartForTest(['fs-cdr-service', 'reverb', 'fspbx-contact-center-events']);

        $this->assertCount(4, $commands);
        $this->assertSame(['supervisorctl', 'restart', 'fspbx-contact-center-events'], $commands[3]);
        $output = $this->output->fetch();
        $this->assertStringContainsString('fs-cdr-service; continuing the update', $output);
        $this->assertStringContainsString('reverb; continuing the update: Restart timed out', $output);
    }

    public function test_unavailable_supervisor_is_nonblocking(): void
    {
        Process::fake(fn () => Process::result(exitCode: 1, errorOutput: 'unix:///var/run/supervisor.sock no such file'));
        $this->command()->restartForTest(['fs-cdr-service']);
        Process::assertRanTimes(fn ($process) => $process->command === ['supervisorctl', 'status'], 1);
        Process::assertNotRan(fn ($process) => ($process->command[1] ?? '') === 'restart');
        $this->assertStringContainsString('Could not read Supervisor status; continuing the update', $this->output->fetch());
    }

    public function test_status_process_exceptions_are_nonblocking(): void
    {
        Process::fake(function () {
            throw new RuntimeException('Status timed out');
        });
        $this->command()->restartForTest(['fs-cdr-service']);
        $this->assertStringContainsString('continuing the update: Status timed out', $this->output->fetch());
    }

    public function test_empty_restart_list_does_not_call_supervisor(): void
    {
        $this->command()->restartForTest(['']);
        Process::assertNothingRan();
    }

    public function test_enabled_module_restart_failure_does_not_abort_handle(): void
    {
        $modules = $this->createMock(ProFeaturesService::class);
        $modules->method('getSupervisorProgramsToRestart')->willReturn(['fspbx-contact-center-events']);
        $this->app->instance(ProFeaturesService::class, $modules);
        Process::fake(fn ($process) => $process->command === ['supervisorctl', 'status']
            ? Process::result(output: "fspbx-contact-center-events RUNNING pid 123\n")
            : Process::result(exitCode: 7, output: 'ERROR (spawn error)'));

        $this->command()->handle();

        $output = $this->output->fetch();
        $this->assertStringContainsString('fspbx-contact-center-events; continuing the update', $output);
        $this->assertStringContainsString('Update completed successfully!', $output);
        Process::assertRan(fn ($process) => $process->command === ['supervisorctl', 'restart', 'fspbx-contact-center-events']);
    }
}
