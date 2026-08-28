<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class ResetPasswordMail extends BaseMailable
{
    public function __construct(array $attributes)
    {
        $attributes['greeting_name'] = filled($attributes['name'] ?? null)
            ? ' '.$attributes['name']
            : '';
        $attributes['email_subject'] = 'Reset Password Notification';

        parent::__construct($attributes);
        $this->useEmailTemplate('authentication', 'reset-password');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.authentication.reset-password',
            text: 'emails.authentication.reset-password-text',
        ));
    }
}
