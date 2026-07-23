<?php

namespace App\Services;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Schema;

class EmailTemplateService
{
    public function __construct(
        private readonly EmailTemplateSourceService $sources,
        private readonly SafeEmailTemplateRenderer $renderer,
    ) {
    }

    public function resolve(
        string $category,
        string $subcategory,
        ?string $domainUuid,
        string $language = 'en-us'
    ): ?EmailTemplate {
        if (! Schema::hasTable('email_templates')) {
            return null;
        }

        $key = strtolower(trim($category)).'.'.strtolower(trim($subcategory));
        $languages = array_values(array_unique([strtolower($language), 'en-us']));

        foreach ($languages as $candidateLanguage) {
            $template = EmailTemplate::query()
                ->where('template_key', $key)
                ->where('template_language', $candidateLanguage)
                ->where('template_enabled', true)
                ->where(function ($query) use ($domainUuid) {
                    $query->where('template_type', 'default')
                        ->orWhere(function ($custom) use ($domainUuid) {
                            $custom->where('template_type', 'custom')
                                ->where(function ($scope) use ($domainUuid) {
                                    if ($domainUuid) {
                                        $scope->where('domain_uuid', $domainUuid)
                                            ->orWhereNull('domain_uuid');
                                    } else {
                                        $scope->whereNull('domain_uuid');
                                    }
                                });
                        });
                })
                ->orderByRaw(
                    "CASE
                        WHEN template_type = 'custom' AND domain_uuid = ? THEN 0
                        WHEN template_type = 'custom' AND domain_uuid IS NULL THEN 1
                        ELSE 2
                    END",
                    [$domainUuid]
                )
                ->orderByDesc('updated_at')
                ->first();

            if ($template) {
                return $template;
            }
        }

        return null;
    }

    public function render(
        string $category,
        string $subcategory,
        ?string $domainUuid,
        array $variables,
        string $language = 'en-us',
        array $fallback = []
    ): array {
        $template = $this->resolve($category, $subcategory, $domainUuid, $language);
        $definition = $template ? [
            'template_subject' => $template->template_subject,
            'template_html' => $template->template_html,
            'template_text' => $template->template_text,
            'template_layout' => $template->template_layout ?: 'standard',
        ] : null;

        if ($definition && $this->containsExecutableSyntax($definition)) {
            logger()->warning('Unsafe email template content was ignored.', [
                'email_template_uuid' => $template->email_template_uuid,
                'template_key' => $template->template_key,
            ]);
            $template = null;
            $definition = null;
        }

        $sourceDefinition = $this->sources->find($category, $subcategory, $language);
        $definition ??= $sourceDefinition;
        $subject = $definition['template_subject'] ?? ($fallback['subject'] ?? '');
        $html = $definition['template_html'] ?? ($fallback['html'] ?? '');
        $text = $definition['template_text']
            ?? $sourceDefinition['template_text']
            ?? ($fallback['text'] ?? null);
        $layout = $definition['template_layout'] ?? ($fallback['layout'] ?? 'none');

        return [
            'template' => $template,
            'available' => $definition !== null || $html !== '' || $subject !== '',
            'subject' => $this->renderer->renderSubject($subject, $variables),
            'html' => $this->renderer->renderHtml($html, $variables, $layout),
            'text' => $this->renderer->renderText($text, $variables),
        ];
    }

    public function containsExecutableSyntax(string|array|null $content): bool
    {
        if (is_array($content)) {
            return $this->renderer->containsExecutableSyntax($content['template_subject'] ?? null)
                || $this->renderer->containsExecutableSyntax($content['template_html'] ?? null)
                || $this->renderer->containsExecutableSyntax($content['template_text'] ?? null);
        }

        return $this->renderer->containsExecutableSyntax($content);
    }
}
