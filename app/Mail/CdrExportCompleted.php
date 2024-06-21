<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;


class CdrExportCompleted extends BaseMailable
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

        return $this->from(config('mail.from.address'), config('mail.from.name'))
        ->subject('Call history report')
            ->from($this->attributes['smtp_from'], $this->attributes['smtp_from_name']);

    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.export.completed',
            text: 'emails.export.completed-text'
        );
    }
}
