<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class ExportCompleted extends BaseMailable
{
    public function __construct(array $params)
    {
        parent::__construct($params);
        $this->useEmailTemplate('export', 'completed');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.export.completed',
            text: 'emails.export.completed-text',
        ));
    }
}
