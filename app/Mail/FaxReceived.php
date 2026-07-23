<?php

namespace App\Mail;

use App\Models\DomainSettings;
use App\Models\DefaultSettings;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;

class FaxReceived extends BaseMailable
{
    public function __construct(array $attributes = [])
    {
        $attributes = $this->withFaxSenderSettings($attributes);

        $sender = $attributes['caller_id_number']
            ?? $attributes['caller_id_name']
            ?? '';
        $pages = $attributes['fax_pages'] ?? '';

        $attributes['email_subject'] = $attributes['email_subject']
            ?? 'Fax received from ' . $sender
                . ($pages !== '' ? ' (' . $pages . ' page' . ((string) $pages === '1' ? '' : 's') . ')' : '');
        $attributes['caller_display'] = trim(
            ($attributes['caller_id_name'] ?? '').
            (filled($attributes['caller_id_number'] ?? null) ? ' <'.$attributes['caller_id_number'].'>' : '')
        ) ?: 'Unknown sender';
        $attributes['fax_destination'] = $attributes['fax_destination']
            ?? $attributes['fax_extension']
            ?? 'your fax line';
        parent::__construct($attributes);
        $this->useEmailTemplate('fax', 'received');
    }

    private function withFaxSenderSettings(array $attributes): array
    {
        $domainRows = [];
        $domainUuid = $attributes['domain_uuid'] ?? null;

        if ($domainUuid) {
            $domainRows = DomainSettings::query()
                ->selectRaw('domain_setting_subcategory as subcategory, domain_setting_value as value')
                ->where('domain_uuid', $domainUuid)
                ->where('domain_setting_category', 'fax')
                ->whereIn('domain_setting_subcategory', ['smtp_from', 'smtp_from_name'])
                ->where('domain_setting_enabled', 'true')
                ->get();
        }

        $defaultRows = DefaultSettings::query()
            ->selectRaw('default_setting_subcategory as subcategory, default_setting_value as value')
            ->where('default_setting_category', 'fax')
            ->whereIn('default_setting_subcategory', ['smtp_from', 'smtp_from_name'])
            ->where('default_setting_enabled', 'true')
            ->get();

        $fromEmail = $this->settingValue($domainRows, 'smtp_from')
            ?? $this->settingValue($defaultRows, 'smtp_from');
        $fromName = $this->settingValue($domainRows, 'smtp_from_name')
            ?? $this->settingValue($defaultRows, 'smtp_from_name');

        if ($fromEmail !== null) {
            $attributes['from_email'] = $fromEmail;
        }

        if ($fromName !== null) {
            $attributes['from_name'] = $fromName;
        }

        return $attributes;
    }

    private function settingValue(iterable $settings, string $subcategory): ?string
    {
        foreach ($settings as $setting) {
            if (($setting->subcategory ?? null) !== $subcategory) {
                continue;
            }

            $value = trim((string) ($setting->value ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.fax.received',
            text: 'emails.fax.received-text',
        ));
    }

    public function attachments(): array
    {
        $path = $this->attributes['attachment_path'] ?? null;

        if (!$path || !is_file($path)) {
            return [];
        }

        return [
            Attachment::fromPath($path)
                ->as($this->attributes['attachment_name'] ?? basename($path))
                ->withMime($this->attributes['attachment_mime'] ?? 'application/octet-stream'),
        ];
    }
}
