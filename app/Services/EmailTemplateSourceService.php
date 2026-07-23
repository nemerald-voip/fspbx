<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class EmailTemplateSourceService
{
    private ?array $definitions = null;

    public function __construct(private readonly SafeEmailTemplateRenderer $renderer)
    {
    }

    public function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $definitions = [];
        $basePath = resource_path('views/emails');

        foreach (File::allFiles($basePath) as $file) {
            $path = $file->getPathname();
            $source = File::get($path);
            if (! $this->hasFrontMatter($source)) {
                continue;
            }

            $metadata = $this->parseFrontMatter($source);
            $format = Str::lower((string) ($metadata['format'] ?? ''));
            if ($format === 'text') {
                continue;
            }
            if ($format !== 'html') {
                throw new RuntimeException("Unsupported or missing format metadata in {$path}");
            }

            $definition = $this->parse($path, $source, $metadata);
            $key = $definition['template_key'].'|'.$definition['template_language'];
            if (isset($definitions[$key])) {
                throw new RuntimeException("Duplicate default email template source: {$key}");
            }

            $definitions[$key] = $definition;
        }

        return $this->definitions = $definitions;
    }

    public function find(string $category, string $subcategory, string $language = 'en-us'): ?array
    {
        $key = Str::lower(trim($category)).'.'.Str::lower(trim($subcategory));
        foreach (array_unique([Str::lower($language), 'en-us']) as $candidate) {
            if ($definition = $this->definitions()[$key.'|'.$candidate] ?? null) {
                return $definition;
            }
        }

        return null;
    }

    private function parse(string $htmlPath, string $source, array $metadata): array
    {
        foreach (['version', 'language', 'category', 'subcategory', 'format', 'layout', 'subject', 'description'] as $key) {
            if (! filled($metadata[$key] ?? null)) {
                throw new RuntimeException("Missing {$key} metadata in {$htmlPath}");
            }
        }

        if (! preg_match('/^\d+\.\d+\.\d+$/', $metadata['version'])) {
            throw new RuntimeException("Invalid version metadata in {$htmlPath}");
        }

        $category = Str::lower(trim($metadata['category']));
        $subcategory = Str::lower(trim($metadata['subcategory']));
        $language = Str::lower(trim($metadata['language']));
        $layout = Str::lower($metadata['layout']);
        $relativePath = Str::after(
            str_replace('\\', '/', $htmlPath),
            str_replace('\\', '/', resource_path('views/emails')).'/'
        );
        $expectedPath = "{$category}/{$subcategory}.blade.php";
        if ($relativePath !== $expectedPath) {
            throw new RuntimeException(
                "Email template path must be {$expectedPath}; found {$relativePath}"
            );
        }

        $html = $this->stripFrontMatter($source);
        if ($layout === 'standard') {
            if (! preg_match(
                '/\A\s*@extends\([\'\"]emails\.email_layout[\'\"]\)\s*@section\([\'\"]content[\'\"]\)\s*.*?\s*@endsection\s*\z/s',
                $html
            )) {
                throw new RuntimeException("Standard email template must extend emails.email_layout in {$htmlPath}");
            }
        } elseif ($layout !== 'none') {
            throw new RuntimeException("Unsupported layout metadata in {$htmlPath}");
        }

        $textPath = preg_replace('/\.blade\.php$/', '-text.blade.php', $htmlPath);
        if (! is_string($textPath) || ! File::isFile($textPath)) {
            throw new RuntimeException("Missing plain-text email template companion for {$htmlPath}");
        }

        $textSource = File::get($textPath);
        $textMetadata = $this->parseFrontMatter($textSource);
        foreach (['format', 'layout'] as $key) {
            if (! filled($textMetadata[$key] ?? null)) {
                throw new RuntimeException("Missing {$key} metadata in {$textPath}");
            }
        }

        foreach (['version', 'language', 'category', 'subcategory', 'subject', 'description'] as $key) {
            if (array_key_exists($key, $textMetadata)) {
                throw new RuntimeException(
                    "Shared {$key} metadata belongs only in the HTML template: {$textPath}"
                );
            }
        }

        if (Str::lower($textMetadata['format']) !== 'text' || Str::lower($textMetadata['layout']) !== 'none') {
            throw new RuntimeException("Plain-text template must use format text and layout none in {$textPath}");
        }

        $text = trim($this->stripFrontMatter($textSource));
        if ($text === '') {
            throw new RuntimeException("Plain-text email template is empty in {$textPath}");
        }

        $this->renderer->assertSafe($metadata['subject']);
        $this->renderer->assertSafe($html);
        $this->renderer->assertSafe($text);

        return [
            'source_path' => $htmlPath,
            'template_key' => $category.'.'.$subcategory,
            'template_language' => $language,
            'template_category' => $category,
            'template_subcategory' => $subcategory,
            'template_layout' => $layout,
            'version' => $metadata['version'],
            'template_subject' => $metadata['subject'],
            'template_html' => $html,
            'template_text' => $text,
            'template_description' => $metadata['description'],
        ];
    }

    private function hasFrontMatter(string $source): bool
    {
        return preg_match('/\A(?:\xEF\xBB\xBF)?\s*\{\{--\s*email-template\b/s', substr($source, 0, 8192)) === 1;
    }

    private function parseFrontMatter(string $source): array
    {
        if (! preg_match('/\A(?:\xEF\xBB\xBF)?\s*\{\{--\s*(.*?)\s*--\}\}/s', substr($source, 0, 8192), $match)) {
            throw new RuntimeException('Missing email-template metadata.');
        }

        $metadata = [];
        foreach (preg_split('/\R/', trim($match[1])) as $line) {
            if (preg_match('/^\s*([A-Za-z0-9_-]+)\s*:\s*(.*?)\s*$/', $line, $parts)) {
                $metadata[Str::lower($parts[1])] = trim($parts[2]);
            }
        }

        return $metadata;
    }

    private function stripFrontMatter(string $source): string
    {
        return preg_replace('/\A(?:\xEF\xBB\xBF)?\s*\{\{--\s*.*?\s*--\}\}\s*/s', '', $source, 1) ?? $source;
    }
}
