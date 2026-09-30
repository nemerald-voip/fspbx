<?php

namespace Tests\Unit;

use App\Services\EmailTemplatePreviewService;
use App\Services\EmailTemplateSourceService;
use Illuminate\Support\Facades\File;
use Illuminate\View\Compilers\Compiler;
use ReflectionProperty;
use Tests\TestCase;

class EmailTemplatePreviewServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $compiledPath = '/tmp/fspbx-email-template-preview-tests';
        File::ensureDirectoryExists($compiledPath);
        config(['view.compiled' => $compiledPath]);

        $cachePath = new ReflectionProperty(Compiler::class, 'cachePath');
        $cachePath->setValue(app('blade.compiler'), $compiledPath);
    }

    public function test_voicemail_preview_renders_subject_html_and_text_with_sample_data(): void
    {
        $definition = app(EmailTemplateSourceService::class)
            ->definitions()['voicemail.default|en-us'];
        $preview = app(EmailTemplatePreviewService::class)->render($definition);

        $this->assertSame(
            'Voicemail from Jordan Lee <+1 202-555-0142> 00:42',
            $preview['subject']
        );
        $this->assertStringContainsString('Jordan Lee', $preview['html']);
        $this->assertStringContainsString('secure download link', $preview['html']);
        $this->assertStringNotContainsString('@extends', $preview['html']);
        $this->assertStringNotContainsString('{{', $preview['html']);
        $this->assertStringContainsString('Jordan Lee', $preview['text']);
        $this->assertStringContainsString(
            'https://example.test/voicemail/sample',
            $preview['text']
        );
        $this->assertStringNotContainsString('@if', $preview['text']);
        $this->assertStringNotContainsString('{{', $preview['text']);
    }

    public function test_password_reset_preview_includes_link_and_expiration_in_both_formats(): void
    {
        $definition = app(EmailTemplateSourceService::class)->definitions()['authentication.reset-password|en-us'];
        $preview = app(EmailTemplatePreviewService::class)->render($definition);

        foreach (['html', 'text'] as $format) {
            $this->assertStringContainsString('https://example.test/reset-password/sample-token?email=jordan%40example.test', $preview[$format]);
            $this->assertStringContainsString(config('auth.passwords.users.expire').' minutes', $preview[$format]);
            $this->assertStringContainsString('Jordan Lee', $preview[$format]);
        }
    }

    public function test_every_default_template_can_render_a_preview(): void
    {
        $previewer = app(EmailTemplatePreviewService::class);

        foreach (app(EmailTemplateSourceService::class)->definitions() as $key => $definition) {
            $preview = $previewer->render($definition);

            $this->assertNotSame('', $preview['subject'], $key);
            $this->assertNotSame('', $preview['html'], $key);
            $this->assertNotSame('', $preview['text'], $key);
        }
    }

    public function test_email_language_does_not_depend_on_the_interface_locale(): void
    {
        app()->setLocale('ru');
        $definition = app(EmailTemplateSourceService::class)->find('authentication', 'reset-password', 'fr');
        $preview = app(EmailTemplatePreviewService::class)->render($definition);
        $this->assertStringContainsString('Réinitialisation du mot de passe', $preview['subject']);
        $this->assertStringContainsString('Bonjour', $preview['html']);
        $this->assertStringContainsString('Bonjour', $preview['text']);
        $this->assertSame(1, substr_count($preview['html'], '<html'));
        $this->assertStringContainsString('lang="fr"', $preview['html']);
    }
}
