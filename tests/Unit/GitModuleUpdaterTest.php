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

    public function test_manual_pull_is_followed_by_exact_asset_installation(): void
    {
        $target = $this->advance();
        $package = $this->package();
        $this->publish($package);
        $this->assertStringContainsString('already published', $this->publish($package));
        $this->pullManually();
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

    public function test_refresh_downloads_current_commit_assets_without_pulling_newer_source(): void
    {
        $this->publish($this->package());
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
        $this->assertRejected('Could not download frontend assets for checked-out commit');
    }

    public function test_missing_new_bundle_preserves_an_existing_active_module(): void
    {
        $this->publish($this->package());
        $this->updater->update('ContactCenter', fn () => null);
        $this->assets->activatePrepared();
        $before = $this->assets->state();
        $this->advance();
        $this->pullManually();
        try {
            $this->updater->update('ContactCenter', fn () => $this->fail('Hooks must not run'));
            $this->fail('Expected missing package rejection');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Could not download frontend assets for checked-out commit', $error->getMessage());
        }
        $this->assertSame($before, $this->assets->state());
        $this->assertSame($this->before, $this->git($this->checkout, ['rev-parse', 'HEAD']));
    }

    public function test_publisher_preserves_source_index_and_rejects_replacing_a_package(): void
    {
        $this->advance();
        $this->pullManually();
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
    public function test_unbuilt_frontend_changes_are_preserved_and_rejected(string $file): void
    {
        $this->publish($this->package());
        file_put_contents($this->checkout.'/'.$file, 'local work');
        $this->assertRejected('do not match this checkout');
        $this->assertSame('local work', file_get_contents($this->checkout.'/'.$file));
    }

    public static function localChanges(): array
    {
        return [['Resources/assets/js/Pages/Dashboard.vue'], ['Resources/assets/js/Pages/Untracked.vue']];
    }

    public function test_unrelated_local_edits_do_not_block_asset_installation(): void
    {
        $this->publish($this->package());
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
        $this->assertRejected('Could not download frontend assets for checked-out commit');
        $this->assertSame('local commit', file_get_contents($this->checkout.'/local.txt'));
    }

    public function test_detached_checkout_is_preserved(): void
    {
        $this->publish($this->package());
        $this->git($this->checkout, ['checkout', '--detach']);
        $this->updater->update('ContactCenter', fn () => null);
        $this->assertSame('HEAD', $this->git($this->checkout, ['rev-parse', '--abbrev-ref', 'HEAD']));
        $this->assertSame($this->before, $this->git($this->checkout, ['rev-parse', 'HEAD']));
        $this->assertArrayHasKey('ContactCenter', $this->assets->state()['modules']);
    }

    public function test_checkout_without_upstream_downloads_assets_from_origin(): void
    {
        $this->publish($this->package());
        $this->git($this->checkout, ['branch', '--unset-upstream']);
        $this->updater->update('ContactCenter', fn () => null);
        $this->assertArrayHasKey('ContactCenter', $this->assets->state()['modules']);
    }

    public function test_missing_remote_is_reported_only_when_assets_need_downloading(): void
    {
        $this->git($this->checkout, ['remote', 'remove', 'origin']);
        $this->assertRejected('Configure origin or a tracking remote');
    }

    public function test_in_progress_git_operation_is_preserved(): void
    {
        file_put_contents($this->checkout.'/.git/MERGE_HEAD', $this->before);
        $this->assertRejected('Finish the in-progress Git operation');
        $this->assertFileExists($this->checkout.'/.git/MERGE_HEAD');
    }

    public function test_private_remote_access_failure_does_not_expose_remote_credentials(): void
    {
        $this->git($this->checkout, ['remote', 'set-url', 'origin', $this->temporary.'/secret-token-missing.git']);
        $this->assertRejected('Git fetch failed');
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
            ["connect ECONNREFUSED /run/user/0/vscode-git-private-secret.sock\nfatal: Authentication failed", 'VS Code Git credential bridge'],
            ['fatal: could not read Username for https://private-secret@github.com: terminal prompts disabled', 'HTTPS authentication'],
            ['fatal: Authentication failed for https://user:private-secret@github.com/repo', 'HTTPS authentication'],
            ['git@github.com: Permission denied (publickey). private-secret', 'SSH key authentication'],
            ['Host key verification failed. private-secret', 'host-key verification'],
            ["fatal: couldn't find remote ref refs/heads/private-secret", 'reference does not exist'],
            ['fatal: detected dubious ownership in repository private-secret', 'repository ownership'],
            ["fatal: cannot open '.git/FETCH_HEAD': Permission denied private-secret", 'filesystem permissions'],
            ['fatal: Could not resolve host: private-secret', 'hostname could not be resolved'],
            ['fatal: SSL certificate problem: private-secret', 'TLS certificate verification'],
            ['custom helper printed private-secret', 'unrecognized error'],
        ];
    }

    /** @dataProvider editorCredentialFailures */
    public function test_only_dead_editor_credentials_are_retried_without_overriding_other_authentication(string $firstError, string $secondError, int $attempts): void
    {
        $updater = new class($this->assets, $this->root) extends GitModuleUpdater {
            public array $environments = [];
            public array $errors = [];
            protected function runGitProcess(string $directory, array $arguments, array $environment): Process
            {
                $this->environments[] = $environment;
                $error = array_shift($this->errors) ?? '';
                $process = new Process([PHP_BINARY, '-r', $error === '' ? 'echo "ok";' : 'fwrite(STDERR, $argv[1]); exit(128);', $error]);
                $process->run();
                return $process;
            }
        };
        $updater->errors = [$firstError, $secondError];
        try {
            $result = (new \ReflectionMethod(GitModuleUpdater::class, 'git'))->invoke($updater, $this->checkout, ['fetch']);
            $this->assertSame('', $attempts === 1 ? $firstError : $secondError);
            $this->assertSame('ok', $result);
        } catch (RuntimeException $error) {
            $this->assertNotSame('', $attempts === 1 ? $firstError : $secondError);
            $this->assertStringNotContainsString('private-secret', $error->getMessage());
        }
        $this->assertCount($attempts, $updater->environments);
        $this->assertSame(['GIT_TERMINAL_PROMPT' => '0'], $updater->environments[0]);
        if ($attempts === 2) {
            $this->assertFalse($updater->environments[1]['GIT_ASKPASS']);
            $this->assertFalse($updater->environments[1]['VSCODE_GIT_IPC_HANDLE']);
            foreach (['SSH_AUTH_SOCK', 'GIT_SSH_COMMAND', 'SSH_ASKPASS', 'HOME', 'GIT_CONFIG_GLOBAL'] as $name) {
                $this->assertArrayNotHasKey($name, $updater->environments[1]);
            }
        }
    }

    public static function editorCredentialFailures(): array
    {
        $stale = 'connect ECONNREFUSED /run/user/0/vscode-git-private-secret.sock';
        return [
            'normal configured credentials succeed' => [$stale, '', 2],
            'no replacement credentials' => [$stale, 'fatal: Authentication failed for https://private-secret@github.com', 2],
            'editor stays broken' => [$stale, $stale, 2],
            'working authentication is preserved' => ['', '', 1],
            'other authentication failure is not retried' => ['fatal: Authentication failed', '', 1],
        ];
    }

    /** @dataProvider invalidBundles */
    public function test_invalid_bundle_is_rejected_without_changing_the_checkout(string $failure, string $message): void
    {
        $target = $this->advance();
        $this->pullManually();
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
        $this->pullManually();
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
        $this->pullManually();
        $this->git($this->checkout, ['remote', 'remove', 'origin']);
        $configured = false;
        $this->updater->update('ContactCenter', function () use (&$configured) { $configured = true; });
        $this->assertTrue($configured);
        $this->assertSame($installed, $this->assets->state()['modules']['ContactCenter']);
        $this->assertFileExists($this->checkout.'/backend.php');
    }

    public function test_linked_worktree_uses_its_tracking_remote_for_assets_only(): void
    {
        $clone = $this->temporary.'/clone';
        rename($this->checkout, $clone);
        $this->git($clone, ['worktree', 'add', '-b', 'production', $this->checkout, 'origin/main']);
        $target = $this->advance();
        $this->publish($this->package());
        $this->pullManually();
        $this->git($this->checkout, ['remote', 'rename', 'origin', 'module-upstream']);
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
