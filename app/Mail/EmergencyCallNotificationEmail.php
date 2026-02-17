<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;


class EmergencyCallNotificationEmail extends BaseMailable
{

    public function __construct($params)
    {
        parent::__construct($params);
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.emergency-call.notification',
            text: 'emails.emergency-call.notification-text'
        );
    }
}
