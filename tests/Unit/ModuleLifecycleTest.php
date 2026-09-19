<?php

namespace Tests\Unit;

use App\Console\Commands\{ConfigureModule, RefreshModules};
use App\Services\{KeygenAPIService, ProFeaturesService};
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Process\Factory;
use Illuminate\Support\Facades\{Facade, Process};
use Nwidart\Modules\Facades\Module;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ModuleLifecycleTest extends TestCase
{
    private Application $app;
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/module-lifecycle-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        $this->app = new Application($this->directory);
        $this->app->instance('config', new Repository());
        $this->app->singleton(Factory::class, fn () => new Factory());
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($this->app);
        Module::swap(\Mockery::mock());
        Process::fake(); Process::preventStrayProcesses();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->directory);
        \Mockery::close(); Facade::clearResolvedInstances();
        parent::tearDown();
    }

    private function service(): ProFeaturesService
    {
        return new class(\Mockery::mock(KeygenAPIService::class)) extends ProFeaturesService {
            public function enableInstalledModule(string $moduleName): void { parent::enableInstalledModule($moduleName); }
            public function downloadAndDeployModule(string $licenseKey, string $moduleName, string $version, string $artifactName): bool|string
            { return parent::downloadAndDeployModule($licenseKey, $moduleName, $version, $artifactName); }
        };
    }

    public function test_lifecycle_uses_fresh_cli_after_enabling_module(): void
    {
        $commands = [];
        Process::fake(function ($process) use (&$commands) { $commands[] = $process->command; return Process::result(); });
        $this->service()->enableInstalledModule('ContactCenter');
        $this->assertSame([
            ['/usr/bin/php', base_path('artisan'), 'module:enable', 'ContactCenter', '--no-interaction'],
            ['/usr/bin/php', base_path('artisan'), 'modules:configure', 'ContactCenter', '--no-interaction'],
        ], $commands);
    }

    public function test_failed_enable_prevents_configure(): void
    {
        Process::fake(fn () => Process::result(exitCode: 1, errorOutput: 'enable failed'));
        try {
            $this->service()->enableInstalledModule('ContactCenter');
            $this->fail('Expected enable failure');
        } catch (\RuntimeException $error) { $this->assertStringContainsString('enable failed', $error->getMessage()); }
        Process::assertRanTimes(fn () => true, 1);
    }

    public function test_failed_hook_preserves_old_version_and_next_attempt_can_complete(): void
    {
        mkdir(base_path('Modules/ContactCenter'), 0755, true);
        file_put_contents(base_path('Modules/ContactCenter/module.json'), '{"name":"ContactCenter","version":"1.0.57"}');
        $tar = new \PharData($this->directory.'/release.tar');
        $tar->addFromString('module.json', '{"name":"ContactCenter","version":"1.0.58"}');
        $tar->compress(\Phar::GZ);
        $content = file_get_contents($this->directory.'/release.tar.gz');
        $keygen = \Mockery::mock(KeygenAPIService::class);
        $keygen->shouldReceive('downloadArtifact')->twice()->andReturn($content);
        $service = new class($keygen) extends ProFeaturesService {
            public function deploy(string $artifact = 'module.tar.gz'): bool|string
            { return $this->downloadAndDeployModule('test', 'ContactCenter', '1.0.58', $artifact); }
        };
        Process::fake(fn ($process) => in_array('modules:configure', $process->command)
            ? Process::result(exitCode: 1, errorOutput: 'Supervisor failed') : Process::result());
        $this->assertStringContainsString('Supervisor failed', $service->deploy());
        $this->assertSame('1.0.57', json_decode(file_get_contents(base_path('Modules/ContactCenter/module.json')), true)['version']);
        Process::fake();
        // Phar retains archive paths within one PHP process; a real retry boots
        // a new process. Use another fixture filename for this second extraction.
        $this->assertTrue($service->deploy('retry.tar.gz'));
        $this->assertSame('1.0.58', json_decode(file_get_contents(base_path('Modules/ContactCenter/module.json')), true)['version']);
    }

    private function configurationCommand(bool $automatic, array $failures = [], array $hooks = ['install', 'update', 'uninstall']): array
    {
        $module = \Mockery::mock()->shouldReceive('get')->with('migration')->andReturn(['automatic' => $automatic])->getMock();
        Module::shouldReceive('findOrFail')->with('ContactCenter')->andReturn($module);
        $command = new class extends ConfigureModule {
            public array $calls = [];
            public array $failures = [];
            public function call($command, array $arguments = []) {
                $this->calls[] = [$command, $arguments]; return $this->failures[$command] ?? 0;
            }
        };
        $command->failures = $failures;
        $command->setLaravel($this->app);
        $console = new ConsoleApplication();
        $console->add($command);
        foreach ($hooks as $hook) { $console->add(new Command('module:'.$hook.'-ContactCenter')); }
        return [$command, new CommandTester($command)];
    }

    public function test_contact_center_skips_migrations_but_seeds_and_runs_hooks(): void
    {
        [$command, $tester] = $this->configurationCommand(false);
        $this->assertSame(0, $tester->execute(['module' => 'ContactCenter']));
        $this->assertSame(['module:seed', 'module:install-ContactCenter', 'module:update-ContactCenter'], array_column($command->calls, 0));
    }

    public function test_other_modules_keep_automatic_migrations(): void
    {
        [$command, $tester] = $this->configurationCommand(true);
        $this->assertSame(0, $tester->execute(['module' => 'ContactCenter']));
        $this->assertSame(['module:migrate', ['module' => 'ContactCenter', '--force' => true]], $command->calls[0]);
    }

    public function test_update_only_runs_the_update_hook_without_migrations_seeders_or_install(): void
    {
        [$command, $tester] = $this->configurationCommand(true);
        $this->assertSame(0, $tester->execute(['module' => 'ContactCenter', '--update-only' => true]));
        $this->assertSame([['module:update-ContactCenter', []]], $command->calls);
    }

    public function test_update_only_propagates_hook_failure(): void
    {
        [$command, $tester] = $this->configurationCommand(false, ['module:update-ContactCenter' => 1]);
        $this->assertSame(1, $tester->execute(['module' => 'ContactCenter', '--update-only' => true]));
        $this->assertStringContainsString('Module configuration failed: module:update-ContactCenter', $tester->getDisplay());
    }

    public function test_update_only_is_a_noop_when_the_module_has_no_update_hook(): void
    {
        [$command, $tester] = $this->configurationCommand(true, [], []);
        $this->assertSame(0, $tester->execute(['module' => 'ContactCenter', '--update-only' => true]));
        $this->assertSame([], $command->calls);
    }

    /** @dataProvider gitMarkers */
    public function test_git_refresh_runs_local_hooks_without_version_changes_or_release_api(string $marker): void
    {
        mkdir(base_path('Modules/ContactCenter'), 0755, true);
        $manifest = '{"name":"ContactCenter","version":"1.0.58"}';
        file_put_contents(base_path('Modules/ContactCenter/module.json'), $manifest);
        if ($marker === 'directory') { mkdir(base_path('Modules/ContactCenter/.git')); }
        else { file_put_contents(base_path('Modules/ContactCenter/.git'), 'gitdir: /test/worktree'); }
        $module = \Mockery::mock()->shouldReceive('getName')->andReturn('ContactCenter')->getMock();
        Module::shouldReceive('allEnabled')->twice()->andReturn([$module]);
        $commands = [];
        Process::fake(function ($process) use (&$commands) { $commands[] = $process->command; return Process::result(); });

        // No DB or license service is configured: local hooks must run without
        // fetching release metadata, and must run again at the same version.
        for ($i = 0; $i < 2; $i++) {
            $result = $this->service()->refreshModules();
            $this->assertSame([], $result['errors']);
            $this->assertCount(1, $result['updated']);
        }
        $this->assertSame(array_fill(0, 2, ['/usr/bin/php', base_path('artisan'), 'modules:configure', 'ContactCenter', '--update-only', '--no-interaction']), $commands);
        $this->assertSame($manifest, file_get_contents(base_path('Modules/ContactCenter/module.json')));
    }

    public static function gitMarkers(): array
    {
        return [['directory'], ['file']];
    }

    public function test_git_refresh_reports_hook_failure(): void
    {
        mkdir(base_path('Modules/ContactCenter/.git'), 0755, true);
        $module = \Mockery::mock()->shouldReceive('getName')->andReturn('ContactCenter')->getMock();
        Module::shouldReceive('allEnabled')->once()->andReturn([$module]);
        Process::fake(fn () => Process::result(exitCode: 1, errorOutput: 'Supervisor failed'));
        $result = $this->service()->refreshModules();
        $this->assertSame([], $result['updated']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('ContactCenter: local update failed', $result['errors'][0]);
        $this->assertStringContainsString('Supervisor failed', $result['errors'][0]);
    }

    public function test_refresh_separates_git_checkouts_from_release_managed_modules(): void
    {
        mkdir(base_path('Modules/ContactCenter/.git'), 0755, true);
        $git = \Mockery::mock()->shouldReceive('getName')->andReturn('ContactCenter')->getMock();
        $release = \Mockery::mock()->shouldReceive('getName')->andReturn('Billing')->getMock();
        Module::shouldReceive('allEnabled')->once()->andReturn([$git, $release]);
        $service = new class(\Mockery::mock(KeygenAPIService::class)) extends ProFeaturesService {
            public array $releaseModules = [];
            protected function syncModules(string $mode, ?\Illuminate\Support\Collection $enabledModules = null, ?string $licenseOverride = null): array
            {
                $this->releaseModules = [$mode, $enabledModules->all()];
                return ['updated' => [], 'skipped' => ['Billing: already latest'], 'errors' => ['Release API error']];
            }
        };
        $result = $service->refreshModules();
        $this->assertSame(['enabled_only', ['Billing']], $service->releaseModules);
        $this->assertCount(1, $result['updated']);
        $this->assertSame(['Billing: already latest'], $result['skipped']);
        $this->assertSame(['Release API error'], $result['errors']);
        Process::assertRanTimes(fn () => true, 1);
    }

    public function test_disabled_git_checkout_is_not_updated(): void
    {
        mkdir(base_path('Modules/ContactCenter/.git'), 0755, true);
        Module::shouldReceive('allEnabled')->once()->andReturn([]);
        $this->assertSame(['updated' => [], 'skipped' => [], 'errors' => []], $this->service()->refreshModules());
        Process::assertNothingRan();
    }

    public function test_hook_failure_makes_configuration_fail(): void
    {
        [$command, $tester] = $this->configurationCommand(false, ['module:update-ContactCenter' => 1]);
        $this->assertSame(1, $tester->execute(['module' => 'ContactCenter']));
        $this->assertStringContainsString('Module configuration failed: module:update-ContactCenter', $tester->getDisplay());
    }

    public function test_uninstall_does_not_seed_migrate_or_run_update_hooks(): void
    {
        [$command, $tester] = $this->configurationCommand(false);
        $this->assertSame(0, $tester->execute(['module' => 'ContactCenter', '--uninstall' => true]));
        $this->assertSame(['module:uninstall-ContactCenter'], array_column($command->calls, 0));
    }

    public function test_update_restarts_only_installed_programs_declared_by_enabled_modules(): void
    {
        file_put_contents($this->directory.'/module.json', json_encode(['supervisor' => ['fspbx-contact-center-events', 'missing', '../unsafe', 'fspbx-contact-center-events']]));
        file_put_contents($this->directory.'/fspbx-contact-center-events.conf', 'installed');
        $module = \Mockery::mock()->shouldReceive('getPath')->andReturn($this->directory)->getMock();
        Module::shouldReceive('allEnabled')->once()->andReturn([$module]);
        $this->assertSame(['fspbx-contact-center-events'], $this->service()->getSupervisorProgramsToRestart($this->directory));
    }

    public function test_module_refresh_returns_failure_when_deployment_failed(): void
    {
        $service = \Mockery::mock(ProFeaturesService::class);
        $service->shouldReceive('refreshModules')->once()->andReturn(['updated' => [], 'skipped' => [], 'errors' => ['Supervisor failed']]);
        $this->app->instance(ProFeaturesService::class, $service);
        $command = new RefreshModules(); $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $this->assertSame(1, $tester->execute([]));
        $this->assertStringContainsString('Supervisor failed', $tester->getDisplay());
    }
}
