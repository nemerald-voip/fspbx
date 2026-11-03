<?php

namespace Tests\Unit;

use App\Services\FrontendAssets;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FrontendAssetsTest extends TestCase
{
    private string $root;
    private FrontendAssets $assets;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/fspbx-assets-test-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/resources/js', 0755, true);
        file_put_contents($this->root.'/resources/js/vue.js', 'export const source = 1;');
        $this->assets = new FrontendAssets($this->root);
    }

    protected function tearDown(): void
    {
        $this->assets->removeDirectory($this->root);
    }

    private function package(string $kind = 'core', string $runtime = 'a', string $origin = 'release', string $name = 'ContactCenter'): string
    {
        $directory = $this->root.'/package-'.bin2hex(random_bytes(8));
        mkdir($directory.'/assets', 0755, true);
        file_put_contents($directory.'/assets/app.js', 'export const build = "'.basename($directory).'";');
        file_put_contents($directory.'/assets/app.css', 'body { color: black; }');
        file_put_contents($directory.'/assets/runtime.js', 'export const runtime = true;');
        file_put_contents($directory.'/manifest.json', json_encode([
            'resources/js/vue.js' => ['file' => 'assets/app.js'],
            'resources/scss/tailwind.css' => ['file' => 'assets/app.css'],
        ]));
        $files = [];
        foreach (['assets/app.js', 'assets/app.css', 'assets/runtime.js', 'manifest.json'] as $file) {
            $files[$file] = hash_file('sha256', $directory.'/'.$file);
        }
        $this->metadata($directory, [
            'schema' => 1, 'api' => 1, 'kind' => $kind, 'name' => $kind === 'core' ? 'fspbx' : $name,
            'version' => '1.2.3', 'core_version' => '1.2.3', 'runtime' => str_repeat($runtime, 64),
            'origin' => $origin, 'sources' => $kind === 'core' ? $this->assets->sourceFiles() : [], 'files' => $files,
            'css' => ['assets/app.css'], 'entry' => 'assets/app.js',
            'imports' => ['@fspbx/runtime/vue' => 'assets/runtime.js'],
        ]);
        return $directory;
    }

    private function metadata(string $directory, array $metadata): void
    {
        file_put_contents($directory.'/frontend.json', json_encode($metadata));
    }

    private function installModuleSource(string $name = 'ContactCenter', string $version = '1.2.3'): void
    {
        mkdir($this->root.'/Modules/'.$name, 0755, true);
        file_put_contents($this->root.'/Modules/'.$name.'/module.json', json_encode(['name' => $name, 'version' => $version]));
    }

    public function test_release_preparation_does_not_activate_or_replace_local_build(): void
    {
        $this->assets->publish([$this->package(origin: 'local')]);
        $before = $this->assets->state();
        $release = $this->package();
        $this->assets->prepare($release, '1.2.3');
        $this->assertSame($before, $this->assets->state());
        $this->assets->activatePrepared();
        $this->assertSame('release', $this->assets->state()['core']['metadata']['origin']);
        $this->assertSame($before, json_decode(file_get_contents($this->root.'/storage/app/frontend/previous.json'), true));
    }

    public function test_failed_module_build_or_checksum_cannot_activate_partial_local_build(): void
    {
        $this->assets->publish([$this->package()]);
        $before = $this->assets->state();
        $module = $this->package('module');
        file_put_contents($module.'/assets/app.js', 'corrupted');
        try {
            $this->assets->publish([$this->package(origin: 'local'), $module]);
            $this->fail('Expected checksum failure');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('checksum', $error->getMessage());
        }
        $this->assertSame($before, $this->assets->state());
    }

    public function test_only_enabled_installed_compatible_module_versions_are_exposed(): void
    {
        $this->installModuleSource();
        $this->installModuleSource('Billing');
        $this->assets->publish([$this->package(), $this->package('module'), $this->package('module', name: 'Billing')]);
        $browser = $this->assets->browserConfiguration(['ContactCenter']);
        $this->assertSame(['ContactCenter'], array_keys($browser['modules']));
        $this->assertCount(1, $browser['css']);
        $this->assertStringStartsWith('/storage/frontend/core/', $browser['imports']['@fspbx/runtime/vue']);
        file_put_contents($this->root.'/Modules/ContactCenter/module.json', '{"version":"1.2.4"}');
        $this->assertSame([], $this->assets->browserConfiguration(['ContactCenter'])['modules']);
    }

    public function test_unlicensed_module_refresh_is_not_required_for_compatible_core_update(): void
    {
        $this->installModuleSource();
        $this->assets->publish([$this->package(), $this->package('module')]);
        $module = $this->assets->state()['modules']['ContactCenter'];
        $this->assets->prepare($this->package(), '1.2.3');
        $this->assets->activatePrepared();
        $this->assertSame($module, $this->assets->state()['modules']['ContactCenter']);
        $this->assertArrayHasKey('ContactCenter', $this->assets->browserConfiguration(['ContactCenter'])['modules']);
    }

    public function test_module_can_target_staged_runtime_without_becoming_visible_early(): void
    {
        $this->installModuleSource();
        $this->assets->publish([$this->package()]);
        $this->assets->prepare($this->package(runtime: 'b'), '1.2.3');
        $module = $this->package('module', 'b');
        $this->assets->validateModule($module, 'ContactCenter', '1.2.3');
        $this->assets->publish([$module]);
        $this->assertSame([], $this->assets->browserConfiguration(['ContactCenter'])['modules']);
        $this->assets->activatePrepared();
        $this->assertArrayHasKey('ContactCenter', $this->assets->browserConfiguration(['ContactCenter'])['modules']);
    }

    public function test_first_release_can_stage_modules_before_activating_the_new_core(): void
    {
        $this->installModuleSource();
        $this->assets->prepare($this->package(), '1.2.3');
        $module = $this->package('module');
        $this->assets->validateModule($module, 'ContactCenter', '1.2.3');
        $this->assets->publish([$module]);
        $this->assertNull($this->assets->state()['core']);
        $this->assertSame('storage/vite', $this->assets->buildDirectory());
        $this->assertSame([], $this->assets->browserConfiguration(['ContactCenter'])['modules']);
        $this->assets->activatePrepared();
        $this->assertArrayHasKey('ContactCenter', $this->assets->browserConfiguration(['ContactCenter'])['modules']);
    }

    public function test_incompatible_module_is_rejected_without_changing_active_assets(): void
    {
        $this->assets->publish([$this->package()]);
        $before = $this->assets->state();
        try {
            $this->assets->publish([$this->package('module', 'b')]);
            $this->fail('Expected compatibility failure');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('incompatible', $error->getMessage());
        }
        $this->assertSame($before, $this->assets->state());
    }

    public function test_same_version_with_different_source_is_rejected(): void
    {
        $package = $this->package();
        file_put_contents($this->root.'/resources/js/new-page.vue', '<template>New source</template>');
        $this->expectExceptionMessage('do not match this checkout');
        $this->assets->prepare($package, '1.2.3');
    }

    public function test_module_archive_source_must_match_its_compiled_assets(): void
    {
        $this->installModuleSource();
        $directory = $this->root.'/Modules/ContactCenter';
        mkdir($directory.'/Resources/assets/js/Pages', 0755, true);
        file_put_contents($directory.'/Resources/assets/js/Pages/Dashboard.vue', '<template>First</template>');
        $metadata = ['name' => 'ContactCenter', 'sources' => $this->assets->sourceFiles('ContactCenter')];
        $this->assets->verifyModuleSource($directory, $metadata);
        file_put_contents($directory.'/Resources/assets/js/Pages/Dashboard.vue', '<template>Changed</template>');
        $this->expectExceptionMessage('do not match the packaged module source');
        $this->assets->verifyModuleSource($directory, $metadata);
    }

    public function test_source_change_after_preparation_prevents_activation(): void
    {
        $this->assets->prepare($this->package(), '1.2.3');
        file_put_contents($this->root.'/resources/js/vue.js', 'changed');
        $this->expectExceptionMessage('No verified frontend release');
        $this->assets->activatePrepared();
    }

    public function test_manual_build_can_replace_release_without_a_mode_switch(): void
    {
        $this->assets->publish([$this->package()]);
        file_put_contents($this->root.'/resources/js/vue.js', 'patched');
        $this->assets->publish([$this->package(origin: 'local')], true);
        $this->assertSame('local', $this->assets->state()['core']['metadata']['origin']);
    }

    public function test_unpublished_checkout_can_reuse_only_an_exact_existing_local_build(): void
    {
        $this->assertFalse($this->assets->prepareExistingLocal());
        $this->assets->publish([$this->package()]);
        $this->assertFalse($this->assets->prepareExistingLocal());
        $this->assets->publish([$this->package(origin: 'local')]);
        $this->assertTrue($this->assets->prepareExistingLocal());
        $this->assertSame('local', $this->assets->prepared()['metadata']['origin']);
        file_put_contents($this->root.'/resources/js/vue.js', 'new unbuilt source');
        $this->expectExceptionMessage('do not match this checkout');
        $this->assets->prepareExistingLocal();
    }

    public function test_local_hotfix_can_be_rolled_back_without_rebuilding(): void
    {
        $this->assets->publish([$this->package()]);
        $release = $this->assets->state();
        $this->assets->publish([$this->package(origin: 'local')]);
        $this->assets->rollback('1.2.3');
        $this->assertSame($release, $this->assets->state());
    }

    public function test_rollback_rejects_a_different_backend_version(): void
    {
        $this->assets->publish([$this->package()]);
        $this->assets->publish([$this->package(origin: 'local')]);
        $this->expectExceptionMessage('another application version');
        $this->assets->rollback('1.2.4');
    }

    public function test_release_for_wrong_version_is_rejected(): void
    {
        $this->expectExceptionMessage('does not match the requested release');
        $this->assets->prepare($this->package(), '9.9.9');
    }

    public function test_unlisted_file_is_rejected(): void
    {
        $package = $this->package();
        file_put_contents($package.'/unexpected.php', '<?php');
        $this->expectExceptionMessage('unexpected files');
        $this->assets->validate($package);
    }

    public function test_symlink_is_rejected(): void
    {
        $package = $this->package();
        symlink($this->root.'/resources/js/vue.js', $package.'/link.js');
        $this->expectExceptionMessage('regular files');
        $this->assets->validate($package);
    }

    public function test_archive_roundtrip_is_validated_before_publication(): void
    {
        $package = $this->package();
        $tar = new \PharData($this->root.'/assets.tar');
        $tar->buildFromDirectory($package);
        $tar->compress(\Phar::GZ);
        $this->assets->extract($this->root.'/assets.tar.gz', $this->root.'/extracted');
        $this->assets->prepare($this->root.'/extracted', '1.2.3');
        $this->assertNull($this->assets->state()['core']);
        $this->assets->activatePrepared();
        $this->assertSame('release', $this->assets->state()['core']['metadata']['origin']);
    }
}
