<?php

namespace Tests\Unit;

use App\Console\Commands\InstallFrontendAssets;
use App\Services\FrontendAssets;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\{Facade, Http};
use Nwidart\Modules\Facades\Module;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class InstallFrontendAssetsTest extends TestCase
{
    private string $root;
    private Application $app;
    private $previousContainer;
    private $previousFacade;

    protected function setUp(): void
    {
        $this->previousContainer = Container::getInstance();
        $this->previousFacade = Facade::getFacadeApplication();
        $this->root = sys_get_temp_dir().'/fspbx-frontend-command-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/config', 0755, true);
        file_put_contents($this->root.'/config/version.php', '<?php return ["release" => "1.2.3"];');
        $this->app = new Application($this->root);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        Module::swap(\Mockery::mock());
        Module::shouldReceive('allEnabled')->andReturn([]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        (new FrontendAssets($this->root))->removeDirectory($this->root);
        \Mockery::close();
        Container::setInstance($this->previousContainer);
        Facade::setFacadeApplication($this->previousFacade);
        Facade::clearResolvedInstances();
    }

    private function command(FrontendAssets $assets): CommandTester
    {
        $this->app->instance(FrontendAssets::class, $assets);
        $command = new InstallFrontendAssets();
        $command->setLaravel($this->app);
        return new CommandTester($command);
    }

    public function test_missing_release_without_matching_local_build_fails_without_activation(): void
    {
        Http::fake(['*' => Http::response('', 404)]);
        $assets = $this->createMock(FrontendAssets::class);
        $assets->expects($this->once())->method('prepareExistingLocal')->willReturn(false);
        $assets->expects($this->never())->method('activatePrepared');
        $assets->expects($this->never())->method('extract');
        $command = $this->command($assets);
        $this->assertSame(1, $command->execute([]));
        $this->assertStringContainsString('No npm build was started', $command->getDisplay());
        Http::assertSentCount(1);
    }

    public function test_development_checkout_reuses_verified_local_build_without_a_setting(): void
    {
        Http::fake(['*' => Http::response('', 404)]);
        $assets = $this->createMock(FrontendAssets::class);
        $assets->expects($this->once())->method('prepareExistingLocal')->willReturn(true);
        $assets->method('prepared')->willReturn(['metadata' => ['origin' => 'local']]);
        $assets->expects($this->once())->method('activatePrepared')->willReturn([]);
        $command = $this->command($assets);
        $this->assertSame(0, $command->execute([]));
        $this->assertStringContainsString('Verified local frontend assets retained', $command->getDisplay());
        $this->assertStringNotContainsString('Replacing', $command->getDisplay());
    }

    public function test_download_checksum_is_checked_before_extraction_and_preparation_does_not_activate(): void
    {
        $body = 'fixture archive bytes';
        Http::fake(function ($request, $options) use ($body) {
            if (str_ends_with($request->url(), '.sha256')) return Http::response(hash('sha256', $body).'  archive.tar.gz');
            file_put_contents($options['sink'], $body);
            return Http::response($body);
        });
        $assets = $this->createMock(FrontendAssets::class);
        $assets->expects($this->once())->method('extract');
        $assets->expects($this->once())->method('prepare')->with($this->anything(), '1.2.3');
        $assets->expects($this->never())->method('activatePrepared');
        $command = $this->command($assets);
        $this->assertSame(0, $command->execute(['--prepare' => true]));
        Http::assertSentCount(2);
    }

    public function test_corrupt_download_is_not_extracted_or_activated(): void
    {
        Http::fake(function ($request, $options) {
            if (str_ends_with($request->url(), '.sha256')) return Http::response(str_repeat('a', 64));
            file_put_contents($options['sink'], 'corrupt');
            return Http::response('corrupt');
        });
        $assets = $this->createMock(FrontendAssets::class);
        $assets->method('prepareExistingLocal')->willReturn(false);
        $assets->expects($this->never())->method('extract');
        $assets->expects($this->never())->method('activatePrepared');
        $command = $this->command($assets);
        $this->assertSame(1, $command->execute([]));
        $this->assertStringContainsString('checksum failed', $command->getDisplay());
    }

    public function test_activation_reports_replacing_local_assets_without_another_download(): void
    {
        Http::fake();
        $assets = $this->createMock(FrontendAssets::class);
        $assets->method('prepared')->willReturn(['metadata' => ['origin' => 'release']]);
        $assets->method('state')->willReturn(['core' => ['metadata' => ['origin' => 'local']]]);
        $assets->expects($this->once())->method('activatePrepared')->willReturn([]);
        $command = $this->command($assets);
        $this->assertSame(0, $command->execute(['--activate' => true]));
        $this->assertStringContainsString('Replacing the locally compiled frontend', $command->getDisplay());
        Http::assertNothingSent();
    }

    public function test_explicit_bad_archive_is_not_hidden_by_local_build_fallback(): void
    {
        Http::fake();
        $assets = $this->createMock(FrontendAssets::class);
        $assets->expects($this->never())->method('prepareExistingLocal');
        $assets->expects($this->never())->method('activatePrepared');
        $command = $this->command($assets);
        $this->assertSame(1, $command->execute(['--archive' => $this->root.'/missing.tar.gz']));
        Http::assertNothingSent();
    }
}
