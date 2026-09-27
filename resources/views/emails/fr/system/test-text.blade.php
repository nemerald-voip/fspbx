{{-- email-template
format: text
layout: none
--}}
Bonjour,

Ceci est un e-mail de test de {{ config('app.name', 'FS PBX') }}.

Si vous avez reçu ce message, le service de messagerie configuré peut envoyer des e-mails.

Envoyé le {{ $attributes['sent_at'] ?? now()->toDateTimeString() }}.
