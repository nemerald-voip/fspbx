<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class FaxNotAuthorized extends BaseMailable
{
    public function __construct(array $attributes)
    {
        $attributes['email_subject'] = 'Email Not Authorized';
        parent::__construct($attributes);
        $this->useEmailTemplate('fax', 'not-authorized');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.fax.not-authorized',
            text: 'emails.fax.not-authorized-text',
        ));
    }
}
