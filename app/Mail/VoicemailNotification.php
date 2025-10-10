<?php

namespace App\Mail;

use App\Models\DomainSettings;
use App\Models\DefaultSettings;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Facades\Storage;
use Illuminate\Mail\Mailables\Attachment;


class VoicemailNotification extends BaseMailable
{

    public function __construct($params)
    {
        // Get smtp_from params

        $cats = ['voicemail', 'email'];
        $subs = ['smtp_from', 'smtp_from_name'];

        // 1) Domain overrides (single query)
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
            ->filter(fn($r) => filled($r->value))
            ->values();

        // 2) Global defaults (single query)
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
            ->filter(fn($r) => filled($r->value))
            ->values();

        // Index helpers: ['category.subcategory' => value]
        $indexByCatSub = function ($rows) {
            $out = [];
            foreach ($rows as $r) {
                $out["{$r->category}.{$r->subcategory}"] = $r->value;
            }
            return $out;
        };

        $D = $indexByCatSub($domainRows);   // domain-level overrides
        $G = $indexByCatSub($defaultRows);  // global defaults

        $pick = function (string $sub) use ($D, $G) {
            // priority: domain.voicemail → domain.email → global.voicemail → global.email
            return $D["voicemail.$sub"]
                ?? $D["email.$sub"]
                ?? $G["voicemail.$sub"]
                ?? $G["email.$sub"]
                ?? null;
        };

        // assign if found
        if ($val = $pick('smtp_from')) {
            $params['from_email'] = $val;
        }
        if ($val = $pick('smtp_from_name')) {
            $params['from_name'] = $val;
        }

        parent::__construct($params);
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->attributes['bodyHtml'] ?? '<!-- empty -->',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        // Check if the attachment path attribute exists and is not null
        if (!empty($this->attributes['attachment_path'])) {
            $path = $this->attributes['attachment_path'];
            $disk = 'voicemail';

            // Double-check that the file exists on the disk before attaching
            if (Storage::disk($disk)->exists($path)) {
                // Get the file extension to create a user-friendly filename
                $extension = pathinfo($path, PATHINFO_EXTENSION);
                $friendlyFilename = 'voicemail.' . $extension;

                return [
                    // Create an attachment from your 'voicemail' storage disk
                    Attachment::fromStorageDisk($disk, $path)
                        ->as($friendlyFilename)
                        ->withMime('audio/' . ($extension == 'mp3' ? 'mpeg' : 'wav')),
                ];
            }
        }

        // If no path or file doesn't exist, return an empty array
        return [];
    }
    
}
