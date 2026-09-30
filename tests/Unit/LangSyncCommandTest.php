<?php

namespace Tests\Unit;

use App\Console\Commands\LangSyncCommand;
use App\Support\Localization\LocaleFileLoader;
use App\Support\Localization\LocaleRegistry;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class LangSyncCommandTest extends TestCase
{
    private string $root;
    private Application $app;
    private Filesystem $files;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/lang-sync-test-'.bin2hex(random_bytes(8));
        $this->files = new Filesystem();
        $this->app = new Application($this->root);
        $this->app->useLangPath($this->root.'/resources/lang');
        $this->app->instance('files', $this->files);
        $this->app->instance('config', new Repository(['locales' => [
            'default' => 'en-us', 'locales' => ['en-us' => [], 'es-es' => []],
        ]]));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        $this->app->instance('translation.loader', new LocaleFileLoader(
            $this->files, $this->app->langPath(), new LocaleRegistry()
        ));
        $this->write('resources/lang/en-us.json', '{"Save":"Save","Old key":"Old key"}');
        $this->write('resources/lang/es-es.json', '{"Save":"Guardar","Old key":"Anterior"}');
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->root);
        Facade::clearResolvedInstances();
        parent::tearDown();
    }

    private function write(string $path, string $contents): void
    {
        $this->files->ensureDirectoryExists(dirname($this->root.'/'.$path));
        $this->files->put($this->root.'/'.$path, $contents);
    }

    private function sync(array $options = []): void
    {
        $command = new LangSyncCommand();
        $command->setLaravel($this->app);
        $this->assertSame(0, (new CommandTester($command))->execute($options));
    }

    private function catalog(string $locale = 'en-us'): array
    {
        return json_decode($this->files->get($this->root.'/resources/lang/'.$locale.'.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_collects_module_php_blade_vue_and_javascript_without_overwriting_translations(): void
    {
        $this->write('Modules/ContactCenter/Http/Controllers/Settings.php', "<?php __('Module PHP'); trans('Save');");
        $this->write('Modules/ContactCenter/Resources/views/index.blade.php', "@lang('Module Blade')");
        $this->write('Modules/ContactCenter/Resources/assets/js/Settings.vue', <<<'VUE'
<template>{{ $t('Module Vue') }} {{ $tChoice('One caller|:count callers', count) }}</template>
<script setup>trans('Module script');</script>
VUE);
        $this->write('Modules/Billing/Resources/assets/js/labels.mjs', "transChoice('One invoice|:count invoices', count);");
        $this->write('Modules/DisabledModule/Resources/assets/js/labels.ts', "trans('Installed module');");
        $this->sync();
        foreach (['Module PHP', 'Module Blade', 'Module Vue', 'Module script', 'One caller|:count callers',
            'One invoice|:count invoices', 'Installed module'] as $key) {
            $this->assertSame($key, $this->catalog()[$key]);
            $this->assertSame('', $this->catalog('es-es')[$key]);
        }
        $this->assertSame('Guardar', $this->catalog('es-es')['Save']);
        $this->assertSame('Anterior', $this->catalog('es-es')['Old key']);
        $this->assertSame(array_keys($this->catalog()), array_keys($this->catalog('es-es')));
    }

    public function test_excludes_dependencies_tests_email_templates_and_namespaced_catalog_keys(): void
    {
        foreach (['Modules/ContactCenter/Tests/Unit/Test.php', 'Modules/ContactCenter/tests/test.php',
            'Modules/ContactCenter/vendor/dependency/file.php', 'Modules/ContactCenter/node_modules/package/file.js',
            'Modules/ContactCenter/Resources/lang/en/messages.php', 'Modules/ContactCenter/Resources/assets/dist/bundle.js',
            'Modules/ContactCenter/Resources/views/emails/message.blade.php', 'resources/views/emails/message.blade.php'] as $path) {
            $this->write($path, "<?php trans('Excluded key');");
        }
        $this->write('Modules/ContactCenter/Resources/assets/js/Settings.vue', <<<'VUE'
<template>{{ $t('contactcenter::messages.title') }} {{ $t('pagination.next') }} {{ $t('Included key') }}</template>
VUE);
        $this->sync();
        $this->assertArrayHasKey('Included key', $this->catalog());
        foreach (['Excluded key', 'contactcenter::messages.title', 'pagination.next'] as $key) {
            $this->assertArrayNotHasKey($key, $this->catalog());
        }
    }

    public function test_prune_retains_keys_used_only_by_installed_modules(): void
    {
        $this->write('Modules/ContactCenter/Resources/assets/js/Settings.vue', "<template>{{ \$t('Old key') }}</template>");
        $this->sync(['--prune' => true]);
        $this->assertSame(['Old key' => 'Old key'], $this->catalog());
        $this->assertSame(['Old key' => 'Anterior'], $this->catalog('es-es'));
    }

    public function test_works_when_no_optional_modules_directory_exists(): void
    {
        $this->write('app/Http/Controllers/Settings.php', "<?php __('Core PHP');");
        $this->write('resources/js/labels.mjs', "trans('Core JavaScript');");
        $this->sync();
        $this->assertArrayHasKey('Core PHP', $this->catalog());
        $this->assertArrayHasKey('Core JavaScript', $this->catalog());
    }

    public function test_framework_source_messages_are_synced_and_survive_pruning_without_php_translations(): void
    {
        $this->write('resources/lang/en-us/auth.php', "<?php return ['failed' => 'Credentials rejected.'];");
        $this->write('resources/lang/en-us/passwords.php', "<?php return ['sent' => 'Reset link sent.'];");
        $this->write('resources/lang/en-us/validation.php', <<<'PHP'
<?php return [
    'min' => ['string' => 'At least :min characters.'],
    'password.letters' => 'Include a letter.',
    'attributes' => ['user_email' => 'email address'],
];
PHP);
        $this->write('resources/lang/en-us.json', '{"Credentials rejected.":"Credentials rejected."}');
        $this->write('resources/lang/es-es.json', '{"Credentials rejected.":"Credenciales rechazadas."}');
        $this->sync(['--prune' => true]);
        foreach (['Credentials rejected.', 'Reset link sent.', 'At least :min characters.', 'Include a letter.', 'email address'] as $key) {
            $this->assertSame($key, $this->catalog()[$key]);
            $this->assertArrayHasKey($key, $this->catalog('es-es'));
        }
        $this->assertSame('Credenciales rechazadas.', $this->catalog('es-es')['Credentials rejected.']);
        $this->assertArrayNotHasKey('auth.failed', $this->catalog());
        $this->assertDirectoryDoesNotExist($this->app->langPath('es-es'));
    }
}
