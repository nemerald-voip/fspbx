<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class EmergencyCallNotificationEmail extends BaseMailable
{
    public function __construct(array $params)
    {
        parent::__construct($params);
        $this->useEmailTemplate('emergency', 'call');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.emergency.call',
            text: 'emails.emergency.call-text',
        ));
    }
}
