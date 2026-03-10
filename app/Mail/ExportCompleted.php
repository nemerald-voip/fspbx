<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;


class ExportCompleted extends BaseMailable
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
            view: 'emails.export.export-completed',
            text: 'emails.export.export-completed-text'
        );
    }
}
