<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class TestEmail extends BaseMailable
{
    public function __construct(array $attributes = [])
    {
        $attributes = array_merge([
            'email_subject' => 'Test email',
            'sent_at' => now()->toDateTimeString(),
        ], $attributes);
        parent::__construct($attributes);
        $this->useEmailTemplate('system', 'test');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.system.test',
            text: 'emails.system.test-text',
        ));
    }
}
