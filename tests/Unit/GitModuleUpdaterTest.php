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
        $this->git($this->producer, ['pull', '--ff-only']);
        $this->writeSource('1.0.2');
        $this->git($this->producer, ['add', '.']);
        $this->git($this->producer, ['commit', '-m', 'New source']);
        $this->git($this->producer, ['push', 'origin', 'main']);
        return $this->git($this->producer, ['rev-parse', 'HEAD']);
    }

    private function pullManually(): void
    {
        $this->git($this->checkout, ['pull', '--ff-only']);
        $this->before = $this->git($this->checkout, ['rev-parse', 'HEAD']);
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

    private function publish(string $package, string $tag = ''): string
    {
        $repo = dirname(__DIR__, 2);
        return $this->execute($this->producer, ['bash', $repo.'/.github/scripts/bundle-module-frontend.sh', 'HEAD', $package, $repo, 'main', 'origin', $tag]);
    }

    private function copyPackage(string $package): void
    {
        $destination = $this->checkout.'/Resources/dist';
        $this->assets->removeDirectory($destination);
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($package, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = $destination.'/'.substr($file->getPathname(), strlen($package) + 1);
            if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
            copy($file->getPathname(), $path);
        }
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

    public function test_manual_pull_is_followed_by_exact_asset_installation(): void
    {
        $this->advance();
        $package = $this->package();
        $target = $this->publish($package);
        $this->assertSame($target, $this->publish($package));
        $this->pullManually();
        $configured = 0;
        $this->updater->update('ContactCenter', function () use (&$configured) { $configured++; });
        $this->assertSame($target, $this->git($this->checkout, ['rev-parse', 'HEAD']));
        $this->assertSame('', $this->git($this->checkout, ['status', '--porcelain']));
        $this->assertSame('1.0.2', $this->assets->state()['modules']['ContactCenter']['metadata']['version']);
        $this->assertSame('release', $this->assets->state()['modules']['ContactCenter']['metadata']['origin']);
        $this->assertSame(1, $configured);
        $this->assertFileExists($this->checkout.'/Resources/dist/frontend.json');
        $this->assets->activatePrepared();
        $this->assertArrayHasKey('ContactCenter', $this->assets->browserConfiguration(['ContactCenter'])['modules']);
    }

    public function test_refresh_installs_bundled_assets_without_pulling_newer_source(): void
    {
        $this->publish($this->package());
        $this->pullManually();
        $this->advance();
        $this->publish($this->package());
        $this->updater->update('ContactCenter', fn () => null);
        $this->assertSame($this->before, $this->git($this->checkout, ['rev-parse', 'HEAD']));
        $this->assertSame($this->before, $this->git($this->checkout, ['rev-parse', 'origin/main']));
        $this->assertSame('', $this->git($this->checkout, ['status', '--porcelain']));
        $this->assertSame('1.0.1', $this->assets->state()['modules']['ContactCenter']['metadata']['version']);
    }

    public function test_missing_bundle_leaves_old_checkout_and_assets_untouched(): void
    {
        $this->advance();
        $this->assertRejected('Matching frontend assets are missing or incompatible');
    }

    public function test_missing_new_bundle_preserves_an_existing_active_module(): void
    {
        $this->publish($this->package());
        $this->pullManually();
        $this->updater->update('ContactCenter', fn () => null);
        $this->assets->activatePrepared();
        $before = $this->assets->state();
        $this->advance();
        $this->pullManually();
        try {
            $this->updater->update('ContactCenter', fn () => $this->fail('Hooks must not run'));
            $this->fail('Expected missing package rejection');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Matching frontend assets are missing or incompatible', $error->getMessage());
        }
        $this->assertSame($before, $this->assets->state());
        $this->assertSame($this->before, $this->git($this->checkout, ['rev-parse', 'HEAD']));
    }

    public function test_bundler_preserves_source_index_and_published_release(): void
    {
        $this->advance();
        $this->pullManually();
        file_put_contents($this->producer.'/staged.php', '<?php // preserve staged work');
        $this->git($this->producer, ['add', 'staged.php']);
        $before = $this->git($this->producer, ['status', '--porcelain']);
        $package = $this->package();
        $source = $this->git($this->producer, ['rev-parse', 'HEAD']);
        $bundle = $this->publish($package, 'v1.0.2');
        $this->assertSame($before, $this->git($this->producer, ['status', '--porcelain']));
        $this->assertSame($source, $this->git($this->producer, ['rev-parse', 'HEAD']));
        $this->assertSame($source, $this->git($this->producer, ['rev-parse', $bundle.'^']));
        $this->assertSame('', $this->git($this->producer, ['diff', $source, $bundle, '--', '.', ':(exclude)Resources/dist']));
        $this->git($this->producer, ['tag', 'v1.0.2', $bundle]);
        $this->git($this->producer, ['push', 'origin', 'refs/tags/v1.0.2']);
        $this->assertSame($bundle, $this->publish($package, 'v1.0.2'));
        $meta = json_decode(file_get_contents($package.'/frontend.json'), true);
        file_put_contents($package.'/assets/app.js', 'export const version = 2;');
        $meta['files']['assets/app.js'] = hash_file('sha256', $package.'/assets/app.js');
        file_put_contents($package.'/frontend.json', json_encode($meta));
        try {
            $this->publish($package, 'v1.0.2');
            $this->fail('Expected immutable publication rejection');
        } catch (\Symfony\Component\Process\Exception\ProcessFailedException $error) {
            $this->assertStringContainsString('Refusing to replace', $error->getProcess()->getErrorOutput());
        }
        $this->pullManually();
        $this->updater->update('ContactCenter', fn () => null);
        $this->assertNotSame($meta['files'], $this->assets->state()['modules']['ContactCenter']['metadata']['files']);
    }

    public function test_bundler_rejects_a_build_for_source_that_has_advanced(): void
    {
        $package = $this->package();
        $target = $this->advance();
        $repo = dirname(__DIR__, 2);
        try {
            $this->execute($this->checkout, ['bash', $repo.'/.github/scripts/bundle-module-frontend.sh', 'HEAD', $package, $repo]);
            $this->fail('Expected stale build rejection');
        } catch (\Symfony\Component\Process\Exception\ProcessFailedException $error) {
            $this->assertStringContainsString('Module source changed while building', $error->getProcess()->getErrorOutput());
        }
        $this->assertSame($target, $this->git($this->remote, ['rev-parse', 'refs/heads/main']));
        $this->assertSame($this->before, $this->git($this->checkout, ['rev-parse', 'HEAD']));
    }

    public function test_bundler_checks_committed_source_instead_of_dirty_frontend_files(): void
    {
        file_put_contents($this->producer.'/Resources/assets/js/Pages/Dashboard.vue', 'Uncommitted frontend');
        $package = $this->package();
        try {
            $this->publish($package);
            $this->fail('Expected uncommitted build rejection');
        } catch (\Symfony\Component\Process\Exception\ProcessFailedException $error) {
            $this->assertStringContainsString('do not match', $error->getProcess()->getErrorOutput());
        }
        $this->assertSame($this->before, $this->git($this->remote, ['rev-parse', 'refs/heads/main']));
        $this->assertSame('Uncommitted frontend', file_get_contents($this->producer.'/Resources/assets/js/Pages/Dashboard.vue'));
    }

    public function test_replacing_a_bundle_removes_obsolete_chunks_from_source_and_archives(): void
    {
        $package = $this->package();
        file_put_contents($package.'/assets/obsolete.js', '// old chunk');
        $meta = json_decode(file_get_contents($package.'/frontend.json'), true);
        $meta['files']['assets/obsolete.js'] = hash_file('sha256', $package.'/assets/obsolete.js');
        file_put_contents($package.'/frontend.json', json_encode($meta));
        $this->publish($package);
        $this->git($this->producer, ['pull', '--ff-only']);
        $package = $this->package();
        $bundle = $this->publish($package, 'v1.0.1');
        $this->assertSame($bundle, $this->git($this->remote, ['rev-parse', 'refs/tags/v1.0.1']));
        $this->pullManually();
        $this->assertFileDoesNotExist($this->checkout.'/Resources/dist/assets/obsolete.js');
        $archive = $this->temporary.'/release.tar.gz';
        $this->git($this->producer, ['archive', '--format=tar.gz', '--output='.$archive, $bundle]);
        $this->assets->extract($archive, $this->temporary.'/release');
        $meta = $this->assets->validateModule($this->temporary.'/release/Resources/dist', 'ContactCenter', '1.0.1');
        $this->assets->verifyModuleSource($this->temporary.'/release', $meta);
        $this->assertSame(file_get_contents($package.'/frontend.json'), file_get_contents($this->temporary.'/release/Resources/dist/frontend.json'));
        $this->assertFileDoesNotExist($this->temporary.'/release/Resources/dist/assets/obsolete.js');
    }

    public function test_released_bundle_can_be_retried_after_new_source_is_pushed(): void
    {
        $package = $this->package();
        $source = $this->git($this->producer, ['rev-parse', 'HEAD']);
        $bundle = $this->publish($package, 'v1.0.1');
        $latest = $this->advance();
        // Simulate rerunning the original workflow at its original SHA.
        $this->git($this->producer, ['checkout', '--detach', $source]);
        $this->assertSame($bundle, $this->publish($package, 'v1.0.1'));
        $this->assertSame($latest, $this->git($this->remote, ['rev-parse', 'refs/heads/main']));
        $this->assertSame($bundle, $this->git($this->remote, ['rev-parse', 'refs/tags/v1.0.1']));
    }

    public function test_contact_center_version_check_accepts_bundled_tag_on_a_workflow_retry(): void
    {
        $workflow = dirname(__DIR__, 2).'/Modules/ContactCenter/.github/workflows/main.yml';
        if (!is_file($workflow)) $this->markTestSkipped('Contact Center is optional.');
        $steps = \Symfony\Component\Yaml\Yaml::parseFile($workflow)['jobs']['create-release']['steps'];
        $script = null;
        foreach ($steps as $step) {
            if (($step['id'] ?? null) === 'module_version') $script = $step['run'];
        }
        $this->assertNotNull($script);
        $source = $this->advance();
        $output = $this->temporary.'/workflow-output';
        $run = function () use ($script, $source, $output): string {
            file_put_contents($output, '');
            (new Process(['bash', '-c', $script], $this->producer, [
                'GITHUB_SHA' => $source, 'BEFORE_SHA' => $this->before,
                'GITHUB_OUTPUT' => $output,
            ], null, 30))->mustRun();
            return file_get_contents($output);
        };
        $this->assertStringContainsString('tag_exists=false', $run());
        $bundle = $this->publish($this->package(), 'v1.0.2');
        $this->git($this->producer, ['fetch', '--tags']);
        $retry = $run();
        $this->assertStringContainsString('tag_exists=true', $retry);
        $this->assertStringContainsString('publish_tag=v1.0.2', $retry);
        $this->assertSame($bundle, $this->git($this->remote, ['rev-parse', 'refs/tags/v1.0.2']));
        $this->assertSame($bundle, $this->git($this->remote, ['rev-parse', 'refs/heads/main']));
    }

    /** @dataProvider localChanges */
    public function test_unbuilt_frontend_changes_are_preserved_and_rejected(string $file): void
    {
        $this->publish($this->package());
        $this->pullManually();
        file_put_contents($this->checkout.'/'.$file, 'local work');
        $this->assertRejected('For local frontend changes, run npm run build');
        $this->assertSame('local work', file_get_contents($this->checkout.'/'.$file));
    }

    public static function localChanges(): array
    {
        return [['Resources/assets/js/Pages/Dashboard.vue'], ['Resources/assets/js/Pages/Untracked.vue']];
    }

    public function test_unrelated_local_edits_do_not_block_asset_installation(): void
    {
        $this->publish($this->package());
        $this->pullManually();
        file_put_contents($this->checkout.'/backend.php', '<?php // local work');
        $this->git($this->checkout, ['add', 'backend.php']);
        file_put_contents($this->checkout.'/notes.txt', 'untracked notes');
        $status = $this->git($this->checkout, ['status', '--porcelain']);
        $this->updater->update('ContactCenter', fn () => null);
        $this->assertSame($status, $this->git($this->checkout, ['status', '--porcelain']));
        $this->assertSame('<?php // local work', file_get_contents($this->checkout.'/backend.php'));
        $this->assertSame('untracked notes', file_get_contents($this->checkout.'/notes.txt'));
        $this->assertSame($this->before, $this->git($this->checkout, ['rev-parse', 'HEAD']));
        $this->assertArrayHasKey('ContactCenter', $this->assets->state()['modules']);
    }

    public function test_matching_local_build_is_preserved_without_remote_access(): void
    {
        $package = $this->package();
        $meta = json_decode(file_get_contents($package.'/frontend.json'), true);
        file_put_contents($this->checkout.'/Resources/assets/js/Pages/Dashboard.vue', '<template>Local patch</template>');
        $meta['origin'] = 'local';
        $meta['sources'] = $this->assets->sourceFiles('ContactCenter');
        file_put_contents($package.'/frontend.json', json_encode($meta));
        $this->assets->publish([$package], true);
        $this->assets->activatePrepared();
        $before = $this->assets->state()['modules']['ContactCenter'];
        $this->git($this->checkout, ['remote', 'remove', 'origin']);
        $configured = false;
        $this->updater->update('ContactCenter', function () use (&$configured) { $configured = true; });
        $this->assertTrue($configured);
        $this->assertSame($before, $this->assets->state()['modules']['ContactCenter']);
        $this->assertSame('<template>Local patch</template>', file_get_contents($this->checkout.'/Resources/assets/js/Pages/Dashboard.vue'));
    }

    public function test_local_commits_are_not_reset_or_merged(): void
    {
        $this->advance();
        file_put_contents($this->checkout.'/local.txt', 'local commit');
        $this->git($this->checkout, ['add', '.']);
        $this->git($this->checkout, ['-c', 'user.name=Fixture', '-c', 'user.email=test@example.invalid', 'commit', '-m', 'Local']);
        $this->before = $this->git($this->checkout, ['rev-parse', 'HEAD']);
        $this->assertRejected('Matching frontend assets are missing or incompatible');
        $this->assertSame('local commit', file_get_contents($this->checkout.'/local.txt'));
    }

    public function test_detached_checkout_is_preserved(): void
    {
        $this->publish($this->package());
        $this->pullManually();
        $this->git($this->checkout, ['checkout', '--detach']);
        $this->updater->update('ContactCenter', fn () => null);
        $this->assertSame('HEAD', $this->git($this->checkout, ['rev-parse', '--abbrev-ref', 'HEAD']));
        $this->assertSame($this->before, $this->git($this->checkout, ['rev-parse', 'HEAD']));
        $this->assertArrayHasKey('ContactCenter', $this->assets->state()['modules']);
    }

    public function test_checkout_without_upstream_installs_bundled_assets(): void
    {
        $this->publish($this->package());
        $this->pullManually();
        $this->git($this->checkout, ['branch', '--unset-upstream']);
        $this->updater->update('ContactCenter', fn () => null);
        $this->assertArrayHasKey('ContactCenter', $this->assets->state()['modules']);
    }

    public function test_missing_bundle_reports_retry_guidance_without_a_remote(): void
    {
        $this->git($this->checkout, ['remote', 'remove', 'origin']);
        $this->assertRejected('pull the source including Resources/dist');
    }

    public function test_in_progress_git_operation_is_preserved(): void
    {
        file_put_contents($this->checkout.'/.git/MERGE_HEAD', $this->before);
        $this->assertRejected('Finish the in-progress Git operation');
        $this->assertFileExists($this->checkout.'/.git/MERGE_HEAD');
    }

    public function test_bundled_assets_install_when_the_remote_is_inaccessible(): void
    {
        $this->publish($this->package());
        $this->pullManually();
        $fetchHead = file_get_contents($this->checkout.'/.git/FETCH_HEAD');
        $this->git($this->checkout, ['remote', 'set-url', 'origin', $this->temporary.'/secret-token-missing.git']);
        $configured = false;
        $this->updater->update('ContactCenter', function () use (&$configured) { $configured = true; });
        $this->assertTrue($configured);
        $this->assertSame($fetchHead, file_get_contents($this->checkout.'/.git/FETCH_HEAD'));
        $this->assertSame($this->before, $this->git($this->checkout, ['rev-parse', 'HEAD']));
        $this->assertArrayHasKey('ContactCenter', $this->assets->state()['modules']);
    }

    /** @dataProvider gitFailureMessages */
    public function test_git_failures_report_the_cause_without_echoing_secrets(string $stderr, string $expected): void
    {
        $reason = GitModuleUpdater::failureReason($stderr);
        $this->assertStringContainsString($expected, $reason);
        $this->assertStringNotContainsString('private-secret', $reason);
        $this->assertStringNotContainsString('https://', $reason);
    }

    public static function gitFailureMessages(): array
    {
        return [
            ['fatal: detected dubious ownership in repository private-secret', 'repository ownership'],
            ['fatal: not a git repository private-secret', 'not a Git checkout'],
            ['fatal: Permission denied private-secret', 'filesystem permissions'],
            ['custom helper printed private-secret', 'check it with git status'],
        ];
    }

    /** @dataProvider invalidBundles */
    public function test_invalid_bundle_is_rejected_without_changing_the_checkout(string $failure): void
    {
        $this->advance();
        $this->pullManually();
        $package = $this->package();
        $meta = json_decode(file_get_contents($package.'/frontend.json'), true);
        if ($failure === 'runtime') $meta['runtime'] = str_repeat('b', 64);
        if ($failure === 'source') $meta['sources']['module.json'] = str_repeat('c', 64);
        if ($failure === 'checksum') file_put_contents($package.'/assets/app.js', 'corrupt');
        file_put_contents($package.'/frontend.json', json_encode($meta));
        // Model missing/corrupt/incompatible files already present in a checkout.
        $this->copyPackage($package);
        $this->assertRejected('Matching frontend assets are missing or incompatible');
    }

    public static function invalidBundles(): array
    {
        return [['runtime'], ['source'], ['checksum']];
    }

    public function test_matching_existing_bundle_is_reused_and_hooks_retry(): void
    {
        $this->advance();
        $this->publish($this->package());
        $this->pullManually();
        try {
            $this->updater->update('ContactCenter', fn () => throw new RuntimeException('Hook failed'));
            $this->fail('Expected hook failure');
        } catch (RuntimeException $error) { $this->assertSame('Hook failed', $error->getMessage()); }
        $this->assets->activatePrepared();
        $installed = $this->assets->state()['modules']['ContactCenter'];
        // A PHP-only commit can reuse an exact frontend without another build.
        $this->git($this->producer, ['pull', '--ff-only']);
        file_put_contents($this->producer.'/backend.php', '<?php // changed');
        $this->git($this->producer, ['add', '.']);
        $this->git($this->producer, ['commit', '-m', 'Backend only']);
        $this->git($this->producer, ['push', 'origin', 'main']);
        $this->pullManually();
        $this->git($this->checkout, ['remote', 'remove', 'origin']);
        $configured = false;
        $this->updater->update('ContactCenter', function () use (&$configured) { $configured = true; });
        $this->assertTrue($configured);
        $this->assertSame($installed, $this->assets->state()['modules']['ContactCenter']);
        $this->assertFileExists($this->checkout.'/backend.php');
    }

    public function test_linked_worktree_installs_bundled_assets_without_a_remote(): void
    {
        $clone = $this->temporary.'/clone';
        rename($this->checkout, $clone);
        $this->git($clone, ['worktree', 'add', '-b', 'production', $this->checkout, 'origin/main']);
        $this->advance();
        $target = $this->publish($this->package());
        $this->pullManually();
        $this->git($this->checkout, ['remote', 'remove', 'origin']);
        $this->updater->update('ContactCenter', fn () => null);
        $this->assertFileExists($this->checkout.'/.git');
        $this->assertSame($target, $this->git($this->checkout, ['rev-parse', 'HEAD']));
    }

    public function test_module_without_frontend_runs_hooks_without_a_remote(): void
    {
        $this->git($this->producer, ['rm', '-r', 'Resources']);
        $this->git($this->producer, ['commit', '-m', 'Backend module']);
        $this->git($this->producer, ['push', 'origin', 'main']);
        $this->pullManually();
        $this->git($this->checkout, ['remote', 'remove', 'origin']);
        $configured = false;
        $this->updater->update('ContactCenter', function () use (&$configured) { $configured = true; });
        $this->assertTrue($configured);
        $this->assertSame([], $this->assets->state()['modules']);
        $this->assertSame($this->git($this->producer, ['rev-parse', 'HEAD']), $this->git($this->checkout, ['rev-parse', 'HEAD']));
    }
}
