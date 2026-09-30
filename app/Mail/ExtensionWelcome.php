<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class ExtensionWelcome extends BaseMailable
{
    public function __construct(array $attributes = [])
    {
        $attributes['email_subject'] = $attributes['email_subject']
            ?? 'Your extension '.($attributes['extension'] ?? '').' is ready';

        parent::__construct($attributes);
        $this->useEmailTemplate('extension', 'welcome');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.en-us.extension.welcome',
            text: 'emails.en-us.extension.welcome-text',
        ));
    }
}
