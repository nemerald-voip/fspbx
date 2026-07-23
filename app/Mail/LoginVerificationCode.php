<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class LoginVerificationCode extends BaseMailable
{
    public function __construct(array $attributes)
    {
        $attributes['greeting_name'] = filled($attributes['name'] ?? null)
            ? ' '.$attributes['name']
            : '';
        $attributes['email_subject'] = config('app.name', 'FS PBX').' two factor verification code';

        parent::__construct($attributes);
        $this->useEmailTemplate('authentication', 'verification-code');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.authentication.verification-code',
            text: 'emails.authentication.verification-code-text',
        ));
    }
}
