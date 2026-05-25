<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class AiReceptionistRouteNotification extends BaseMailable
{
    public function __construct($params)
    {
        parent::__construct($params);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ai-receptionist.route-notification',
            text: 'emails.ai-receptionist.route-notification-text'
        );
    }
}
