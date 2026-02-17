<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;


class FaxQueueStatus extends BaseMailable
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
            view: 'emails.fax.fax-queue-status',
            text: 'emails.fax.fax-queue-status-text'
        );
    }
}
