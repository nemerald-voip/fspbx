<?php

namespace Tests\Unit;

use App\Console\Commands\Updates\Update213;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

class Update213LegacyQueueServicesTest extends TestCase
{
    private string $directory;
    private array $commands = [];
    private string $output = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/fspbx-legacy-queues-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->directory.'/etc');
        File::ensureDirectoryExists($this->directory.'/vendor');
        Process::preventStrayProcesses();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function update(): Update213
    {
        return new class([$this->directory.'/etc', $this->directory.'/vendor']) extends Update213 {
            public function __construct(private array $directories) {}
            protected function systemdUnitDirectories(): array { return $this->directories; }
        };
    }

    private function unit(string $name, string $location = 'etc'): string
    {
        $path = $this->directory.'/'.$location.'/'.$name.'.service';
        File::put($path, "[Service]\nExecStart=/usr/bin/php /missing/{$name}.php\nRestart=always\n");
        return $path;
    }

    private function runCleanup(): bool
    {
        ob_start();
        try {
            return $this->update()->removeLegacyQueueServices();
        } finally {
            $this->output = ob_get_clean();
        }
    }

    private function fakeSystemd(array $states, ?string $failure = null): void
    {
        Process::fake(function ($process) use (&$states, $failure) {
            $command = $process->command;
            $this->commands[] = $command;
            $operation = $command[1];
            $unit = end($command);
            if ($operation === $failure && ($unit === 'email_queue.service' || $operation === 'daemon-reload')) {
                return Process::result(exitCode: 1, errorOutput: 'Simulated systemctl failure');
            }
            if ($operation === 'show') {
                [$load, $active] = $states[$unit] ?? ['not-found', 'inactive'];
                return Process::result(output: "LoadState={$load}\nActiveState={$active}\n", exitCode: $load === 'not-found' ? 1 : 0);
            }
            if ($operation === 'stop') {
                if ($states[$unit][1] !== 'failed') {
                    $states[$unit][1] = 'inactive';
                }
            }
            if ($operation === 'reset-failed') {
                $states[$unit][1] = 'inactive';
            }
            if ($operation === 'daemon-reload') {
                foreach ($states as $name => $state) {
                    if (! file_exists($this->directory.'/etc/'.$name) && ! file_exists($this->directory.'/vendor/'.$name)) {
                        $states[$name] = ['not-found', 'inactive'];
                    }
                }
            }
            return Process::result();
        });
    }

    public function test_removes_restart_loops_unit_copies_links_and_overrides_without_touching_other_services(): void
    {
        foreach (['email_queue', 'fax_queue'] as $name) {
            $path = $this->unit($name);
            $this->unit($name, 'vendor');
            File::ensureDirectoryExists($path.'.d');
            File::put($path.'.d/override.conf', "[Service]\nRestart=always\n");
            foreach (['multi-user.target.wants', 'example.target.requires'] as $dependency) {
                File::ensureDirectoryExists($this->directory.'/etc/'.$dependency);
                symlink($path, $this->directory.'/etc/'.$dependency.'/'.$name.'.service');
            }
        }
        $unrelated = $this->unit('horizon');
        $this->fakeSystemd([
            'email_queue.service' => ['loaded', 'activating'],
            'fax_queue.service' => ['loaded', 'failed'],
        ]);

        $this->assertTrue($this->runCleanup());
        foreach (['email_queue', 'fax_queue'] as $name) {
            $this->assertFileDoesNotExist($this->directory.'/etc/'.$name.'.service');
            $this->assertFileDoesNotExist($this->directory.'/vendor/'.$name.'.service');
            $this->assertDirectoryDoesNotExist($this->directory.'/etc/'.$name.'.service.d');
            foreach (['multi-user.target.wants', 'example.target.requires'] as $dependency) {
                $this->assertFalse(is_link($this->directory.'/etc/'.$dependency.'/'.$name.'.service'));
            }
            $this->assertContains(['systemctl', 'stop', $name.'.service'], $this->commands);
            $this->assertContains(['systemctl', 'disable', $name.'.service'], $this->commands);
            $this->assertStringContainsString($name.'.service: stopped and removed.', $this->output);
        }
        $this->assertNotContains(['systemctl', 'reset-failed', 'email_queue.service'], $this->commands);
        $this->assertContains(['systemctl', 'reset-failed', 'fax_queue.service'], $this->commands);
        $this->assertFileExists($unrelated);
        $this->assertSame(['systemctl', 'daemon-reload'], $this->commands[count($this->commands) - 3]);
        $this->commands = [];
        $this->assertTrue($this->runCleanup());
        $this->assertCount(2, $this->commands);
        $this->assertSame(['show', 'show'], array_column($this->commands, 1));
        $this->assertStringContainsString('already absent', $this->output);
    }

    public function test_stopped_and_disabled_services_are_removed_too(): void
    {
        $path = $this->unit('email_queue');
        $this->fakeSystemd(['email_queue.service' => ['loaded', 'inactive']]);
        $this->assertTrue($this->runCleanup());
        $this->assertFileDoesNotExist($path);
        $this->assertContains(['systemctl', 'disable', 'email_queue.service'], $this->commands);
    }

    public function test_service_unloaded_during_disable_does_not_need_reset_failed(): void
    {
        $path = $this->unit('email_queue');
        $loaded = true;
        Process::fake(function ($process) use (&$loaded) {
            $this->commands[] = $process->command;
            $operation = $process->command[1];
            if ($operation === 'disable') {
                $loaded = false;
            }
            if ($operation === 'show') {
                $exists = $loaded && end($process->command) === 'email_queue.service';
                return Process::result(output: 'LoadState='.($exists ? 'loaded' : 'not-found')."\nActiveState=inactive\n",
                    exitCode: $exists ? 0 : 1);
            }
            if ($operation === 'reset-failed') {
                return Process::result(exitCode: 1, errorOutput: 'Unit not loaded');
            }
            return Process::result();
        });
        $this->assertTrue($this->runCleanup());
        $this->assertFileDoesNotExist($path);
        $this->assertNotContains('reset-failed', array_column($this->commands, 1));
    }

    public function test_masked_service_removes_only_its_symlink_and_leaves_the_target_intact(): void
    {
        $target = $this->directory.'/mask-target';
        File::put($target, 'preserve');
        symlink($target, $this->directory.'/etc/email_queue.service');
        $this->fakeSystemd(['email_queue.service' => ['masked', 'inactive']]);
        $this->assertTrue($this->runCleanup());
        $this->assertFalse(is_link($this->directory.'/etc/email_queue.service'));
        $this->assertSame('preserve', File::get($target));
        $this->assertNotContains(['systemctl', 'disable', 'email_queue.service'], $this->commands);
    }

    public function test_orphaned_enablement_links_are_removed_when_unit_is_already_missing(): void
    {
        File::ensureDirectoryExists($this->directory.'/etc/multi-user.target.wants');
        $link = $this->directory.'/etc/multi-user.target.wants/email_queue.service';
        symlink($this->directory.'/missing.service', $link);
        $this->fakeSystemd([]);
        $this->assertTrue($this->runCleanup());
        $this->assertFalse(is_link($link));
        $this->assertSame(['show', 'show', 'daemon-reload', 'show'], array_column($this->commands, 1));
    }

    /** @dataProvider serviceFailures */
    public function test_service_failure_preserves_its_definition_and_continues_with_the_other_queue(string $operation): void
    {
        $email = $this->unit('email_queue');
        $fax = $this->unit('fax_queue');
        $this->fakeSystemd([
            'email_queue.service' => ['loaded', $operation === 'reset-failed' ? 'failed' : 'activating'],
            'fax_queue.service' => ['loaded', 'activating'],
        ], $operation);
        $this->assertFalse($this->runCleanup());
        $this->assertFileExists($email);
        $this->assertFileDoesNotExist($fax);
        $this->assertStringContainsString('Could not fully remove email_queue.service', $this->output);
        $this->assertStringContainsString('fax_queue.service: stopped and removed.', $this->output);
        $this->assertStringContainsString('Continuing the application update', $this->output);
        $this->assertStringContainsString('removeLegacyQueueServices();', $this->output);
    }

    public static function serviceFailures(): array
    {
        return [['stop'], ['disable'], ['reset-failed']];
    }

    public function test_daemon_reload_failure_is_reported_without_claiming_success(): void
    {
        $path = $this->unit('email_queue');
        $this->fakeSystemd(['email_queue.service' => ['loaded', 'active']], 'daemon-reload');
        $this->assertFalse($this->runCleanup());
        $this->assertFileDoesNotExist($path);
        $this->assertStringContainsString('removal could not be verified', $this->output);
        $this->assertStringNotContainsString('stopped and removed', $this->output);
    }

    public function test_unavailable_systemd_preserves_files_and_warns(): void
    {
        $path = $this->unit('email_queue');
        Process::fake(fn () => Process::result(exitCode: 1, errorOutput: 'Failed to connect to bus'));
        $this->assertFalse($this->runCleanup());
        $this->assertFileExists($path);
        $this->assertStringContainsString('Failed to connect to bus', $this->output);
        $this->assertStringContainsString('Continuing the application update', $this->output);
    }

    public function test_process_exception_is_nonblocking(): void
    {
        $path = $this->unit('email_queue');
        Process::fake(fn () => throw new RuntimeException('Timed out'));
        $this->assertFalse($this->runCleanup());
        $this->assertFileExists($path);
        $this->assertStringContainsString('Timed out', $this->output);
    }

    public function test_fresh_install_scripts_and_service_definitions_do_not_install_retired_queues(): void
    {
        foreach (File::allFiles(base_path('install')) as $file) {
            if (! in_array($file->getExtension(), ['sh', 'conf', 'service'], true)) {
                continue;
            }
            $contents = preg_replace('/^\s*#.*$/m', '', File::get($file->getPathname()));
            $this->assertDoesNotMatchRegularExpression('/\b(?:email_queue|fax_queue)\b/', $contents, $file->getRelativePathname());
        }
    }
}
