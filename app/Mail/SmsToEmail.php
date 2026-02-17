<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;


class SmsToEmail extends BaseMailable
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
            view: 'emails.messages.message',
            text: 'emails.messages.message-text'
        );
    }
}
