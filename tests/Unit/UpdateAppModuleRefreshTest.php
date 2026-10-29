<?php

namespace Tests\Unit;

use App\Console\Commands\RefreshModules;
use App\Console\Commands\UpdateApp;
use App\Models\ProFeatures;
use App\Services\GitHubApiService;
use App\Services\KeygenAPIService;
use App\Services\ProFeaturesService;
use Illuminate\Config\Repository;
use Illuminate\Console\OutputStyle;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Process;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Nwidart\Modules\Facades\Module;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class UpdateAppModuleRefreshTest extends TestCase
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
        $this->directory = sys_get_temp_dir().'/fspbx-module-update-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/config', 0777, true);
        file_put_contents($this->directory.'/config/version.php', '<?php return ["release" => "99.0.0"];');
        $this->app = new Application($this->directory);
        $this->app->instance('config', new Repository(['app' => ['version' => '99.0.0']]));
        $this->app->instance('translator', new Translator(new ArrayLoader(), 'en-us'));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        Module::swap(\Mockery::mock());
        Process::fake();
        Process::preventStrayProcesses();
        $this->output = new BufferedOutput();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->directory);
        \Mockery::close();
        Container::setInstance($this->previousContainer);
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Facade::clearResolvedInstances();
        parent::tearDown();
    }

    private function command(): UpdateApp
    {
        $command = new class($this->createMock(GitHubApiService::class)) extends UpdateApp {
            public array $shellCommands = [];
            public array $artisanCommands = [];
            public ?\Throwable $refreshException = null;

            protected function executeCommand($command, $timeout = 60, $failOnError = true)
            {
                // Record deployment work without running it on the host.
                $this->shellCommands[] = $command;
            }

            public function call($command, array $arguments = [])
            {
                $this->artisanCommands[] = $command;
                if ($command === 'modules:refresh') {
                    if ($this->refreshException) {
                        throw $this->refreshException;
                    }
                    $refresh = new RefreshModules();
                    $refresh->setLaravel($this->laravel);

                    return $refresh->run(new ArrayInput($arguments), $this->output);
                }

                return 0;
            }
        };
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), $this->output));

        return $command;
    }

    /**
     * @dataProvider unusableLicenses
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_unusable_license_skips_module_work_and_allows_update_to_finish(?string $license, $response, string $expectedWarning): void
    {
        mkdir($this->directory.'/Modules/ContactCenter', 0777, true);
        file_put_contents($this->directory.'/Modules/ContactCenter/module.json', '{"name":"ContactCenter","version":"1.0.0"}');
        $keygen = $this->createMock(KeygenAPIService::class);
        if ($license) {
            $validation = $keygen->expects($this->once())->method('validateLicenseKey')->with($license);
            if ($response instanceof \Throwable) {
                $validation->willThrowException($response);
            } else {
                $validation->willReturn($response);
            }
        } else {
            $keygen->expects($this->never())->method('validateLicenseKey');
        }
        $keygen->expects($this->never())->method('getEntitlementsByLicense');
        $keygen->expects($this->never())->method('getReleases');
        $keygen->expects($this->never())->method('downloadArtifact');

        $service = new class($keygen) extends ProFeaturesService {
            public ?ProFeatures $pro = null;
            protected function getProRow(): ?ProFeatures { return $this->pro; }
        };
        $service->pro = $license === null ? null : new ProFeatures(['license' => $license]);
        $this->app->instance(ProFeaturesService::class, $service);
        $module = \Mockery::mock();
        $module->shouldReceive('getName')->andReturn('ContactCenter');
        $module->shouldReceive('getPath')->andReturn($this->directory.'/Modules/ContactCenter');
        Module::shouldReceive('allEnabled')->andReturn([$module]);

        $command = $this->command();
        $command->handle();

        $output = $this->output->fetch();
        $this->assertStringContainsString($expectedWarning, $output);
        $this->assertStringContainsString('Continuing the application update', $output);
        $this->assertStringContainsString('Update completed successfully!', $output);
        $this->assertContains('php artisan route:cache', $command->shellCommands);
        $this->assertContains('php artisan db:seed --force', $command->shellCommands);
        $this->assertContains('npm run build', $command->shellCommands);
        $this->assertContains('horizon:terminate', $command->artisanCommands);
        Process::assertNothingRan();
    }

    public static function unusableLicenses(): array
    {
        return [
            'no Pro Features row' => [null, null, 'ProFeatures row not found'],
            'missing key' => ['', null, 'No Pro Features license key found'],
            'invalid' => ['test-license', false, 'Pro Features License invalid or expired'],
            'expired' => ['test-license', ['meta' => ['valid' => false, 'code' => 'EXPIRED']], 'Pro Features License invalid or expired'],
            'revoked' => ['test-license', ['meta' => ['valid' => false, 'code' => 'REVOKED']], 'Pro Features License invalid or expired'],
            'malformed response' => ['test-license', ['meta' => []], 'Pro Features License invalid or expired'],
            'validation unavailable' => ['test-license', new \RuntimeException('License API unavailable'), 'License API unavailable'],
        ];
    }

    /**
     * @dataProvider refreshOutcomes
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_module_command_outcome_does_not_abort_application_update(array $errors, ?\Throwable $exception): void
    {
        $service = $this->createMock(ProFeaturesService::class);
        $service->method('refreshModules')->willReturn(['updated' => [], 'skipped' => [], 'errors' => $errors]);
        $service->method('getSupervisorProgramsToRestart')->willReturn([]);
        $this->app->instance(ProFeaturesService::class, $service);
        $command = $this->command();
        $command->refreshException = $exception;

        $command->handle();

        $output = $this->output->fetch();
        $this->assertStringContainsString('Update completed successfully!', $output);
        $this->assertContains('npm run build', $command->shellCommands);
        if ($errors || $exception) {
            $this->assertStringContainsString('Continuing the application update', $output);
            $this->assertStringContainsString($exception ? $exception->getMessage() : $errors[0], $output);
        } else {
            $this->assertStringNotContainsString('Continuing the application update', $output);
        }
        Process::assertNothingRan();
    }

    public static function refreshOutcomes(): array
    {
        return [
            'success' => [[], null],
            'deployment failure' => [['Module deployment failed'], null],
            'command exception' => [[], new \RuntimeException('Module command unavailable')],
            'command error' => [[], new \Error('Module command could not boot')],
        ];
    }
}
