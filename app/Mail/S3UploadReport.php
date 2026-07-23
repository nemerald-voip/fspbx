<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class S3UploadReport extends BaseMailable
{
    public function __construct(array $attributes)
    {
        $attributes['email_subject'] = $attributes['email_subject'] ?? 'Archiving storage report';

        parent::__construct($attributes);
        $this->useEmailTemplate('archive', 'storage-report');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.archive.storage-report',
            text: 'emails.archive.storage-report-text',
        ));
    }
}
