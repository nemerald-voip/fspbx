<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;


class CdrExportCompleted extends BaseMailable
{

    public function __construct($fileUrl)
    {
        $attributes['fileUrl'] = $fileUrl; // Add your additional variable here
        parent::__construct($attributes);
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

        return $this->from(config('mail.from.address'), config('mail.from.name'))
        ->subject('Call history report');

    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.export.export-completed',
            text: 'emails.export.export-completed-text'
        );
    }
}
