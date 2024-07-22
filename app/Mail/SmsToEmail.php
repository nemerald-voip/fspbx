<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;


class SmsToEmail extends BaseMailable
{
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
