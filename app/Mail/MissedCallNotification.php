<?php

namespace App\Mail;

use App\Models\DomainSettings;
use App\Models\DefaultSettings;
use Illuminate\Mail\Mailables\Content;

class MissedCallNotification extends BaseMailable
{
    public function __construct(array $params)
    {
        $params = $this->applyDomainSender($params);
        $params['caller_display'] = trim(
            ($params['caller_id_name'] ?? '').
            (filled($params['caller_id_number'] ?? null) ? ' <'.$params['caller_id_number'].'>' : '')
        ) ?: 'Unknown caller';
        $params['ring_group_display'] = trim(
            ($params['ring_group_name'] ?? '').
            (filled($params['ring_group_extension'] ?? null) ? ' ext '.$params['ring_group_extension'] : '')
        ) ?: 'Ring group';
        parent::__construct($params);
        $this->useEmailTemplate('missed', 'ring-group');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.missed.ring-group',
            text: 'emails.missed.ring-group-text',
        ));
    }

    private function applyDomainSender(array $params): array
    {
        $domainUuid = $params['domain_uuid'] ?? null;
        $subs = ['smtp_from', 'smtp_from_name'];

        $domainRows = collect();
        if ($domainUuid) {
            $domainRows = DomainSettings::query()
                ->selectRaw("
                    domain_setting_subcategory AS subcategory,
                    domain_setting_value AS value
                ")
                ->where('domain_uuid', $domainUuid)
                ->where('domain_setting_category', 'email')
                ->whereIn('domain_setting_subcategory', $subs)
                ->where('domain_setting_enabled', true)
                ->get()
                ->filter(fn ($row) => filled($row->value))
                ->keyBy('subcategory');
        }

        $defaultRows = DefaultSettings::query()
            ->selectRaw("
                default_setting_subcategory AS subcategory,
                default_setting_value AS value
            ")
            ->where('default_setting_category', 'email')
            ->whereIn('default_setting_subcategory', $subs)
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
