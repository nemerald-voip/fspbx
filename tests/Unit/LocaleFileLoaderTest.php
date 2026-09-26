<?php

namespace Tests\Unit;

use App\Support\Localization\LocaleFileLoader;
use App\Support\Localization\LocaleRegistry;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\Translator;
use PHPUnit\Framework\TestCase;

class LocaleFileLoaderTest extends TestCase
{
    private string $root;
    private Filesystem $files;
    private LocaleFileLoader $loader;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/locale-loader-test-'.bin2hex(random_bytes(8));
        $this->files = new Filesystem;
        $app = new Application($this->root);
        $app->instance('config', new Repository(['locales' => [
            'default' => 'en-us', 'locales' => ['en-us' => [], 'fr' => [], 'de' => []],
        ]]));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        $this->loader = new LocaleFileLoader($this->files, $this->root, new LocaleRegistry);
        $this->write('en-us/auth.php', "<?php return ['failed' => 'Credentials rejected.'];");
        $this->write('en-us/passwords.php', "<?php return ['sent' => 'Reset link sent.'];");
        $this->write('en-us/validation.php', <<<'PHP'
<?php return [
    'required' => 'The :attribute is required.',
    'min' => ['string' => 'The :attribute needs :min characters.'],
    'password.letters' => 'Include a letter.',
    'attributes' => ['user_email' => 'email address'],
];
PHP);
        $this->write('en-us.json', json_encode([
            'Credentials rejected.' => 'Credentials rejected.',
            'Reset link sent.' => 'Custom English confirmation.',
        ]));
        $this->write('fr.json', json_encode([
            'Credentials rejected.' => 'Identifiants refusés.',
            'Reset link sent.' => 'Lien envoyé.',
            'The :attribute is required.' => '',
            'The :attribute needs :min characters.' => 'Le champ :attribute nécessite :min caractères.',
            'Include a letter.' => 'Ajoutez une lettre.',
            'email address' => 'adresse e-mail',
            'Package message' => 'Must not affect packages',
            'Unrelated message' => 'Must not affect other groups',
        ]));
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->root);
        Facade::clearResolvedInstances();
        parent::tearDown();
    }

    public function test_framework_keys_resolve_json_without_locale_php_files(): void
    {
        $translator = new Translator($this->loader, 'fr');
        $translator->setFallback('en-us');
        $this->assertDirectoryDoesNotExist($this->root.'/fr');
        $this->assertSame('Identifiants refusés.', $translator->get('auth.failed'));
        $this->assertSame($translator->get('Credentials rejected.'), $translator->get('auth.failed'));
        $this->assertSame('Lien envoyé.', $translator->get('passwords.sent'));
        $this->assertSame('adresse e-mail', $translator->get('validation.attributes.user_email'));
        $this->assertSame('Ajoutez une lettre.', $translator->get('validation.password.letters'));
        $this->assertSame('Le champ mot de passe nécessite 10 caractères.', $translator->get('validation.min.string', [
            'attribute' => 'mot de passe', 'min' => 10,
        ]));
        $this->assertSame('The email is required.', $translator->get('validation.required', ['attribute' => 'email']));
        $this->assertSame('auth.unknown', $translator->get('auth.unknown'));
    }

    public function test_empty_and_missing_catalogs_fall_back_without_leaking_another_locale(): void
    {
        $translator = new Translator($this->loader, 'fr');
        $translator->setFallback('en-us');
        $this->assertSame('Identifiants refusés.', $translator->get('auth.failed'));
        $translator->setLocale('en-us');
        $this->assertSame('Credentials rejected.', $translator->get('auth.failed'));
        $this->assertSame('Custom English confirmation.', $translator->get('passwords.sent'));
        $translator->setLocale('de');
        $this->assertSame('Credentials rejected.', $translator->get('auth.failed'));
        $this->assertSame('Include a letter.', $translator->get('validation.password.letters'));
        $this->assertSame('Custom English confirmation.', $translator->get('passwords.sent'));
        $translator->setLocale('fr');
        $this->assertSame('Identifiants refusés.', $translator->get('auth.failed'));
    }

    public function test_explicit_php_overrides_other_groups_and_packages_keep_laravel_behavior(): void
    {
        $this->write('fr/auth.php', "<?php return ['failed' => 'Existing override'];");
        $this->write('fr/validation.php', "<?php return ['password' => ['letters' => 'Existing nested override']];");
        $this->write('fr/messages.php', "<?php return ['hello' => 'Unrelated message'];");
        $this->write('package/fr/validation.php', "<?php return ['required' => 'Package message'];");
        $this->loader->addNamespace('example', $this->root.'/package');
        $translator = new Translator($this->loader, 'fr');
        $this->assertSame('Existing override', $translator->get('auth.failed'));
        $this->assertSame('Existing nested override', $translator->get('validation.password.letters'));
        $this->assertSame('Unrelated message', $translator->get('messages.hello'));
        $this->assertSame('Package message', $translator->get('example::validation.required'));
    }

    private function write(string $path, string $contents): void
    {
        $this->files->ensureDirectoryExists(dirname($this->root.'/'.$path));
        $this->files->put($this->root.'/'.$path, $contents);
    }
}
