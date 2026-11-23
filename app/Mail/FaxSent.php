<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class FaxSent extends BaseMailable
{
    public function __construct(array $attributes = [])
    {
        $destination = $attributes['fax_destination'] ?? '';
        $pages       = $attributes['fax_pages'] ?? '';

        $attributes['email_subject'] = $attributes['email_subject']
            ?? 'Fax sent'
                . ($destination ? ' to ' . $destination : '')
                . ($pages !== '' ? ' (' . $pages . ' page' . ((string) $pages === '1' ? '' : 's') . ')' : '');
        if (!isset($attributes['fax_date'])) {
            $domainUuid = $attributes['domain_uuid'] ?? null;
            $attributes['fax_date'] = now(get_local_time_zone($domainUuid))->format(local_datetime_format($domainUuid, false));
        }
        parent::__construct($attributes);
        $this->useEmailTemplate('fax', 'sent');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.en-us.fax.sent',
            text: 'emails.en-us.fax.sent-text',
        ));
    }

    public function attachments(): array
    {
        $path = $this->attributes['attachment_path'] ?? null;

        if (!$path || !is_file($path)) {
            return $this->attachmentsFromReferences([]);
        }

        return $this->attachmentsFromReferences([[
            'path' => $path,
            'name' => $this->attributes['attachment_name'] ?? basename($path),
            'mime' => $this->attributes['attachment_mime'] ?? 'application/pdf',
        ]]);
    }
}
