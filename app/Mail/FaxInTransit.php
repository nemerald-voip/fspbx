<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class FaxInTransit extends BaseMailable
{
    public function __construct(array $attributes)
    {
        $attributes['email_subject'] = 'Re: fax to '.($attributes['fax_destination'] ?? '');
        parent::__construct($attributes);
        $this->useEmailTemplate('fax', 'in-transit');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.fax.in-transit',
            text: 'emails.fax.in-transit-text',
        ));
    }
}
