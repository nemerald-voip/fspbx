<?php

namespace Tests\Unit;

use App\Services\EmailTemplateSourceService;
use App\Services\SafeEmailTemplateRenderer;
use RuntimeException;
use Tests\TestCase;

class SafeEmailTemplateRendererTest extends TestCase
{
    public function test_all_notification_template_sources_are_safe_and_discoverable(): void
    {
        $definitions = app(EmailTemplateSourceService::class)->definitions();

        $this->assertCount(19, $definitions);
        $this->assertArrayHasKey('voicemail.default|en-us', $definitions);
        $this->assertArrayHasKey('voicemail.transcription|en-us', $definitions);

        foreach ($definitions as $definition) {
            $this->assertNotSame('', $definition['template_subject']);
            $this->assertNotSame('', $definition['template_html']);
            $this->assertNotSame('', $definition['template_text']);
            $this->assertStringEndsWith(
                $definition['template_category'].'/'.$definition['template_subcategory'].'.blade.php',
                $definition['source_path']
            );
            if ($definition['template_layout'] === 'standard') {
                $this->assertStringStartsWith(
                    "@extends('emails.email_layout')",
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
