<?php

namespace Tests\Unit;

use App\Services\EmailTemplateSourceService;
use App\Services\SafeEmailTemplateRenderer;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class EmailTemplateSourceServiceTest extends TestCase
{
    private string $originalBasePath;
    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalBasePath = base_path();
        $this->fixturePath = sys_get_temp_dir().'/fspbx-email-sources-'.bin2hex(random_bytes(8));
        $this->app->setBasePath($this->fixturePath);
    }

    protected function tearDown(): void
    {
        $this->app->setBasePath($this->originalBasePath);
        File::deleteDirectory($this->fixturePath);
        parent::tearDown();
    }

    public function test_language_folders_are_discovered_independently_and_missing_languages_fall_back_to_english(): void
    {
        $this->writePair('en-us', 'en-us');
        $this->writePair('fr', 'fr');
        $sources = $this->sources();
        $this->assertCount(2, $sources->definitions());
        $this->assertSame('fr', $sources->find('system', 'test', 'FR')['template_language']);
        $this->assertSame('en-us', $sources->find('system', 'test', 'de')['template_language']);
    }

    public function test_multibyte_metadata_is_preserved_with_unix_windows_and_classic_line_endings(): void
    {
        $subject = "Данные для входа в приложение {{ config('app.name', 'FS PBX') }}";
        $description = 'Входящее сообщение, пересланное на электронную почту';
        foreach (["\n", "\r\n", "\r"] as $lineEnding) {
            $this->writePair('ru', 'ru');
            $path = resource_path('views/emails/ru/system/test.blade.php');
            $source = str_replace(
                ['subject: Test', 'description: Test'],
                ['subject: '.$subject, 'description: '.$description],
                File::get($path)
            );
            File::put($path, str_replace("\n", $lineEnding, $source));

            $definition = $this->sources()->find('system', 'test', 'ru');
            $this->assertSame($subject, $definition['template_subject']);
            $this->assertSame($description, $definition['template_description']);
        }
    }

    public function test_invalid_utf8_is_reported_with_the_source_path(): void
    {
        foreach (['test.blade.php', 'test-text.blade.php'] as $filename) {
            $this->writePair('ru', 'ru');
            $path = resource_path('views/emails/ru/system/'.$filename);
            File::append($path, "\xD1");
            try {
                $this->sources()->definitions();
                $this->fail('Invalid UTF-8 source was accepted.');
            } catch (RuntimeException $exception) {
                $this->assertSame('Email template source must be valid UTF-8: '.$path, $exception->getMessage());
            }
        }
    }

    public function test_a_language_folder_must_match_its_metadata(): void
    {
        $this->writePair('fr', 'ru');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Email template path must be ru/system/test.blade.php');
        $this->sources()->definitions();
    }

    public function test_english_uses_the_full_locale_code(): void
    {
        $this->writePair('en', 'en-us');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Email template path must be en-us/system/test.blade.php');
        $this->sources()->definitions();
    }

    public function test_unregistered_languages_are_rejected(): void
    {
        $this->writePair('xx', 'xx');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown email template language: xx');
        $this->sources()->definitions();
    }

    public function test_translations_require_a_plain_text_companion(): void
    {
        $this->writePair('fr', 'fr');
        File::delete(resource_path('views/emails/fr/system/test-text.blade.php'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing plain-text email template companion');
        $this->sources()->definitions();
    }

    public function test_standard_layout_must_belong_to_the_same_language(): void
    {
        $this->writePair('fr', 'fr', 'standard', 'emails.email_layout');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Standard email template must extend emails.fr.email_layout');
        $this->sources()->definitions();
    }

    public function test_standard_layout_file_must_exist(): void
    {
        $this->writePair('fr', 'fr', 'standard', 'emails.fr.email_layout');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing email layout for fr');
        $this->sources()->definitions();
    }

    private function sources(): EmailTemplateSourceService
    {
        return new EmailTemplateSourceService(app(SafeEmailTemplateRenderer::class));
    }

    private function writePair(string $directory, string $language, string $layout = 'none', ?string $view = null): void
    {
        $directory = resource_path("views/emails/{$directory}/system");
        File::ensureDirectoryExists($directory);
        $html = '<p>Test</p>';
        if ($view) {
            $html = "@extends('{$view}')\n@section('content')\n{$html}\n@endsection";
        }
        File::put($directory.'/test.blade.php', "{{-- email-template\nversion: 1.0.0\nlanguage: {$language}\ncategory: system\nsubcategory: test\nformat: html\nlayout: {$layout}\nsubject: Test\ndescription: Test\n--}}\n{$html}");
        File::put($directory.'/test-text.blade.php', "{{-- email-template\nformat: text\nlayout: none\n--}}\nTest");
    }
}
