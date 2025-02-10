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
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Add line unsubscrible header
        $this->buildMessageHeaders();

        return $this->from(config('mail.from.address'), config('mail.from.name'));
        // ->subject('Call history report');

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
