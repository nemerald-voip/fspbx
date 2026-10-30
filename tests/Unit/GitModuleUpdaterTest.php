<?php

namespace Tests\Unit;

use App\Services\{FrontendAssets, GitModuleUpdater};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class GitModuleUpdaterTest extends TestCase
{
    private string $temporary;
    private string $root;
    private string $producer;
    private string $checkout;
    private string $remote;
    private FrontendAssets $assets;
    private GitModuleUpdater $updater;
    private string $before;

    protected function setUp(): void
    {
        $this->temporary = sys_get_temp_dir().'/git-module-test-'.bin2hex(random_bytes(8));
        $this->root = $this->temporary.'/app';
        $this->producer = $this->temporary.'/producer';
        $this->checkout = $this->root.'/Modules/ContactCenter';
        $this->remote = $this->temporary.'/remote.git';
        mkdir($this->root.'/Modules', 0755, true);
        mkdir($this->producer);
        $this->git($this->temporary, ['init', '--bare', $this->remote]);
        $this->git($this->producer, ['init', '-b', 'main']);
        $this->git($this->producer, ['config', 'user.name', 'Fixture']);
        $this->git($this->producer, ['config', 'user.email', 'fixture@example.invalid']);
        $this->writeSource('1.0.1');
        $this->git($this->producer, ['add', '.']);
        $this->git($this->producer, ['commit', '-m', 'Initial']);
        $this->git($this->producer, ['remote', 'add', 'origin', $this->remote]);
        $this->git($this->producer, ['push', '-u', 'origin', 'main']);
        $this->git($this->temporary, ['clone', '--branch', 'main', $this->remote, $this->checkout]);
        $this->before = $this->git($this->checkout, ['rev-parse', 'HEAD']);
        $this->assets = new FrontendAssets($this->root);
        $this->updater = new GitModuleUpdater($this->assets, $this->root);
        $this->assets->prepare($this->package('core'), '2.1.4');
    }

    protected function tearDown(): void
    {
        $this->assets->removeDirectory($this->temporary);
        parent::tearDown();
    }

    private function execute(string $directory, array $command): string
    {
        $process = new Process($command, $directory, ['GIT_TERMINAL_PROMPT' => '0'], null, 30);
        $process->mustRun();
        return trim($process->getOutput());
    }

    private function git(string $directory, array $arguments): string
    {
        return $this->execute($directory, ['git', ...$arguments]);
    }

    private function writeSource(string $version): void
    {
        $pages = $this->producer.'/Resources/assets/js/Pages';
        if (!is_dir($pages)) mkdir($pages, 0755, true);
        file_put_contents($this->producer.'/module.json', json_encode(['name' => 'ContactCenter', 'version' => $version]));
        file_put_contents($pages.'/Dashboard.vue', '<template>'.$version.'</template>');
    }

    private function advance(): string
    {
        $this->writeSource('1.0.2');
        $this->git($this->producer, ['add', '.']);
        $this->git($this->producer, ['commit', '-m', 'New source']);
        $this->git($this->producer, ['push', 'origin', 'main']);
        return $this->git($this->producer, ['rev-parse', 'HEAD']);
    }

    private function package(string $kind = 'module'): string
    {
        $directory = $this->temporary.'/package-'.bin2hex(random_bytes(5));
        mkdir($directory.'/assets', 0755, true);
        file_put_contents($directory.'/assets/app.js', 'export const version = 1;');
        file_put_contents($directory.'/assets/app.css', 'body{}');
        file_put_contents($directory.'/manifest.json', json_encode([
            'resources/js/vue.js' => ['file' => 'assets/app.js'],
            'resources/scss/tailwind.css' => ['file' => 'assets/app.css'],
        ]));
        $files = [];
        foreach (['assets/app.js', 'assets/app.css', 'manifest.json'] as $file) $files[$file] = hash_file('sha256', $directory.'/'.$file);
        $meta = [
            'schema' => 1, 'api' => 1, 'kind' => $kind, 'name' => $kind === 'core' ? 'fspbx' : 'ContactCenter',
            'version' => $kind === 'core' ? '2.1.4' : json_decode(file_get_contents($this->producer.'/module.json'), true)['version'],
            'runtime' => str_repeat('a', 64), 'origin' => 'release', 'files' => $files,
            'sources' => $kind === 'core' ? $this->assets->sourceFiles() : $this->assets->sourceFiles('ContactCenter', $this->producer),
            'css' => ['assets/app.css'], 'entry' => 'assets/app.js', 'imports' => ['vue' => 'assets/app.js'],
        ];
        file_put_contents($directory.'/frontend.json', json_encode($meta));
        return $directory;
    }

    private function publish(string $package): string
    {
        $repo = dirname(__DIR__, 2);
        return $this->execute($this->producer, ['bash', $repo.'/.github/scripts/publish-module-frontend.sh', 'HEAD', $package, $repo]);
    }

    private function assertRejected(string $message): void
    {
        $configured = false;
        try {
            $this->updater->update('ContactCenter', function () use (&$configured) { $configured = true; });
            $this->fail('Expected update rejection');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString($message, $error->getMessage());
        }
        $this->assertFalse($configured);
        $this->assertSame($this->before, $this->git($this->checkout, ['rev-parse', 'HEAD']));
        $this->assertSame([], $this->assets->state()['modules']);
    }

    public function test_fast_forward_installs_exact_assets_without_changing_source_branch_contents(): void
    {
        $target = $this->advance();
        $package = $this->package();
        $this->publish($package);
        $this->assertStringContainsString('already published', $this->publish($package));
        $configured = 0;
        $this->updater->update('ContactCenter', function () use (&$configured) { $configured++; });
        $this->assertSame($target, $this->git($this->checkout, ['rev-parse', 'HEAD']));
        $this->assertSame('', $this->git($this->checkout, ['status', '--porcelain']));
        $this->assertSame('1.0.2', $this->assets->state()['modules']['ContactCenter']['metadata']['version']);
        $this->assertSame('release', $this->assets->state()['modules']['ContactCenter']['metadata']['origin']);
        $this->assertSame(1, $configured);
        $this->assertDirectoryDoesNotExist($this->checkout.'/Resources/dist');
        $this->assets->activatePrepared();
        $this->assertArrayHasKey('ContactCenter', $this->assets->browserConfiguration(['ContactCenter'])['modules']);
    }

    public function test_missing_bundle_leaves_old_checkout_and_assets_untouched(): void
    {
        $this->advance();
        $this->assertRejected('Matching frontend assets are unavailable');
    }

    public function test_missing_new_bundle_preserves_an_existing_active_module(): void
    {
        $this->publish($this->package());
        $this->updater->update('ContactCenter', fn () => null);
        $this->assets->activatePrepared();
        $before = $this->assets->state();
        $this->advance();
        try {
            $this->updater->update('ContactCenter', fn () => $this->fail('Hooks must not run'));
            $this->fail('Expected missing package rejection');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Matching frontend assets are unavailable', $error->getMessage());
        }
        $this->assertSame($before, $this->assets->state());
        $this->assertSame($this->before, $this->git($this->checkout, ['rev-parse', 'HEAD']));
    }

    public function test_publisher_preserves_source_index_and_rejects_replacing_a_package(): void
    {
        $this->advance();
        file_put_contents($this->producer.'/staged.php', '<?php // preserve staged work');
        $this->git($this->producer, ['add', 'staged.php']);
        $before = $this->git($this->producer, ['status', '--porcelain']);
        $package = $this->package();
        $this->publish($package);
        $this->assertSame($before, $this->git($this->producer, ['status', '--porcelain']));
        $meta = json_decode(file_get_contents($package.'/frontend.json'), true);
        file_put_contents($package.'/assets/app.js', 'export const version = 2;');
        $meta['files']['assets/app.js'] = hash_file('sha256', $package.'/assets/app.js');
        file_put_contents($package.'/frontend.json', json_encode($meta));
        try {
            $this->publish($package);
            $this->fail('Expected immutable publication rejection');
        } catch (\Symfony\Component\Process\Exception\ProcessFailedException $error) {
            $this->assertStringContainsString('Refusing to replace', $error->getProcess()->getErrorOutput());
        }
        $this->updater->update('ContactCenter', fn () => null);
        $this->assertNotSame($meta['files'], $this->assets->state()['modules']['ContactCenter']['metadata']['files']);
    }

    /** @dataProvider localChanges */
    public function test_local_changes_are_preserved(string $file): void
    {
        $this->advance();
        file_put_contents($this->checkout.'/'.$file, 'local work');
        $this->assertRejected('Local changes or untracked files');
        $this->assertSame('local work', file_get_contents($this->checkout.'/'.$file));
    }

    public static function localChanges(): array
    {
        return [['module.json'], ['untracked.txt']];
    }

    public function test_local_commits_are_not_reset_or_merged(): void
    {
        $this->advance();
        file_put_contents($this->checkout.'/local.txt', 'local commit');
        $this->git($this->checkout, ['add', '.']);
        $this->git($this->checkout, ['-c', 'user.name=Fixture', '-c', 'user.email=test@example.invalid', 'commit', '-m', 'Local']);
        $this->before = $this->git($this->checkout, ['rev-parse', 'HEAD']);
        $this->assertRejected('cannot be fast-forwarded');
    }

    public function test_detached_checkout_is_preserved(): void
    {
        $this->git($this->checkout, ['checkout', '--detach']);
        $this->assertRejected('Detached Git checkout');
    }

    public function test_missing_upstream_is_reported(): void
    {
        $this->git($this->checkout, ['branch', '--unset-upstream']);
        $this->assertRejected('Configure an upstream');
    }

    public function test_private_remote_access_failure_does_not_expose_remote_credentials(): void
    {
        $this->git($this->checkout, ['remote', 'set-url', 'origin', $this->temporary.'/secret-token-missing.git']);
        $this->assertRejected('Git fetch failed');
    }

    /** @dataProvider invalidBundles */
    public function test_invalid_bundle_is_rejected_before_pull(string $failure, string $message): void
    {
        $target = $this->advance();
        $package = $this->package();
        $meta = json_decode(file_get_contents($package.'/frontend.json'), true);
        if ($failure === 'runtime') $meta['runtime'] = str_repeat('b', 64);
        if ($failure === 'source') $meta['sources']['module.json'] = str_repeat('c', 64);
        if ($failure === 'checksum') file_put_contents($package.'/assets/app.js', 'corrupt');
        file_put_contents($package.'/frontend.json', json_encode($meta));
        // Deliberately bypass the publisher's validation to model an invalid remote package.
        $this->git($package, ['init']);
        $this->git($package, ['add', '.']);
        $this->git($package, ['-c', 'user.name=Fixture', '-c', 'user.email=test@example.invalid', 'commit', '-m', 'Invalid package']);
        $this->git($package, ['push', $this->remote, 'HEAD:refs/tags/frontend/'.$target.'/'.str_repeat('a', 64)]);
        $this->assertRejected($message);
    }

    public static function invalidBundles(): array
    {
        return [['runtime', 'compatible FS PBX release'], ['source', 'do not match'], ['checksum', 'checksum failed']];
    }

    public function test_matching_existing_bundle_is_reused_and_hooks_retry(): void
    {
        $this->advance();
        $this->publish($this->package());
        try {
            $this->updater->update('ContactCenter', fn () => throw new RuntimeException('Hook failed'));
            $this->fail('Expected hook failure');
        } catch (RuntimeException $error) { $this->assertSame('Hook failed', $error->getMessage()); }
        $this->assets->activatePrepared();
        $installed = $this->assets->state()['modules']['ContactCenter'];
        // A PHP-only commit can reuse an exact frontend without another asset ref.
        file_put_contents($this->producer.'/backend.php', '<?php // changed');
        $this->git($this->producer, ['add', '.']);
        $this->git($this->producer, ['commit', '-m', 'Backend only']);
        $this->git($this->producer, ['push', 'origin', 'main']);
        $configured = false;
        $this->updater->update('ContactCenter', function () use (&$configured) { $configured = true; });
        $this->assertTrue($configured);
        $this->assertSame($installed, $this->assets->state()['modules']['ContactCenter']);
        $this->assertFileExists($this->checkout.'/backend.php');
    }

    public function test_linked_worktree_uses_its_tracking_branch(): void
    {
        $clone = $this->temporary.'/clone';
        rename($this->checkout, $clone);
        $this->git($clone, ['worktree', 'add', '-b', 'production', $this->checkout, 'origin/main']);
        $target = $this->advance();
        $this->publish($this->package());
        $this->updater->update('ContactCenter', fn () => null);
        $this->assertFileExists($this->checkout.'/.git');
        $this->assertSame($target, $this->git($this->checkout, ['rev-parse', 'HEAD']));
    }

    public function test_module_without_frontend_pulls_without_an_asset_ref(): void
    {
        $this->git($this->producer, ['rm', '-r', 'Resources']);
        $this->git($this->producer, ['commit', '-m', 'Backend module']);
        $this->git($this->producer, ['push', 'origin', 'main']);
        $this->updater->update('ContactCenter', fn () => null);
        $this->assertSame($this->git($this->producer, ['rev-parse', 'HEAD']), $this->git($this->checkout, ['rev-parse', 'HEAD']));
    }
}
