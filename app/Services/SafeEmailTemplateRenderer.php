<?php

namespace App\Services;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use RuntimeException;

class SafeEmailTemplateRenderer
{
    public function containsExecutableSyntax(?string $content): bool
    {
        if (! is_string($content) || $content === '') {
            return false;
        }

        return preg_match('/<\?|@(?:php|endphp)\b/i', $content) === 1
            || preg_match('/<\s*script\b|\son[a-z]+\s*=|(?:href|src)\s*=\s*["\']?\s*(?:javascript|data\s*:\s*text\/html)\s*:/i', $content) === 1;
    }

    public function renderHtml(
        string $body,
        array $variables,
        string $layout = 'standard'
    ): string
    {
        $this->assertSafe($body);

        $template = match ($layout) {
            'standard' => $this->withStandardLayout($body),
            'none' => $body,
            default => throw new RuntimeException("Unsupported email template layout: {$layout}"),
        };

        return $this->renderString($this->renderBlade($template, $variables), $variables, true);
    }

    public function renderText(?string $text, array $variables): ?string
    {
        if ($text === null) {
            return null;
        }

        $this->assertSafe($text);

        return $this->renderString($this->renderBlade($text, $variables), $variables, false);
    }

    public function renderSubject(string $subject, array $variables): string
    {
        $this->assertSafe($subject);

        $rendered = $this->renderString($this->renderBlade($subject, $variables), $variables, false);

        // The subject is a plain-text email header, but Blade's {{ }} echo
        // HTML-escapes its output (e.g. "<6467052267>" becomes "&lt;...&gt;").
        // Decode those entities so the subject reads as plain text.
        $rendered = html_entity_decode($rendered, ENT_QUOTES | ENT_HTML5);

        return trim(str_replace(["\r", "\n"], ' ', $rendered));
    }

    public function assertSafe(?string $content): void
    {
        if ($this->containsExecutableSyntax($content)) {
            throw new RuntimeException('Email templates may use Blade, but @php, raw PHP tags, and scripts are not allowed.');
        }
    }

    private function renderBlade(string $template, array $variables): string
    {
        $data = $variables;
        $data['attributes'] = $variables;
        $data['data'] = $variables;

        return Blade::render($template, $data, true);
    }

    private function renderString(
        string $template,
        array $variables,
        bool $escapeHtml
    ): string
    {
        return preg_replace_callback('/\$\{([A-Za-z0-9_.-]+)\}/', function (array $match) use ($variables, $escapeHtml) {
            $value = data_get($variables, $match[1], '');
            if ($value instanceof HtmlString) {
                return $escapeHtml ? $value->toHtml() : strip_tags($value->toHtml());
            }

            if (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            } elseif (! is_scalar($value) && $value !== null) {
                $value = '';
            }

            $value = (string) $value;

            return $escapeHtml ? e($value) : $value;
        }, $template) ?? $template;
    }

    private function withStandardLayout(string $template): string
    {
        if (preg_match('/@extends\([\'"]emails\.email_layout[\'"]\)/', $template) === 1) {
            return $template;
        }

        return "@extends('emails.email_layout')\n@section('content')\n{$template}\n@endsection";
    }
}
