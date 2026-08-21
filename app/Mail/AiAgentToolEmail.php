<?php

namespace App\Mail;

use App\Models\DefaultSettings;
use App\Models\DomainSettings;
use Illuminate\Mail\Mailables\Content;

class AiAgentToolEmail extends BaseMailable
{
    public function __construct(array $params)
    {
        parent::__construct($this->applyDomainSender($params));
        $this->useEmailTemplate('ai-agent', 'send-email');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.ai-agent.send-email',
            text: 'emails.ai-agent.send-email-text',
        ));
    }

    private function applyDomainSender(array $params): array
    {
        $domainUuid = $params['domain_uuid'] ?? null;
        $subcategories = ['smtp_from', 'smtp_from_name'];
        $domainRows = collect();

        if ($domainUuid) {
            $domainRows = DomainSettings::query()
                ->selectRaw('domain_setting_subcategory AS subcategory, domain_setting_value AS value')
                ->where('domain_uuid', $domainUuid)
                ->where('domain_setting_category', 'email')
                ->whereIn('domain_setting_subcategory', $subcategories)
                ->where('domain_setting_enabled', true)
                ->get()
                ->filter(fn ($row) => filled($row->value))
                ->keyBy('subcategory');
        }

        $defaultRows = DefaultSettings::query()
            ->selectRaw('default_setting_subcategory AS subcategory, default_setting_value AS value')
            ->where('default_setting_category', 'email')
            ->whereIn('default_setting_subcategory', $subcategories)
            ->where('default_setting_enabled', true)
            ->get()
            ->filter(fn ($row) => filled($row->value))
            ->keyBy('subcategory');

        if ($fromEmail = $domainRows->get('smtp_from')?->value ?? $defaultRows->get('smtp_from')?->value) {
            $params['from_email'] = $fromEmail;
        }

        if ($fromName = $domainRows->get('smtp_from_name')?->value ?? $defaultRows->get('smtp_from_name')?->value) {
            $params['from_name'] = $fromName;
        }

        return $params;
    }
}
