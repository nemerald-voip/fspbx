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

        parent::__construct($params);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.missed-call.notification',
            text: 'emails.missed-call.notification-text',
        );
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
