<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

class AppCredentials extends BaseMailable
{
    public function __construct(array $attributes)
    {
        $payload = json_encode([
            'domain' => $attributes['domain'] ?? '',
            'username' => $attributes['username'] ?? '',
            'password' => $attributes['password'] ?? '',
        ]);

        $attributes['qrCodeUrl'] = URL::temporarySignedRoute(
            'appsMobileAppQr',
            now()->addDays(30),
            ['payload' => Crypt::encryptString($payload)]
        );

        $attributes['email_subject'] = config('app.name', 'FS PBX').' App Credentials';

        parent::__construct($attributes);
        $this->useEmailTemplate('app', 'credentials');
    }

    public function content(): Content
    {
        return $this->databaseTemplateContent(new Content(
            view: 'emails.app.credentials',
            text: 'emails.app.credentials-text',
        ));
    }
}
