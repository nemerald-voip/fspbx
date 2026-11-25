<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;

class LoginVerificationCode extends BaseMailable
{
    public static function defaultSubject(): string
    {
        return config('app.name', 'FS PBX').' two factor verification code';
    }

    public function __construct(array $attributes)
    {
        $attributes['greeting_name'] = filled($attributes['name'] ?? null)
            ? ' '.$attributes['name']
            : '';
        $attributes['email_subject'] = self::defaultSubject();

        parent::__construct($attributes);
        $this->useEmailTemplate('authentication', 'verification-code');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.en-us.authentication.verification-code',
            text: 'emails.en-us.authentication.verification-code-text',
        ));
    }
}
