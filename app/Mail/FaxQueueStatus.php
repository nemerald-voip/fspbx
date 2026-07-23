<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class FaxQueueStatus extends BaseMailable
{
    public function __construct(array $params)
    {
        parent::__construct($params);
        $this->useEmailTemplate('fax', 'service-alert');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.fax.service-alert',
            text: 'emails.fax.service-alert-text',
        ));
    }
}
