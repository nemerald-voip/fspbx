<?php

namespace App\Mail;

use App\Models\DomainSettings;
use App\Models\DefaultSettings;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Facades\Storage;

class VoicemailNotification extends BaseMailable
{
    public function __construct(array $params)
    {
        $cats = ['voicemail', 'email'];
        $subs = ['smtp_from', 'smtp_from_name'];

        $domainRows = DomainSettings::query()
            ->selectRaw("
                domain_setting_category AS category,
                domain_setting_subcategory AS subcategory,
                domain_setting_value AS value,
                domain_setting_enabled AS enabled
            ")
            ->where('domain_uuid', $params['domain_uuid'])
            ->whereIn('domain_setting_category', $cats)
            ->whereIn('domain_setting_subcategory', $subs)
            ->where('domain_setting_enabled', true)
            ->get()
            ->filter(fn ($row) => filled($row->value))
            ->values();

        $defaultRows = DefaultSettings::query()
            ->selectRaw("
                default_setting_category AS category,
                default_setting_subcategory AS subcategory,
                default_setting_value AS value,
                default_setting_enabled AS enabled
            ")
            ->whereIn('default_setting_category', $cats)
            ->whereIn('default_setting_subcategory', $subs)
            ->where('default_setting_enabled', true)
            ->get()
            ->filter(fn ($row) => filled($row->value))
            ->values();

        $indexByCatSub = function ($rows) {
            $out = [];
            foreach ($rows as $row) {
                $out["{$row->category}.{$row->subcategory}"] = $row->value;
            }

            return $out;
        };

        $domainSettings = $indexByCatSub($domainRows);
        $defaultSettings = $indexByCatSub($defaultRows);

        $pick = function (string $sub) use ($domainSettings, $defaultSettings) {
            return $domainSettings["voicemail.$sub"]
                ?? $domainSettings["email.$sub"]
                ?? $defaultSettings["voicemail.$sub"]
                ?? $defaultSettings["email.$sub"]
                ?? null;
        };

        if ($val = $pick('smtp_from')) {
            $params['from_email'] = $val;
        }
        if ($val = $pick('smtp_from_name')) {
            $params['from_name'] = $val;
        }

        parent::__construct($params);
        $this->useEmailTemplate(
            'voicemail',
            $this->attributes['template_subcategory'] ?? 'default'
        );
    }

    public function content(): Content
    {
        $subcategory = $this->attributes['template_subcategory'] ?? 'default';

        return $this->databaseTemplateContent(new Content(
            view: "emails.voicemail.{$subcategory}",
            text: "emails.voicemail.{$subcategory}-text",
        ));
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        if (! empty($this->attributes['attachment_path'])) {
            $path = $this->attributes['attachment_path'];
            $disk = 'voicemail';

            if (Storage::disk($disk)->exists($path)) {
                $extension = pathinfo($path, PATHINFO_EXTENSION);
                $friendlyFilename = 'voicemail.' . $extension;

                return [
                    Attachment::fromStorageDisk($disk, $path)
                        ->as($friendlyFilename)
                        ->withMime('audio/' . ($extension === 'mp3' ? 'mpeg' : 'wav')),
                ];
            }
        }

        return [];
    }
}
