<?php

namespace Tests\Unit;

use App\Services\EmailTemplateSourceService;
use App\Services\SafeEmailTemplateRenderer;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\View\Compilers\Compiler;
use Illuminate\View\ViewException;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

class SafeEmailTemplateRendererTest extends TestCase
{
    private string $compiledPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->compiledPath = sys_get_temp_dir().'/fspbx-safe-email-template-tests-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->compiledPath);
        config(['view.compiled' => $this->compiledPath]);

        $cachePath = new ReflectionProperty(Compiler::class, 'cachePath');
        $cachePath->setValue(app('blade.compiler'), $this->compiledPath);
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->compiledPath)) {
                File::deleteDirectory($this->compiledPath);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_generated_template_source_is_retained_between_renders(): void
    {
        $renderer = app(SafeEmailTemplateRenderer::class);
        $template = 'Voicemail from {{ $name }}';

        $this->assertSame('Voicemail from First', $renderer->renderSubject($template, ['name' => 'First']));
        $this->assertCount(1, File::glob($this->compiledPath.'/*.blade.php'));
        $this->assertSame('Voicemail from Second', $renderer->renderSubject($template, ['name' => 'Second']));
    }

    /** @dataProvider cachedViewRenderers */
    public function test_rendering_recovers_after_an_external_view_cache_clear(string $method, string $template): void
    {
        $renderer = app(SafeEmailTemplateRenderer::class);
        $this->assertStringContainsString('Hello First', $renderer->{$method}($template, ['name' => 'First']));

        // A separate view:clear process deletes files without resetting this worker's memory.
        File::cleanDirectory($this->compiledPath);
        clearstatcache();

        $rendered = $renderer->{$method}($template, ['name' => 'Second']);
        $this->assertStringContainsString('Hello Second', $rendered);
        $this->assertStringNotContainsString('Hello First', $rendered);
        $this->assertStringContainsString('Hello Third', $renderer->{$method}($template, ['name' => 'Third']));
    }

    public static function cachedViewRenderers(): array
    {
        return [
            'subject' => ['renderSubject', 'Hello {{ $name }}'],
            'text' => ['renderText', 'Hello {{ $name }}'],
            'html with layout' => ['renderHtml', '<p>Hello {{ $name }}</p>'],
        ];
    }

    public function test_rendering_recovers_when_the_view_finder_also_forgets_the_deleted_source(): void
    {
        $renderer = app(SafeEmailTemplateRenderer::class);
        $template = 'Voicemail from {{ $name }}';
        $this->assertSame('Voicemail from First', $renderer->renderSubject($template, ['name' => 'First']));

        File::cleanDirectory($this->compiledPath);
        app('view')->getFinder()->flush();
        clearstatcache();

        $this->assertSame('Voicemail from Second', $renderer->renderSubject($template, ['name' => 'Second']));
    }

    /** @dataProvider unrelatedRenderingFailures */
    public function test_unrelated_rendering_failures_are_not_retried(string $exceptionClass, string $message): void
    {
        $attempts = 0;
        $failure = new $exceptionClass($message);
        $callback = function () use (&$attempts, $failure) {
            $attempts++;
            throw $failure;
        };

        try {
            app(SafeEmailTemplateRenderer::class)->renderSubject('{{ $callback() }}', ['callback' => $callback]);
            $this->fail('Expected the original rendering failure.');
        } catch (ViewException $exception) {
            $this->assertSame($failure, $exception->getPrevious());
            $this->assertSame(1, $attempts);
        }
    }

    public static function unrelatedRenderingFailures(): array
    {
        return [
            'template error' => [RuntimeException::class, 'Invalid template data.'],
            'unrelated missing file' => [FileNotFoundException::class, 'File does not exist at path /tmp/missing-voicemail.wav.'],
            'permission error' => [\ErrorException::class, 'file_put_contents(): Failed to open stream: Permission denied'],
        ];
    }

    public function test_missing_generated_views_are_only_retried_once(): void
    {
        $attempts = 0;
        app('view')->creator('*', function ($view) use (&$attempts) {
            $attempts++;
            File::delete($view->getPath());
        });

        try {
            app(SafeEmailTemplateRenderer::class)->renderSubject('Hello {{ $name }}', ['name' => 'First']);
            $this->fail('Expected a persistent missing source to fail.');
        } catch (FileNotFoundException $exception) {
            $this->assertStringContainsString($this->compiledPath, $exception->getMessage());
            $this->assertSame(2, $attempts);
        }
    }

    public function test_all_notification_template_sources_are_safe_and_discoverable(): void
    {
        $definitions = app(EmailTemplateSourceService::class)->definitions();

        $this->assertCount(115, $definitions);
        foreach (['en-us', 'ru', 'fr', 'es-419', 'pt-br'] as $language) {
            $this->assertCount(23, array_filter($definitions, fn ($definition) => $definition['template_language'] === $language));
        }
        $this->assertArrayHasKey('ai-agent.send-email|en-us', $definitions);
        $this->assertArrayHasKey('extension.welcome|en-us', $definitions);
        $this->assertArrayHasKey('voicemail.default|en-us', $definitions);
        $this->assertArrayHasKey('voicemail.transcription|en-us', $definitions);
        $this->assertArrayHasKey('authentication.reset-password|en-us', $definitions);

        foreach ($definitions as $definition) {
            foreach (['template_subject', 'template_description', 'template_html', 'template_text'] as $field) {
                $this->assertTrue(mb_check_encoding($definition[$field], 'UTF-8'), $definition['source_path'].' '.$field);
            }
            $this->assertNotSame('', $definition['template_subject']);
            $this->assertNotSame('', $definition['template_html']);
            $this->assertNotSame('', $definition['template_text']);
            $this->assertStringEndsWith(
                $definition['template_language'].'/'.$definition['template_category'].'/'.$definition['template_subcategory'].'.blade.php',
                $definition['source_path']
            );
            if ($definition['template_layout'] === 'standard') {
                $this->assertStringStartsWith(
                    $definition['template_language'] === 'en-us'
                        ? "@extends('emails.email_layout')"
                        : "@extends('emails.{$definition['template_language']}.email_layout')",
                    ltrim($definition['template_html'])
                );
            }
        }
    }

    public function test_standard_templates_use_the_trusted_layout_and_escape_dynamic_content(): void
    {
        $renderer = app(SafeEmailTemplateRenderer::class);
        $html = $renderer->renderHtml(
            '<p>Hello ${name}</p><p>${untrusted}</p>',
            [
                'name' => 'Ada & Lin',
                'untrusted' => '{{ dangerous() }} <script>alert(1)</script>',
                'app_name' => 'FS PBX',
                'product_url' => 'https://example.test',
                'company_name' => 'Example',
                'company_address' => '123 Main Street',
                'support_email' => 'support@example.test',
            ],
            'standard'
        );

        $this->assertStringContainsString('Hello Ada &amp; Lin', $html);
        $this->assertStringContainsString('{{ dangerous() }} &lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString("@yield('content')", $html);
    }

    public function test_language_layouts_keep_their_own_footer_without_a_second_english_wrapper(): void
    {
        $footers = [
            'ru' => 'Отписаться от рассылки',
            'fr' => 'Se désabonner de cette liste',
            'es-419' => 'Cancelar la suscripción a esta lista',
            'pt-br' => 'Cancelar inscrição nesta lista',
        ];
        foreach ($footers as $language => $footer) {
            $html = app(SafeEmailTemplateRenderer::class)->renderHtml(
                "@extends('emails.{$language}.email_layout')\n@section('content')<p>Example</p>@endsection",
                ['unsubscribe_email' => 'support@example.test'],
                'standard'
            );
            $this->assertStringContainsString($footer, $html);
            $this->assertStringNotContainsString('Unsubscribe from this list', $html);
            $this->assertSame(1, substr_count($html, '<html'));
        }
    }

    public function test_php_blocks_and_scripts_are_rejected_in_editable_content(): void
    {
        $renderer = app(SafeEmailTemplateRenderer::class);

        foreach ([
            '@php echo(1); @endphp',
            '@endphp',
            '<?php echo 1; ?>',
            '<script>alert(1)</script>',
            '<a href="javascript:alert(1)">Open</a>',
            '<img src="x" onerror="alert(1)">',
        ] as $content) {
            try {
                $renderer->assertSafe($content);
                $this->fail("Unsafe content was accepted: {$content}");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('@php, raw PHP tags, and scripts are not allowed', $exception->getMessage());
            }
        }
    }

    public function test_normal_blade_conditionals_and_loops_are_supported(): void
    {
        $renderer = app(SafeEmailTemplateRenderer::class);

        $rendered = $renderer->renderHtml(
            '@if($show)<ul>@foreach($items as $item)<li>{{ $item }}</li>@endforeach</ul>@endif',
            ['show' => true, 'items' => ['One', '<Two>']],
            'none'
        );

        $this->assertSame('<ul><li>One</li><li>&lt;Two&gt;</li></ul>', $rendered);
    }

    public function test_plain_text_bodies_are_not_html_escaped(): void
    {
        $renderer = app(SafeEmailTemplateRenderer::class);

        $rendered = $renderer->renderText(
            "Preview: {{ \$message_text }}\nFrom: {{ \$caller_id_name }}",
            ['message_text' => "Let's see if A & B \"work\"", 'caller_id_name' => "O'Brien <101>"]
        );

        $this->assertSame("Preview: Let's see if A & B \"work\"\nFrom: O'Brien <101>", $rendered);
    }

    public function test_inbound_message_template_keeps_editable_image_and_attachment_loops(): void
    {
        $definition = app(EmailTemplateSourceService::class)
            ->definitions()['messages.inbound|en-us'];

        $html = app(SafeEmailTemplateRenderer::class)->renderHtml(
            $definition['template_html'],
            [
                'source' => '12025550100',
                'destination' => '12025550200',
                'message' => '<Hello>',
                'inline_images' => [[
                    'cid' => 'fspbx-image@inline',
                    'name' => 'photo.jpg',
                ]],
                'media' => [[
                    'original_name' => 'document.pdf',
                    'mime_type' => 'application/pdf',
                ]],
                'app_name' => 'FS PBX',
            ],
            $definition['template_layout']
        );

        $this->assertStringContainsString('&lt;Hello&gt;', $html);
        $this->assertStringContainsString('src="cid:fspbx-image@inline"', $html);
        $this->assertStringContainsString('document.pdf', $html);
        $this->assertStringContainsString('(application/pdf)', $html);
        $this->assertStringNotContainsString('<p>', $definition['template_text']);
        $this->assertStringNotContainsString("@extends('emails.email_layout')", $definition['template_text']);
    }

    public function test_transcription_template_uses_mailer_prepared_values_without_php_blocks(): void
    {
        $definition = app(EmailTemplateSourceService::class)
            ->definitions()['transcription.call-ready|en-us'];

        $html = app(SafeEmailTemplateRenderer::class)->renderHtml(
            $definition['template_html'],
            [
                'date' => 'July 23, 2026',
                'duration' => '00:42',
                'sentiment' => 'Positive',
                'sentiment_badge_class' => 'badge-positive',
                'summary' => 'A short call.',
                'action_items' => [],
                'template_utterances' => [[
                    'speaker_name' => 'Agent',
                    'row_class' => 'is-agent',
                    'time' => '00:03',
                    'text' => 'Hello.',
                ]],
            ],
            $definition['template_layout']
        );

        $this->assertStringContainsString('badge-positive', $html);
        $this->assertStringContainsString('Agent', $html);
        $this->assertStringContainsString('00:03', $html);
    }
}
