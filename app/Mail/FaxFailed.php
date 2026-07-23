<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class FaxFailed extends BaseMailable
{
    public function __construct(array $attributes)
    {
        $attributes['email_subject'] = 'Re: fax to '.($attributes['fax_destination'] ?? '').' Failed';
        parent::__construct($attributes);
        $this->useEmailTemplate('fax', 'failed');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.fax.failed',
            text: 'emails.fax.failed-text',
        ));
    }
}
