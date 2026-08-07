<?php

namespace App\Mail;

use App\Models\DefaultSettings;
use App\Models\DomainSettings;
use Illuminate\Mail\Mailables\Content;

class ContactCenterAbandonedCallNotification extends BaseMailable
{
    public function __construct(array $params)
    {
        $params = $this->applyDomainSender($params);
        $params['caller_display'] = trim(
            ($params['caller_id_name'] ?? '').
            (filled($params['caller_id_number'] ?? null) ? ' <'.$params['caller_id_number'].'>' : '')
        ) ?: 'Unknown caller';
        $params['queue_display'] = trim(
            ($params['queue_name'] ?? '').
            (filled($params['queue_extension'] ?? null) ? ' ext '.$params['queue_extension'] : '')
        ) ?: 'Contact Center';

        parent::__construct($params);
        $this->useEmailTemplate('missed', 'contact-center');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.missed.contact-center',
            text: 'emails.missed.contact-center-text',
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

        $fromEmail = $domainRows->get('smtp_from')?->value
            ?? $defaultRows->get('smtp_from')?->value;
        $fromName = $domainRows->get('smtp_from_name')?->value
            ?? $defaultRows->get('smtp_from_name')?->value;

        if ($fromEmail) {
            $params['from_email'] = $fromEmail;
        }

        if ($fromName) {
            $params['from_name'] = $fromName;
        }

        return $params;
    }
}
