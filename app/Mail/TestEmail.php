<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class TestEmail extends BaseMailable
{
    public function __construct(array $attributes = [])
    {
        parent::__construct(array_merge([
            'email_subject' => 'Test email',
        ], $attributes));
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.test-email',
            text: 'emails.test-email-text',
        );
    }
}
