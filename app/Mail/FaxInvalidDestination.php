<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class FaxInvalidDestination extends BaseMailable
{
    public function __construct(array $attributes)
    {
        $attributes['email_subject'] = 'Fax to '.($attributes['invalid_number'] ?? '').' Failed - Invalid Fax Destination Number';
        parent::__construct($attributes);
        $this->useEmailTemplate('fax', 'invalid-destination');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.fax.invalid-destination',
            text: 'emails.fax.invalid-destination-text',
        ));
    }
}
