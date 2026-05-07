<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Attachment;
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

        parent::__construct($attributes);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.fax.success',
            text: 'emails.fax.success-text',
        );
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
                ->withMime($this->attributes['attachment_mime'] ?? 'application/pdf'),
        ];
    }
}
